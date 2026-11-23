<?php
/**
 * Jedinična cijena (neto količina).
 *
 * @package Farmaceut_Cjenik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Izračun i HTML cijene za jedinicu mjere.
 */
class Farmaceut_Cjenik_Jedinicna_Cijena {

	public const META_KOLICINA = '_farmaceut_neto_kolicina';
	public const META_JEDINICA = '_farmaceut_neto_jedinica';

	/**
	 * Dopuštene jedinice.
	 *
	 * @return string[]
	 */
	public static function dopustene_jedinice(): array {
		return array( 'g', 'kg', 'ml', 'l', 'kom' );
	}

	/**
	 * @param mixed $vrijednost Raw.
	 * @return string|null
	 */
	public static function normaliziraj_kolicinu( $vrijednost ): ?string {
		if ( null === $vrijednost || '' === $vrijednost ) {
			return null;
		}
		if ( is_string( $vrijednost ) ) {
			$vrijednost = str_replace( ',', '.', $vrijednost );
		}
		if ( ! is_numeric( $vrijednost ) || (float) $vrijednost <= 0 ) {
			return null;
		}
		return wc_format_decimal( (float) $vrijednost, 4 );
	}

	/**
	 * @param string $jedinica Unit.
	 * @return string|null
	 */
	public static function normaliziraj_jedinicu( string $jedinica ): ?string {
		$jedinica = strtolower( sanitize_key( $jedinica ) );
		return in_array( $jedinica, self::dopustene_jedinice(), true ) ? $jedinica : null;
	}

	/**
	 * Dohvaća neto podatke.
	 *
	 * @param WC_Product $proizvod Product.
	 * @return array{kolicina:string,jedinica:string}|null
	 */
	public static function dohvati( WC_Product $proizvod ): ?array {
		$k = self::normaliziraj_kolicinu( $proizvod->get_meta( self::META_KOLICINA, true ) );
		$j = self::normaliziraj_jedinicu( (string) $proizvod->get_meta( self::META_JEDINICA, true ) );
		if ( null === $k || null === $j ) {
			return null;
		}
		return array(
			'kolicina' => $k,
			'jedinica' => $j,
		);
	}

	/**
	 * Sprema neto.
	 *
	 * @param WC_Product $proizvod Product.
	 * @param mixed      $kolicina Qty.
	 * @param string     $jedinica Unit.
	 * @return true|WP_Error
	 */
	public static function spremi( WC_Product $proizvod, $kolicina, string $jedinica ) {
		if ( '' === $kolicina || null === $kolicina ) {
			$proizvod->delete_meta_data( self::META_KOLICINA );
			$proizvod->delete_meta_data( self::META_JEDINICA );
			$proizvod->save();
			do_action( 'farmaceut_cjenik_promijenjen', $proizvod->get_id() );
			return true;
		}

		$k = self::normaliziraj_kolicinu( $kolicina );
		$j = self::normaliziraj_jedinicu( $jedinica );
		if ( null === $k || null === $j ) {
			return new WP_Error( 'neispravna_jedinicna', __( 'Neispravna neto količina ili jedinica.', 'farmaceut-cjenik' ) );
		}

		$proizvod->update_meta_data( self::META_KOLICINA, $k );
		$proizvod->update_meta_data( self::META_JEDINICA, $j );
		$proizvod->save();
		do_action( 'farmaceut_cjenik_promijenjen', $proizvod->get_id() );
		return true;
	}

	/**
	 * Izračun cijene po referentnoj jedinici (100 g / 100 ml / 1 kg / 1 l / 1 kom).
	 *
	 * @param float  $maloprodajna Retail.
	 * @param float  $kolicina     Net qty.
	 * @param string $jedinica     Unit.
	 * @return array{iznos:float,oznaka:string}|null
	 */
	public static function izracunaj( float $maloprodajna, float $kolicina, string $jedinica ): ?array {
		if ( $maloprodajna < 0 || $kolicina <= 0 ) {
			return null;
		}

		switch ( $jedinica ) {
			case 'g':
				return array(
					'iznos'  => ( $maloprodajna / $kolicina ) * 100,
					'oznaka' => '100 g',
				);
			case 'ml':
				return array(
					'iznos'  => ( $maloprodajna / $kolicina ) * 100,
					'oznaka' => '100 ml',
				);
			case 'kg':
				return array(
					'iznos'  => $maloprodajna / $kolicina,
					'oznaka' => 'kg',
				);
			case 'l':
				return array(
					'iznos'  => $maloprodajna / $kolicina,
					'oznaka' => 'l',
				);
			case 'kom':
				return array(
					'iznos'  => $maloprodajna / $kolicina,
					'oznaka' => 'kom',
				);
			default:
				return null;
		}
	}

	/**
	 * Iznimka NN 105/2026: < 50 g ili < 50 ml.
	 *
	 * @param float  $kolicina Qty.
	 * @param string $jedinica Unit.
	 * @return bool
	 */
	public static function je_izuzeto( float $kolicina, string $jedinica ): bool {
		if ( 'g' === $jedinica && $kolicina < 50 ) {
			return true;
		}
		if ( 'ml' === $jedinica && $kolicina < 50 ) {
			return true;
		}
		return false;
	}

	/**
	 * HTML jedinične cijene.
	 *
	 * @param WC_Product $proizvod Product.
	 * @return string
	 */
	public static function html( WC_Product $proizvod ): string {
		$neto = self::dohvati( $proizvod );
		if ( ! $neto ) {
			return '';
		}
		if ( self::je_izuzeto( (float) $neto['kolicina'], $neto['jedinica'] ) ) {
			return '';
		}

		$cijena = (float) $proizvod->get_price();
		$izr    = self::izracunaj( $cijena, (float) $neto['kolicina'], $neto['jedinica'] );
		if ( ! $izr ) {
			return '';
		}

		$tekst = sprintf(
			'%s / %s',
			wp_kses_post( wc_price( $izr['iznos'] ) ),
			esc_html( $izr['oznaka'] )
		);

		return '<div class="farmaceut-cjenik__jedinicna" data-farmaceut-cjenik-jedinicna><span class="farmaceut-cjenik__jedinicna-label">' . esc_html__( 'Cijena za jedinicu mjere:', 'farmaceut-cjenik' ) . '</span> <span class="farmaceut-cjenik__jedinicna-iznos">' . $tekst . '</span></div>';
	}

	/**
	 * Vrijednost za CSV (decimal ili prazno).
	 *
	 * @param WC_Product $proizvod Product.
	 * @return array{neto:string,jedinica:string,jedinicna:string}
	 */
	public static function za_csv( WC_Product $proizvod ): array {
		$prazno = array(
			'neto'      => '',
			'jedinica'  => '',
			'jedinicna' => '',
		);
		$neto = self::dohvati( $proizvod );
		if ( ! $neto || self::je_izuzeto( (float) $neto['kolicina'], $neto['jedinica'] ) ) {
			return $prazno;
		}
		$izr = self::izracunaj( (float) $proizvod->get_price(), (float) $neto['kolicina'], $neto['jedinica'] );
		if ( ! $izr ) {
			return $prazno;
		}
		return array(
			'neto'      => $neto['kolicina'],
			'jedinica'  => $neto['jedinica'],
			'jedinicna' => wc_format_decimal( $izr['iznos'], wc_get_price_decimals() ),
		);
	}
}

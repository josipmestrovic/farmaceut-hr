<?php
/**
 * Omnibus: povijest cijena i najniža u 30 dana prije akcije.
 *
 * @package Farmaceut_Cjenik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bilježi povijest i prikazuje referentnu cijenu uz sniženje.
 */
class Farmaceut_Cjenik_Omnibus {

	public const META_POVIJEST   = '_farmaceut_povijest_cijena';
	public const META_REFERENCA  = '_farmaceut_omnibus_referenca';
	public const META_REFERENCA_OD = '_farmaceut_omnibus_od';

	/**
	 * Init hookova.
	 */
	public static function init(): void {
		add_action( 'woocommerce_update_product', array( __CLASS__, 'pri_azuriranju_proizvoda' ), 20 );
		add_action( 'woocommerce_update_product_variation', array( __CLASS__, 'pri_azuriranju_proizvoda' ), 20 );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'pri_azuriranju_proizvoda' ), 20 );
		add_action( 'woocommerce_new_product_variation', array( __CLASS__, 'pri_azuriranju_proizvoda' ), 20 );
	}

	/**
	 * Bilježi cijenu i zamrzava Omnibus referencu kad krene akcija.
	 *
	 * @param int $id Product ID.
	 */
	public static function pri_azuriranju_proizvoda( int $id ): void {
		$proizvod = wc_get_product( $id );
		if ( ! $proizvod || $proizvod->is_type( 'variable' ) ) {
			return;
		}

		$cijena = (float) $proizvod->get_price();
		self::dodaj_u_povijest( $proizvod, $cijena );

		if ( $proizvod->is_on_sale() ) {
			$postojeca = $proizvod->get_meta( self::META_REFERENCA, true );
			if ( '' === $postojeca || null === $postojeca ) {
				$naj = self::najniza_u_razdoblju( $proizvod, 30 );
				if ( null !== $naj ) {
					$proizvod->update_meta_data( self::META_REFERENCA, wc_format_decimal( $naj, wc_get_price_decimals() ) );
					$proizvod->update_meta_data( self::META_REFERENCA_OD, gmdate( 'Y-m-d' ) );
					$proizvod->save();
				}
			}
		} else {
			if ( $proizvod->get_meta( self::META_REFERENCA, true ) ) {
				$proizvod->delete_meta_data( self::META_REFERENCA );
				$proizvod->delete_meta_data( self::META_REFERENCA_OD );
				$proizvod->save();
			}
		}
	}

	/**
	 * Dodaje zapis u povijest.
	 *
	 * @param WC_Product $proizvod Product.
	 * @param float      $cijena   Price.
	 */
	public static function dodaj_u_povijest( WC_Product $proizvod, float $cijena ): void {
		$lista = $proizvod->get_meta( self::META_POVIJEST, true );
		if ( ! is_array( $lista ) ) {
			$lista = array();
		}

		$sada   = time();
		$zapis  = array(
			'c' => wc_format_decimal( $cijena, wc_get_price_decimals() ),
			't' => $sada,
		);

		$zadnji = end( $lista );
		if ( is_array( $zadnji ) && isset( $zadnji['c'] ) && (string) $zadnji['c'] === (string) $zapis['c'] ) {
			return;
		}

		$lista[] = $zapis;

		// Zadrži ~120 dana zapisa.
		$granica = $sada - ( 120 * DAY_IN_SECONDS );
		$lista   = array_values(
			array_filter(
				$lista,
				static function ( $red ) use ( $granica ) {
					return is_array( $red ) && isset( $red['t'] ) && (int) $red['t'] >= $granica;
				}
			)
		);

		$proizvod->update_meta_data( self::META_POVIJEST, $lista );
		$proizvod->save();
	}

	/**
	 * Najniža cijena u N dana prije sada (ili prije zamrznutog početka).
	 *
	 * @param WC_Product $proizvod Product.
	 * @param int        $dana     Days.
	 * @param int|null   $do_ts    End timestamp (exclusive sale start).
	 * @return float|null
	 */
	public static function najniza_u_razdoblju( WC_Product $proizvod, int $dana = 30, ?int $do_ts = null ): ?float {
		$lista = $proizvod->get_meta( self::META_POVIJEST, true );
		if ( ! is_array( $lista ) || ! $lista ) {
			$c = (float) $proizvod->get_regular_price();
			return $c > 0 ? $c : null;
		}

		$do_ts = $do_ts ?? time();
		$od_ts = $do_ts - ( $dana * DAY_IN_SECONDS );
		$min   = null;

		foreach ( $lista as $red ) {
			if ( ! is_array( $red ) || ! isset( $red['c'], $red['t'] ) ) {
				continue;
			}
			$t = (int) $red['t'];
			if ( $t < $od_ts || $t > $do_ts ) {
				continue;
			}
			$c = (float) $red['c'];
			$min = null === $min ? $c : min( $min, $c );
		}

		if ( null === $min ) {
			$c = (float) $proizvod->get_regular_price();
			return $c > 0 ? $c : null;
		}
		return $min;
	}

	/**
	 * Zamrznuta referenca za aktivnu akciju.
	 *
	 * @param WC_Product $proizvod Product.
	 * @return string|null
	 */
	public static function referenca( WC_Product $proizvod ): ?string {
		if ( ! $proizvod->is_on_sale() ) {
			return null;
		}
		$r = $proizvod->get_meta( self::META_REFERENCA, true );
		if ( '' === $r || null === $r ) {
			$naj = self::najniza_u_razdoblju( $proizvod, 30 );
			return null === $naj ? null : wc_format_decimal( $naj, wc_get_price_decimals() );
		}
		return wc_format_decimal( (float) $r, wc_get_price_decimals() );
	}

	/**
	 * Postotak sniženja od Omnibus reference.
	 *
	 * @param float $akcijska Sale price.
	 * @param float $referenca Reference.
	 * @return int|null
	 */
	public static function postotak( float $akcijska, float $referenca ): ?int {
		if ( $referenca <= 0 || $akcijska >= $referenca ) {
			return null;
		}
		return (int) round( ( ( $referenca - $akcijska ) / $referenca ) * 100 );
	}

	/**
	 * HTML Omnibus oznake (samo ako je postavka uključena).
	 *
	 * @param WC_Product $proizvod Product.
	 * @return string
	 */
	public static function html_za_proizvod( WC_Product $proizvod ): string {
		if ( ! Farmaceut_Cjenik_Postavke::omnibus_aktivan() ) {
			return '';
		}
		if ( ! $proizvod->is_on_sale() ) {
			return '';
		}
		$ref = self::referenca( $proizvod );
		if ( null === $ref ) {
			return '';
		}

		$postotak = self::postotak( (float) $proizvod->get_price(), (float) $ref );
		$dodatak  = '';
		if ( null !== $postotak ) {
			$dodatak = ' <span class="farmaceut-cjenik__omnibus-postotak">(-' . esc_html( (string) $postotak ) . '%)</span>';
		}

		return sprintf(
			'<div class="farmaceut-cjenik__omnibus" data-farmaceut-cjenik-omnibus><span class="farmaceut-cjenik__omnibus-label">%s</span> <span class="farmaceut-cjenik__omnibus-iznos">%s</span>%s</div>',
			esc_html__( 'Najniža cijena u 30 dana:', 'farmaceut-cjenik' ),
			wp_kses_post( wc_price( (float) $ref ) ),
			$dodatak
		);
	}
}

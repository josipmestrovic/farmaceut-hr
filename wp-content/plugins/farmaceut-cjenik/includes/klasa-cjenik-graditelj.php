<?php
/**
 * Graditelj CSV sadržaja cjenika.
 *
 * @package Farmaceut_Cjenik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generira retke cjenika prema NN 132/2026.
 */
class Farmaceut_Cjenik_Cjenik_Graditelj {

	/**
	 * Zaglavlja stupaca (redoslijed propisan).
	 *
	 * @return string[]
	 */
	public static function zaglavlja(): array {
		return array(
			'naziv',
			'sifra',
			'marka',
			'neto_kolicina',
			'jedinica_mjere',
			'maloprodajna_cijena',
			'posebni_oblik_prodaje',
			'cijena_za_jedinicu_mjere',
			'barkod',
			'ean',
			'bazna_cijena',
		);
	}

	/**
	 * Generira cijeli CSV string.
	 *
	 * @return array{csv:string,redovi:int,greske:string[]}|WP_Error
	 */
	public static function generiraj() {
		$redovi  = array();
		$greske  = array();
		$ids     = Farmaceut_Cjenik_Bazna_Cijena_Servis::ids_sellable();

		foreach ( $ids as $id ) {
			$proizvod = wc_get_product( $id );
			if ( ! $proizvod ) {
				continue;
			}
			$red = self::red_za_proizvod( $proizvod );
			if ( is_wp_error( $red ) ) {
				$greske[] = sprintf( '#%d: %s', $id, $red->get_error_message() );
				continue;
			}
			$redovi[] = $red;
		}

		if ( $greske ) {
			return new WP_Error(
				'cjenik_nepotpun',
				__( 'Cjenik nije objavljen zbog neispravnih/nedostajućih podataka.', 'farmaceut-cjenik' ),
				array( 'greske' => $greske )
			);
		}

		$csv = self::u_csv_string( $redovi );
		return array(
			'csv'    => $csv,
			'redovi' => count( $redovi ),
			'greske' => array(),
		);
	}

	/**
	 * @param WC_Product $proizvod Product.
	 * @return array|WP_Error
	 */
	public static function red_za_proizvod( WC_Product $proizvod ) {
		$bazna = Farmaceut_Cjenik_Bazna_Cijena_Servis::dohvati( $proizvod );
		if ( ! $bazna ) {
			return new WP_Error( 'nema_bazne', __( 'Nedostaje bazna cijena.', 'farmaceut-cjenik' ) );
		}

		$jed = Farmaceut_Cjenik_Jedinicna_Cijena::za_csv( $proizvod );
		$marka = self::marka( $proizvod );
		$ean   = self::ean( $proizvod );

		return array(
			'naziv'                     => self::naziv( $proizvod ),
			'sifra'                     => (string) $proizvod->get_sku(),
			'marka'                     => $marka,
			'neto_kolicina'             => $jed['neto'],
			'jedinica_mjere'            => $jed['jedinica'],
			'maloprodajna_cijena'       => wc_format_decimal( (float) $proizvod->get_price(), wc_get_price_decimals() ),
			'posebni_oblik_prodaje'     => $proizvod->is_on_sale() ? '1' : '0',
			'cijena_za_jedinicu_mjere'  => $jed['jedinicna'],
			'barkod'                    => $ean,
			'ean'                       => $ean,
			'bazna_cijena'              => $bazna['cijena'],
		);
	}

	/**
	 * @param WC_Product $proizvod Product.
	 * @return string
	 */
	private static function naziv( WC_Product $proizvod ): string {
		if ( $proizvod->is_type( 'variation' ) ) {
			return $proizvod->get_name();
		}
		return $proizvod->get_name();
	}

	/**
	 * @param WC_Product $proizvod Product.
	 * @return string
	 */
	private static function marka( WC_Product $proizvod ): string {
		$id = $proizvod->is_type( 'variation' ) ? $proizvod->get_parent_id() : $proizvod->get_id();
		$pojmovi = get_the_terms( $id, 'product_brand' );
		if ( is_array( $pojmovi ) && $pojmovi ) {
			return $pojmovi[0]->name;
		}
		$pojmovi = get_the_terms( $id, 'pa_brand' );
		if ( is_array( $pojmovi ) && $pojmovi ) {
			return $pojmovi[0]->name;
		}
		return '';
	}

	/**
	 * @param WC_Product $proizvod Product.
	 * @return string
	 */
	private static function ean( WC_Product $proizvod ): string {
		foreach ( array( '_global_unique_id', '_ean', 'ean', '_barcode' ) as $kljuc ) {
			$v = $proizvod->get_meta( $kljuc, true );
			if ( $v ) {
				return (string) $v;
			}
		}
		return '';
	}

	/**
	 * @param array<int,array<string,string>> $redovi Rows.
	 * @return string
	 */
	public static function u_csv_string( array $redovi ): string {
		$fh = fopen( 'php://temp', 'r+' );
		if ( false === $fh ) {
			return '';
		}
		fprintf( $fh, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		fputcsv( $fh, self::zaglavlja(), ';' );
		foreach ( $redovi as $red ) {
			$linija = array();
			foreach ( self::zaglavlja() as $stupac ) {
				$linija[] = $red[ $stupac ] ?? '';
			}
			fputcsv( $fh, $linija, ';' );
		}
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh );
		return false === $csv ? '' : $csv;
	}

	/**
	 * Hash sadržaja za usporedbu.
	 *
	 * @param string $csv CSV.
	 * @return string
	 */
	public static function hash( string $csv ): string {
		return hash( 'sha256', $csv );
	}
}

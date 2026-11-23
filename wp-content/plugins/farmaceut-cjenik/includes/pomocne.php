<?php
/**
 * Javni helperi za temu i predloške (prikaz).
 *
 * @package Farmaceut_Cjenik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Učitava frontend CSS/JS plugina.
 */
function farmaceut_cjenik_ucitaj_frontend_imovina() {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return;
	}

	$treba = ! is_admin();
	if ( ! $treba ) {
		return;
	}

	$css = FARMACEUT_CJENIK_PUTANJA . 'assets/css/cjenik-frontend.css';
	if ( file_exists( $css ) ) {
		wp_enqueue_style(
			'farmaceut-cjenik-frontend',
			FARMACEUT_CJENIK_URL . 'assets/css/cjenik-frontend.css',
			array(),
			(string) filemtime( $css )
		);
	}

	if ( function_exists( 'is_product' ) && is_product() ) {
		$js = FARMACEUT_CJENIK_PUTANJA . 'assets/js/cjenik-varijacije.js';
		if ( file_exists( $js ) ) {
			wp_enqueue_script(
				'farmaceut-cjenik-varijacije',
				FARMACEUT_CJENIK_URL . 'assets/js/cjenik-varijacije.js',
				array( 'jquery', 'wc-add-to-cart-variation' ),
				(string) filemtime( $js ),
				true
			);
		}
	}
}

/**
 * Dodaje baznu/jediničnu HTML u variation JSON.
 *
 * @param array                $podaci     Variation data.
 * @param WC_Product           $proizvod   Parent product.
 * @param WC_Product_Variation $varijacija Variation.
 * @return array
 */
function farmaceut_cjenik_dodaj_podatke_varijaciji( $podaci, $proizvod, $varijacija ) {
	unset( $proizvod );
	if ( function_exists( 'farmaceut_cjenik_html_cijena' ) ) {
		$podaci['farmaceut_cjenik_html'] = farmaceut_cjenik_html_cijena( $varijacija, 'varijacija' );
	}
	return $podaci;
}

/**
 * Dohvaća normalizirane podatke bazne cijene.
 *
 * @param WC_Product $proizvod Product.
 * @return array|null
 */
function farmaceut_cjenik_dohvati_baznu( WC_Product $proizvod ) {
	return Farmaceut_Cjenik_Bazna_Cijena_Servis::dohvati( $proizvod );
}

/**
 * HTML blok cijena (bazna + jedinična na single/varijaciji).
 * Omnibus referenca ide u WC price_html kao prekrižena cijena.
 *
 * @param WC_Product $proizvod Product.
 * @param string     $kontekst card|single|varijacija.
 * @return string
 */
function farmaceut_cjenik_html_cijena( WC_Product $proizvod, string $kontekst = 'card' ): string {
	$dijelovi = array();

	$bazna = Farmaceut_Cjenik_Bazna_Cijena_Servis::html( $proizvod, $kontekst );
	if ( $bazna ) {
		$dijelovi[] = $bazna;
	}

	// Jedinična samo na single / varijaciji (ne na grid kartici).
	if ( 'card' !== $kontekst ) {
		$jedinicna = Farmaceut_Cjenik_Jedinicna_Cijena::html( $proizvod );
		if ( $jedinicna ) {
			$dijelovi[] = $jedinicna;
		}
	}

	if ( ! $dijelovi ) {
		// Varijacija bez bazne/jedinične: prazan root (ne parent raspon).
		if ( 'varijacija' === $kontekst ) {
			return '<div class="farmaceut-cjenik farmaceut-cjenik--varijacija" data-farmaceut-cjenik-root></div>';
		}
		return '';
	}

	return '<div class="farmaceut-cjenik farmaceut-cjenik--' . esc_attr( $kontekst ) . '" data-farmaceut-cjenik-root>' . implode( '', $dijelovi ) . '</div>';
}

/**
 * Ispisuje HTML cijena uz Woo cijenu (za hookove).
 */
function farmaceut_cjenik_ispisi_nakon_cijene() {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	// Variable bez odabrane varijacije: raspon baznih ili prazno mjesto za JS.
	if ( $product->is_type( 'variable' ) ) {
		$html            = Farmaceut_Cjenik_Bazna_Cijena_Servis::html_raspon_varijabilnog( $product );
		$jed_placeholder = '<div class="farmaceut-cjenik__jedinicna" data-farmaceut-cjenik-jedinicna hidden></div>';
		echo '<div class="farmaceut-cjenik farmaceut-cjenik--single" data-farmaceut-cjenik-root>';
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $jed_placeholder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		return;
	}

	echo farmaceut_cjenik_html_cijena( $product, 'single' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'woocommerce_single_product_summary', 'farmaceut_cjenik_ispisi_nakon_cijene', 11 );

/**
 * Dodaje cjenik HTML u petlju proizvoda (kartica).
 *
 * @param string     $html     Price HTML.
 * @param WC_Product $proizvod Product.
 * @return string
 */
function farmaceut_cjenik_dodaj_uz_price_html( string $html, $proizvod ): string {
	if ( ! $proizvod instanceof WC_Product ) {
		return $html;
	}

	// Ne duplirati na single (hook 11 već ispisuje).
	if ( is_product() && ! wp_doing_ajax() ) {
		return $html;
	}

	$dodatak = farmaceut_cjenik_html_cijena( $proizvod, 'card' );
	if ( ! $dodatak ) {
		return $html;
	}

	return $html . $dodatak;
}
add_filter( 'woocommerce_get_price_html', 'farmaceut_cjenik_dodaj_uz_price_html', 20, 2 );

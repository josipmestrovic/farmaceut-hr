<?php
/**
 * Plugin Name: Farmaceut Cjenik
 * Description: Bazne cijene, jedinične cijene, javni CSV cjenik i Omnibus referentna cijena za WooCommerce.
 * Version: 1.0.0
 * Author: Farmaceut.hr
 * Text Domain: farmaceut-cjenik
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * WC requires at least: 8.0
 *
 * @package Farmaceut_Cjenik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FARMACEUT_CJENIK_VERZIJA', '1.0.0' );
define( 'FARMACEUT_CJENIK_DATOTEKA', __FILE__ );
define( 'FARMACEUT_CJENIK_PUTANJA', plugin_dir_path( __FILE__ ) );
define( 'FARMACEUT_CJENIK_URL', plugin_dir_url( __FILE__ ) );

/**
 * Učitava PHP datoteku iz includes/ ako postoji.
 *
 * @param string $datoteka Relative path under includes/.
 */
function farmaceut_cjenik_ucitaj( string $datoteka ): void {
	$putanja = FARMACEUT_CJENIK_PUTANJA . 'includes/' . ltrim( $datoteka, '/' );
	if ( file_exists( $putanja ) ) {
		require_once $putanja;
	}
}

// Sloj podataka (PR1).
farmaceut_cjenik_ucitaj( 'klasa-bazna-cijena-servis.php' );
farmaceut_cjenik_ucitaj( 'klasa-jedinicna-cijena.php' );
farmaceut_cjenik_ucitaj( 'klasa-postavke.php' );
farmaceut_cjenik_ucitaj( 'klasa-bazna-cijena-admin.php' );
farmaceut_cjenik_ucitaj( 'klasa-bazna-cijena-uvoz.php' );

// Prikaz (PR2).
farmaceut_cjenik_ucitaj( 'pomocne.php' );

// Javni cjenik (PR3).
farmaceut_cjenik_ucitaj( 'klasa-cjenik-graditelj.php' );
farmaceut_cjenik_ucitaj( 'klasa-cjenik-objava.php' );
farmaceut_cjenik_ucitaj( 'klasa-cjenik-raspored.php' );
farmaceut_cjenik_ucitaj( 'klasa-cjenik-rute.php' );

// Omnibus (PR4 / faza 2).
farmaceut_cjenik_ucitaj( 'klasa-omnibus.php' );

/**
 * Pokreće dostupne module nakon što je WooCommerce aktivan.
 */
function farmaceut_cjenik_pokreni() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'farmaceut_cjenik_obavijest_nema_woocommerce' );
		return;
	}

	if ( class_exists( 'Farmaceut_Cjenik_Postavke' ) ) {
		Farmaceut_Cjenik_Postavke::init();
	}
	if ( class_exists( 'Farmaceut_Cjenik_Bazna_Cijena_Admin' ) ) {
		Farmaceut_Cjenik_Bazna_Cijena_Admin::init();
	}
	if ( class_exists( 'Farmaceut_Cjenik_Bazna_Cijena_Uvoz' ) ) {
		Farmaceut_Cjenik_Bazna_Cijena_Uvoz::init();
	}
	if ( class_exists( 'Farmaceut_Cjenik_Omnibus' ) ) {
		Farmaceut_Cjenik_Omnibus::init();
	}
	if ( class_exists( 'Farmaceut_Cjenik_Cjenik_Raspored' ) ) {
		Farmaceut_Cjenik_Cjenik_Raspored::init();
	}
	if ( class_exists( 'Farmaceut_Cjenik_Cjenik_Rute' ) ) {
		Farmaceut_Cjenik_Cjenik_Rute::init();
	}

	if ( function_exists( 'farmaceut_cjenik_ucitaj_frontend_imovina' ) ) {
		add_action( 'wp_enqueue_scripts', 'farmaceut_cjenik_ucitaj_frontend_imovina' );
	}
	if ( function_exists( 'farmaceut_cjenik_dodaj_podatke_varijaciji' ) ) {
		add_filter( 'woocommerce_available_variation', 'farmaceut_cjenik_dodaj_podatke_varijaciji', 10, 3 );
	}
}
add_action( 'plugins_loaded', 'farmaceut_cjenik_pokreni', 20 );

/**
 * Admin obavijest kad WooCommerce nije aktivan.
 */
function farmaceut_cjenik_obavijest_nema_woocommerce() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'Farmaceut Cjenik zahtijeva aktivan WooCommerce.', 'farmaceut-cjenik' );
	echo '</p></div>';
}

register_activation_hook( __FILE__, 'farmaceut_cjenik_aktivacija' );

/**
 * Aktivacija plugina.
 */
function farmaceut_cjenik_aktivacija() {
	if ( class_exists( 'Farmaceut_Cjenik_Cjenik_Rute' ) ) {
		Farmaceut_Cjenik_Cjenik_Rute::registriraj_pravila();
		flush_rewrite_rules();
	}
	if ( class_exists( 'Farmaceut_Cjenik_Cjenik_Raspored' ) ) {
		Farmaceut_Cjenik_Cjenik_Raspored::rasporedi_poslove();
	}
}

register_deactivation_hook( __FILE__, 'farmaceut_cjenik_deaktivacija' );

/**
 * Deaktivacija plugina.
 */
function farmaceut_cjenik_deaktivacija() {
	if ( class_exists( 'Farmaceut_Cjenik_Cjenik_Raspored' ) ) {
		Farmaceut_Cjenik_Cjenik_Raspored::ukloni_poslove();
	}
	flush_rewrite_rules();
}

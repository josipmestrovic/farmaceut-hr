<?php
/**
 * Funkcije teme Farmaceut.
 *
 * @package Farmaceut
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrira osnovne mogućnosti teme.
 */
function farmaceut_postavi_temu() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);
	add_theme_support( 'custom-logo' );

	register_nav_menus(
		array(
			'glavni_izbornik' => __( 'Glavni izbornik', 'farmaceut' ),
		)
	);
}
add_action( 'after_setup_theme', 'farmaceut_postavi_temu' );

/**
 * Učitava CSS teme.
 */
function farmaceut_ucitaj_stilove() {
	$putanja = get_template_directory() . '/assets/css/glavni.css';
	$verzija = file_exists( $putanja ) ? (string) filemtime( $putanja ) : '1.0.0';

	wp_enqueue_style(
		'farmaceut-glavni',
		get_template_directory_uri() . '/assets/css/glavni.css',
		array(),
		$verzija
	);
}
add_action( 'wp_enqueue_scripts', 'farmaceut_ucitaj_stilove' );

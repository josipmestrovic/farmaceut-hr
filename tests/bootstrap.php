<?php
/**
 * Bootstrap WordPress PHPUnit okruženja za Farmaceut.hr.
 *
 * Učitava izolirani WP testni suite, temu farmaceut i eventualne
 * vlastite farmaceut-* plugine. Ne čita lokalni wp-config.php.
 *
 * WooCommerce: kad plugin zatraži WC, bootstrap treba proširiti tako da
 * učita WooCommerce prije testova; inače WC testovi moraju markTestSkipped,
 * nikad lažno proći.
 *
 * @package Farmaceut
 */

declare(strict_types=1);

$repo_root = dirname( __DIR__ );

$autoload = $repo_root . '/vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
	fwrite( STDERR, "Nedostaje vendor/autoload.php. Pokreni: composer install\n" );
	exit( 1 );
}
require_once $autoload;

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
}
if ( ! $_tests_dir ) {
	$_tests_dir = $repo_root . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! is_dir( $_tests_dir ) ) {
	fwrite( STDERR, "Nedostaje WordPress PHPUnit biblioteka u: {$_tests_dir}\n" );
	fwrite( STDERR, "Pokreni: composer install && composer test-instalacija\n" );
	exit( 1 );
}

$tests_config = getenv( 'WP_PHPUNIT__TESTS_CONFIG' );
if ( ! $tests_config ) {
	$tmp = getenv( 'TMPDIR' );
	if ( ! $tmp ) {
		$tmp = sys_get_temp_dir();
	}
	$tests_config = rtrim( $tmp, '/\\' ) . '/wordpress-tests-lib/wp-tests-config.php';
}

if ( ! file_exists( $tests_config ) ) {
	fwrite( STDERR, "Nedostaje wp-tests-config.php: {$tests_config}\n" );
	fwrite( STDERR, "Pokreni: composer test-instalacija\n" );
	exit( 1 );
}

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
$config_sadrzaj = file_get_contents( $tests_config );
if ( false === $config_sadrzaj || ! preg_match( "/define\\(\\s*'DB_NAME'\\s*,\\s*'farmaceut_test'\\s*\\)/", $config_sadrzaj ) ) {
	fwrite( STDERR, "Zaštita: testna baza u wp-tests-config.php mora biti točno 'farmaceut_test'.\n" );
	fwrite( STDERR, "Prekidam — izolacija baze nije potvrđena.\n" );
	exit( 1 );
}

putenv( 'WP_PHPUNIT__DIR=' . $_tests_dir );
putenv( 'WP_TESTS_DIR=' . $_tests_dir );
putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . $tests_config );
$_SERVER['WP_PHPUNIT__DIR']          = $_tests_dir;
$_SERVER['WP_TESTS_DIR']             = $_tests_dir;
$_SERVER['WP_PHPUNIT__TESTS_CONFIG'] = $tests_config;

if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', $tests_config );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Vraća relativne putanje farmaceut-* pluginova spremnih za aktivaciju.
 *
 * Pluginovi moraju biti dostupni u testnom WP_PLUGIN_DIR (symlink iz
 * bin/install-wp-tests.sh).
 *
 * @return string[]
 */
function farmaceut_testovi_popis_pluginova(): array {
	$pluginovi = array();
	$mapa      = dirname( __DIR__ ) . '/wp-content/plugins';

	if ( ! is_dir( $mapa ) ) {
		return $pluginovi;
	}

	foreach ( glob( $mapa . '/farmaceut-*', GLOB_ONLYDIR ) ?: array() as $dir ) {
		$slug   = basename( $dir );
		$glavna = $dir . '/' . $slug . '.php';
		if ( file_exists( $glavna ) ) {
			$pluginovi[] = $slug . '/' . $slug . '.php';
		}
	}

	return $pluginovi;
}

/**
 * Aktivira temu farmaceut i vlastite plugine prije učitavanja WordPressa.
 */
function farmaceut_testovi_ucitaj_projekt(): void {
	$pluginovi = farmaceut_testovi_popis_pluginova();

	tests_add_filter(
		'setup_theme',
		static function () {
			switch_theme( 'farmaceut' );
		}
	);

	tests_add_filter(
		'pre_option_stylesheet',
		static function () {
			return 'farmaceut';
		}
	);

	tests_add_filter(
		'pre_option_template',
		static function () {
			return 'farmaceut';
		}
	);

	if ( $pluginovi ) {
		tests_add_filter(
			'pre_option_active_plugins',
			static function () use ( $pluginovi ) {
				return $pluginovi;
			}
		);
	}
}

tests_add_filter( 'muplugins_loaded', 'farmaceut_testovi_ucitaj_projekt' );

require $_tests_dir . '/includes/bootstrap.php';
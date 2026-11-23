<?php
/**
 * Javne rute /cjenici.
 *
 * @package Farmaceut_Cjenik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrite + serving CSV.
 */
class Farmaceut_Cjenik_Cjenik_Rute {

	/**
	 * Init.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'registriraj_pravila' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'opsuzi' ) );
	}

	/**
	 * Rewrite rules.
	 */
	public static function registriraj_pravila(): void {
		add_rewrite_rule( '^cjenici/?$', 'index.php?farmaceut_cjenik=index', 'top' );
		add_rewrite_rule( '^cjenici/roba\.csv$', 'index.php?farmaceut_cjenik=roba', 'top' );
		add_rewrite_rule( '^cjenici/arhiva/([^/]+\.csv)$', 'index.php?farmaceut_cjenik=arhiva&farmaceut_cjenik_datoteka=$matches[1]', 'top' );
	}

	/**
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = 'farmaceut_cjenik';
		$vars[] = 'farmaceut_cjenik_datoteka';
		return $vars;
	}

	/**
	 * Rukovanje zahtjevom.
	 */
	public static function opsuzi(): void {
		$akcija = get_query_var( 'farmaceut_cjenik' );
		if ( ! $akcija ) {
			return;
		}

		if ( 'roba' === $akcija ) {
			self::posalji_trenutni();
		}
		if ( 'arhiva' === $akcija ) {
			self::posalji_arhivu( (string) get_query_var( 'farmaceut_cjenik_datoteka' ) );
		}
		if ( 'index' === $akcija ) {
			self::stranica_index();
		}
	}

	/**
	 * Trenutni CSV.
	 */
	private static function posalji_trenutni(): void {
		$putanja = Farmaceut_Cjenik_Cjenik_Objava::putanja_trenutna();
		$meta    = Farmaceut_Cjenik_Cjenik_Objava::meta_status();

		// Posljednji valjani CSV ostaje javan i kad je regeneracija stale/neuspjela.
		if ( ! $putanja || ! file_exists( $putanja ) ) {
			status_header( 503 );
			nocache_headers();
			header( 'Content-Type: text/plain; charset=UTF-8' );
			echo esc_html__( 'Cjenik trenutno nije dostupan.', 'farmaceut-cjenik' );
			exit;
		}

		$naziv = ! empty( $meta['naziv'] ) ? $meta['naziv'] : ( Farmaceut_Cjenik_Postavke::naziv_datoteke() . '.csv' );
		self::posalji_datoteku( $putanja, $naziv );
	}

	/**
	 * @param string $naziv Filename.
	 */
	private static function posalji_arhivu( string $naziv ): void {
		$naziv = basename( $naziv );
		if ( ! preg_match( '/^[A-Za-z0-9_\-]+\.csv$/', $naziv ) ) {
			status_header( 404 );
			exit;
		}
		$dir = Farmaceut_Cjenik_Cjenik_Objava::direktorij();
		if ( is_wp_error( $dir ) ) {
			status_header( 503 );
			exit;
		}
		$putanja = trailingslashit( $dir ) . $naziv;
		if ( ! file_exists( $putanja ) || 'roba.csv' === $naziv ) {
			status_header( 404 );
			exit;
		}
		self::posalji_datoteku( $putanja, $naziv );
	}

	/**
	 * @param string $putanja Path.
	 * @param string $naziv   Download name.
	 */
	private static function posalji_datoteku( string $putanja, string $naziv ): void {
		status_header( 200 );
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $naziv . '"' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $putanja );
		exit;
	}

	/**
	 * HTML popis.
	 */
	private static function stranica_index(): void {
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		$meta   = Farmaceut_Cjenik_Cjenik_Objava::meta_status();
		$arhiva = Farmaceut_Cjenik_Cjenik_Objava::popis_arhive();
		$trenutni_url = home_url( '/cjenici/roba.csv' );
		?><!DOCTYPE html>
<html lang="hr">
<head>
	<meta charset="utf-8" />
	<meta name="robots" content="noindex,follow" />
	<title><?php echo esc_html__( 'Cjenici', 'farmaceut-cjenik' ); ?></title>
	<style>
		body{font-family:system-ui,sans-serif;max-width:40rem;margin:2rem auto;padding:0 1rem;line-height:1.5;color:#222}
		a{color:#0b5} ul{padding-left:1.2rem}
	</style>
</head>
<body>
	<h1><?php echo esc_html__( 'Cjenici', 'farmaceut-cjenik' ); ?></h1>
	<p><?php echo esc_html__( 'Važeći cjenik robe (CSV, strojno čitljiv):', 'farmaceut-cjenik' ); ?></p>
	<p><a href="<?php echo esc_url( $trenutni_url ); ?>"><?php echo esc_html( $trenutni_url ); ?></a></p>
	<?php if ( ! empty( $meta['vrijeme'] ) ) : ?>
		<p><?php echo esc_html( sprintf( __( 'Zadnja objava: %s', 'farmaceut-cjenik' ), $meta['vrijeme'] ) ); ?></p>
	<?php endif; ?>
	<h2><?php echo esc_html__( 'Arhiva', 'farmaceut-cjenik' ); ?></h2>
	<?php if ( ! $arhiva ) : ?>
		<p><?php echo esc_html__( 'Nema arhiviranih verzija.', 'farmaceut-cjenik' ); ?></p>
	<?php else : ?>
		<ul>
			<?php foreach ( $arhiva as $stavka ) : ?>
				<li><a href="<?php echo esc_url( $stavka['url'] ); ?>"><?php echo esc_html( $stavka['naziv'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</body>
</html>
		<?php
		exit;
	}
}

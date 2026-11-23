<?php
/**
 * Objava i arhiva CSV cjenika.
 *
 * @package Farmaceut_Cjenik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Atomic write, arhiva, status.
 */
class Farmaceut_Cjenik_Cjenik_Objava {

	public const OPTION_STATUS = 'farmaceut_cjenik_status';
	public const OPTION_DIRTY  = 'farmaceut_cjenik_dirty';

	/**
	 * Direktorij za datoteke.
	 *
	 * @return string|WP_Error
	 */
	public static function direktorij() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'upload', $upload['error'] );
		}
		$dir = trailingslashit( $upload['basedir'] ) . 'farmaceut-cjenici';
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'mkdir', __( 'Ne mogu stvoriti direktorij cjenika.', 'farmaceut-cjenik' ) );
		}
		$ht = $dir . '/.htaccess';
		if ( ! file_exists( $ht ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $ht, "Options -Indexes\n<FilesMatch \"\\.php$\">\nDeny from all\n</FilesMatch>\n" );
		}
		return $dir;
	}

	/**
	 * Označi cjenik za regeneraciju.
	 */
	public static function oznaci_dirty(): void {
		update_option( self::OPTION_DIRTY, time(), false );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function meta_status(): array {
		$s = get_option( self::OPTION_STATUS, array() );
		if ( ! is_array( $s ) ) {
			$s = array();
		}
		return array_merge(
			array(
				'vrijeme'   => '',
				'redovi'    => '',
				'hash'      => '',
				'status'    => '',
				'greska'    => '',
				'datoteka'  => '',
				'naziv'     => '',
			),
			$s
		);
	}

	/**
	 * Putanja trenutne datoteke.
	 *
	 * @return string
	 */
	public static function putanja_trenutna(): string {
		$dir = self::direktorij();
		if ( is_wp_error( $dir ) ) {
			return '';
		}
		return trailingslashit( $dir ) . 'roba.csv';
	}

	/**
	 * Generira i objavljuje ako se sadržaj promijenio.
	 *
	 * @param bool $prisili Force even if hash unchanged / ignore dirty.
	 * @return true|WP_Error
	 */
	public static function generiraj_i_objavi( bool $prisili = false ) {
		$lock = 'farmaceut_cjenik_lock';
		if ( get_transient( $lock ) ) {
			return new WP_Error( 'zakljucano', __( 'Generacija cjenika već traje.', 'farmaceut-cjenik' ) );
		}
		set_transient( $lock, 1, 5 * MINUTE_IN_SECONDS );

		try {
			$rezultat = Farmaceut_Cjenik_Cjenik_Graditelj::generiraj();
			if ( is_wp_error( $rezultat ) ) {
				$data = $rezultat->get_error_data();
				$greske = is_array( $data ) && isset( $data['greske'] ) ? implode( '; ', $data['greske'] ) : $rezultat->get_error_message();
				self::spremi_status(
					array(
						'status' => 'greska',
						'greska' => $greske,
					)
				);
				return $rezultat;
			}

			$hash = Farmaceut_Cjenik_Cjenik_Graditelj::hash( $rezultat['csv'] );
			$meta = self::meta_status();
			if ( ! $prisili && ! empty( $meta['hash'] ) && $meta['hash'] === $hash && 'ok' === $meta['status'] ) {
				delete_option( self::OPTION_DIRTY );
				return true;
			}

			$dir = self::direktorij();
			if ( is_wp_error( $dir ) ) {
				return $dir;
			}

			$naziv    = Farmaceut_Cjenik_Postavke::naziv_datoteke() . '.csv';
			$tmp      = trailingslashit( $dir ) . 'roba.tmp.csv';
			$trenutna = self::putanja_trenutna();
			$arhiva   = trailingslashit( $dir ) . $naziv;

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( false === file_put_contents( $tmp, $rezultat['csv'] ) ) {
				return new WP_Error( 'zapis', __( 'Neuspješan zapis privremene datoteke.', 'farmaceut-cjenik' ) );
			}

			if ( file_exists( $trenutna ) ) {
				$stari_naziv = ! empty( $meta['naziv'] ) ? $meta['naziv'] : ( 'arhiva_' . gmdate( 'Ymd_His' ) . '.csv' );
				$stara_arh   = trailingslashit( $dir ) . $stari_naziv;
				if ( ! file_exists( $stara_arh ) ) {
					copy( $trenutna, $stara_arh );
				}
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			if ( ! rename( $tmp, $trenutna ) ) {
				return new WP_Error( 'rename', __( 'Neuspješna atomic zamjena cjenika.', 'farmaceut-cjenik' ) );
			}
			copy( $trenutna, $arhiva );

			Farmaceut_Cjenik_Postavke::povecaj_broj_pohrane();
			delete_option( self::OPTION_DIRTY );
			self::ocisti_arhivu( $dir );

			self::spremi_status(
				array(
					'vrijeme'  => gmdate( 'c' ),
					'redovi'   => $rezultat['redovi'],
					'hash'     => $hash,
					'status'   => 'ok',
					'greska'   => '',
					'datoteka' => $trenutna,
					'naziv'    => $naziv,
				)
			);

			return true;
		} finally {
			delete_transient( $lock );
		}
	}

	/**
	 * @param array $podaci Status patch.
	 */
	private static function spremi_status( array $podaci ): void {
		$meta = self::meta_status();
		update_option( self::OPTION_STATUS, array_merge( $meta, $podaci ), false );
	}

	/**
	 * Briše arhivu stariju od 30 dana (propis: min 15).
	 *
	 * @param string $dir Directory.
	 */
	private static function ocisti_arhivu( string $dir ): void {
		$granica = time() - ( 30 * DAY_IN_SECONDS );
		foreach ( glob( trailingslashit( $dir ) . '*.csv' ) ?: array() as $f ) {
			if ( basename( $f ) === 'roba.csv' ) {
				continue;
			}
			if ( filemtime( $f ) < $granica ) {
				wp_delete_file( $f );
			}
		}
	}

	/**
	 * Popis arhivskih datoteka.
	 *
	 * @return array<int,array{naziv:string,mtime:int,url:string}>
	 */
	public static function popis_arhive(): array {
		$dir = self::direktorij();
		if ( is_wp_error( $dir ) ) {
			return array();
		}
		$lista = array();
		foreach ( glob( trailingslashit( $dir ) . '*.csv' ) ?: array() as $f ) {
			$naziv = basename( $f );
			if ( 'roba.csv' === $naziv ) {
				continue;
			}
			$lista[] = array(
				'naziv' => $naziv,
				'mtime' => (int) filemtime( $f ),
				'url'   => home_url( '/cjenici/arhiva/' . rawurlencode( $naziv ) ),
			);
		}
		usort(
			$lista,
			static function ( $a, $b ) {
				return $b['mtime'] <=> $a['mtime'];
			}
		);
		return $lista;
	}
}

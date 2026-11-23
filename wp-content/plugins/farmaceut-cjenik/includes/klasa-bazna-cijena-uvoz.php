<?php
/**
 * CSV uvoz baznih cijena.
 *
 * @package Farmaceut_Cjenik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preview i commit uvoza.
 */
class Farmaceut_Cjenik_Bazna_Cijena_Uvoz {

	/**
	 * Init.
	 */
	public static function init(): void {
		add_action( 'admin_post_farmaceut_cjenik_uvoz', array( __CLASS__, 'obrada' ) );
	}

	/**
	 * Forma na admin stranici.
	 */
	public static function render_forma(): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="farmaceut_cjenik_uvoz" />
			<?php wp_nonce_field( 'farmaceut_cjenik_uvoz' ); ?>
			<p>
				<input type="file" name="farmaceut_cjenik_csv" accept=".csv,text/csv" required />
			</p>
			<p>
				<label><input type="checkbox" name="dry_run" value="1" checked /> <?php esc_html_e( 'Samo pregled (dry-run)', 'farmaceut-cjenik' ); ?></label>
			</p>
			<?php submit_button( __( 'Uvezi / pregledaj', 'farmaceut-cjenik' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Obrada uploada.
	 */
	public static function obrada(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Nedovoljne ovlasti.', 'farmaceut-cjenik' ) );
		}
		check_admin_referer( 'farmaceut_cjenik_uvoz' );

		$dry = ! empty( $_POST['dry_run'] );
		if ( empty( $_FILES['farmaceut_cjenik_csv']['tmp_name'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=farmaceut-cjenik&uvoz=greska' ) );
			exit;
		}

		$putanja = $_FILES['farmaceut_cjenik_csv']['tmp_name']; // phpcs:ignore
		$rezultat = self::obradi_datoteku( $putanja, $dry );

		set_transient(
			'farmaceut_cjenik_uvoz_rezultat_' . get_current_user_id(),
			$rezultat,
			10 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect( admin_url( 'admin.php?page=farmaceut-cjenik&uvoz=1' ) );
		exit;
	}

	/**
	 * @param string $putanja File path.
	 * @param bool   $dry_run Dry run.
	 * @return array{ok:int,greske:array<int,string>,poruke:array<int,string>}
	 */
	public static function obradi_datoteku( string $putanja, bool $dry_run = true ): array {
		$ok     = 0;
		$greske = array();
		$poruke = array();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$sadrzaj = file_get_contents( $putanja );
		if ( false === $sadrzaj ) {
			return array(
				'ok'     => 0,
				'greske' => array( __( 'Datoteka se ne može pročitati.', 'farmaceut-cjenik' ) ),
				'poruke' => array(),
			);
		}

		$sadrzaj = self::ukloni_bom( $sadrzaj );
		$linije  = preg_split( '/\r\n|\r|\n/', $sadrzaj );
		if ( ! $linije ) {
			return array(
				'ok'     => 0,
				'greske' => array( __( 'Prazna datoteka.', 'farmaceut-cjenik' ) ),
				'poruke' => array(),
			);
		}

		$zaglavlje = str_getcsv( array_shift( $linije ), ';' );
		$mapa      = self::mapa_stupaca( $zaglavlje );
		if ( is_wp_error( $mapa ) ) {
			return array(
				'ok'     => 0,
				'greske' => array( $mapa->get_error_message() ),
				'poruke' => array(),
			);
		}

		$videni_sku = array();
		$broj       = 1;
		foreach ( $linije as $linija ) {
			++$broj;
			if ( '' === trim( $linija ) ) {
				continue;
			}
			$stupci = str_getcsv( $linija, ';' );
			$red    = self::red_iz_stupaca( $stupci, $mapa );
			$rez    = self::obradi_red( $red, $dry_run, $videni_sku );
			if ( is_wp_error( $rez ) ) {
				$greske[] = sprintf( 'Red %d: %s', $broj, $rez->get_error_message() );
				continue;
			}
			++$ok;
			$poruke[] = $rez;
		}

		return array(
			'ok'     => $ok,
			'greske' => $greske,
			'poruke' => $poruke,
		);
	}

	/**
	 * @param string $sadrzaj CSV content.
	 * @return string
	 */
	public static function ukloni_bom( string $sadrzaj ): string {
		if ( str_starts_with( $sadrzaj, "\xEF\xBB\xBF" ) ) {
			return substr( $sadrzaj, 3 );
		}
		return $sadrzaj;
	}

	/**
	 * @param array $zaglavlje Headers.
	 * @return array|WP_Error
	 */
	public static function mapa_stupaca( array $zaglavlje ) {
		$norm = array();
		foreach ( $zaglavlje as $i => $h ) {
			$norm[ strtolower( trim( (string) $h ) ) ] = $i;
		}
		if ( ! isset( $norm['product_id'] ) && ! isset( $norm['sku'] ) ) {
			return new WP_Error( 'zaglavlje', __( 'CSV mora imati product_id ili sku.', 'farmaceut-cjenik' ) );
		}
		if ( ! isset( $norm['bazna_cijena'] ) ) {
			return new WP_Error( 'zaglavlje', __( 'CSV mora imati bazna_cijena.', 'farmaceut-cjenik' ) );
		}
		return $norm;
	}

	/**
	 * @param array $stupci Cells.
	 * @param array $mapa   Header map.
	 * @return array<string,string>
	 */
	private static function red_iz_stupaca( array $stupci, array $mapa ): array {
		$get = static function ( string $kljuc ) use ( $stupci, $mapa ) {
			if ( ! isset( $mapa[ $kljuc ] ) ) {
				return '';
			}
			$i = $mapa[ $kljuc ];
			return isset( $stupci[ $i ] ) ? trim( (string) $stupci[ $i ] ) : '';
		};
		return array(
			'product_id'    => $get( 'product_id' ),
			'sku'           => $get( 'sku' ),
			'bazna_cijena'  => $get( 'bazna_cijena' ),
			'bazna_datum'   => $get( 'bazna_datum' ),
			'bazna_osnova'  => $get( 'bazna_osnova' ),
			'izvor'         => $get( 'izvor' ),
			'potvrdeno'     => $get( 'potvrdeno' ),
			'neto_kolicina' => $get( 'neto_kolicina' ),
			'neto_jedinica' => $get( 'neto_jedinica' ),
		);
	}

	/**
	 * @param array         $red         Row.
	 * @param bool          $dry_run     Dry run.
	 * @param array<string,int> $videni_sku Seen SKUs.
	 * @return string|WP_Error
	 */
	public static function obradi_red( array $red, bool $dry_run, array &$videni_sku ) {
		$proizvod = null;
		if ( $red['product_id'] !== '' ) {
			$proizvod = wc_get_product( absint( $red['product_id'] ) );
		} elseif ( $red['sku'] !== '' ) {
			$sku = $red['sku'];
			if ( isset( $videni_sku[ $sku ] ) ) {
				return new WP_Error( 'duplikat_sku', sprintf( __( 'Duplicirani SKU u datoteci: %s', 'farmaceut-cjenik' ), $sku ) );
			}
			$videni_sku[ $sku ] = 1;
			$id = wc_get_product_id_by_sku( $sku );
			if ( ! $id ) {
				return new WP_Error( 'nepoznat_sku', sprintf( __( 'Nepoznat SKU: %s', 'farmaceut-cjenik' ), $sku ) );
			}
			$proizvod = wc_get_product( $id );
		}

		if ( ! $proizvod ) {
			return new WP_Error( 'nepoznat', __( 'Proizvod nije pronađen.', 'farmaceut-cjenik' ) );
		}
		if ( $proizvod->is_type( 'variable' ) ) {
			return new WP_Error( 'varijabilni', __( 'Uvoz na parent variable nije dopušten — koristi ID/SKU varijacije.', 'farmaceut-cjenik' ) );
		}

		$osnova = $red['bazna_osnova'] !== '' ? $red['bazna_osnova'] : Farmaceut_Cjenik_Bazna_Cijena_Servis::OSNOVA_2025;
		$datum  = $red['bazna_datum'];
		if ( '' === $datum ) {
			$predlozeni = Farmaceut_Cjenik_Bazna_Cijena_Servis::predlozeni_datum_za_osnovu( $osnova );
			$datum      = $predlozeni ?: '';
		}

		$poruka = sprintf(
			'#%d %s → %s (%s)',
			$proizvod->get_id(),
			$proizvod->get_name(),
			$red['bazna_cijena'],
			$datum
		);

		if ( $dry_run ) {
			$cijena = Farmaceut_Cjenik_Bazna_Cijena_Servis::normaliziraj_cijenu( $red['bazna_cijena'] );
			$d      = Farmaceut_Cjenik_Bazna_Cijena_Servis::normaliziraj_datum( $datum );
			$o      = Farmaceut_Cjenik_Bazna_Cijena_Servis::normaliziraj_osnovu( $osnova );
			if ( null === $cijena || null === $d || null === $o ) {
				return new WP_Error( 'validacija', __( 'Neispravni podaci u retku.', 'farmaceut-cjenik' ) );
			}
			return '[dry-run] ' . $poruka;
		}

		$rez = Farmaceut_Cjenik_Bazna_Cijena_Servis::spremi(
			$proizvod,
			array(
				'cijena'    => $red['bazna_cijena'],
				'datum'     => $datum,
				'osnova'    => $osnova,
				'izvor'     => $red['izvor'],
				'potvrdeno' => in_array( strtolower( $red['potvrdeno'] ), array( '1', 'yes', 'da', 'true' ), true ),
			),
			__( 'CSV uvoz', 'farmaceut-cjenik' )
		);
		if ( is_wp_error( $rez ) ) {
			return $rez;
		}

		if ( $red['neto_kolicina'] !== '' ) {
			$proizvod = wc_get_product( $proizvod->get_id() );
			if ( $proizvod ) {
				Farmaceut_Cjenik_Jedinicna_Cijena::spremi(
					$proizvod,
					$red['neto_kolicina'],
					$red['neto_jedinica'] !== '' ? $red['neto_jedinica'] : 'g'
				);
			}
		}

		return $poruka;
	}
}

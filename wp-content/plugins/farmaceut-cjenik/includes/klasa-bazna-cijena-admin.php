<?php
/**
 * Admin UI: polja proizvoda, completeness, postavke.
 *
 * @package Farmaceut_Cjenik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce admin integracija.
 */
class Farmaceut_Cjenik_Bazna_Cijena_Admin {

	/**
	 * Init.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'izbornik' ), 60 );
		add_action( 'woocommerce_product_options_pricing', array( __CLASS__, 'polja_simple' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'spremi_simple' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'polja_varijacija' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'spremi_varijacija' ), 10, 2 );
		add_action( 'admin_post_farmaceut_cjenik_regeneriraj', array( __CLASS__, 'obrada_regeneriraj' ) );
	}

	/**
	 * WooCommerce podizbornik.
	 */
	public static function izbornik(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Cjenik', 'farmaceut-cjenik' ),
			__( 'Cjenik', 'farmaceut-cjenik' ),
			'manage_woocommerce',
			'farmaceut-cjenik',
			array( __CLASS__, 'stranica' )
		);
	}

	/**
	 * Polja na simple proizvodu.
	 */
	public static function polja_simple(): void {
		global $product_object;
		$proizvod = $product_object instanceof WC_Product ? $product_object : null;
		echo '<div class="options_group">';
		self::render_polja( $proizvod, '' );
		echo '</div>';
	}

	/**
	 * @param int                  $loop Loop index.
	 * @param array                $attrs Attributes.
	 * @param WC_Product_Variation $varijacija Variation.
	 */
	public static function polja_varijacija( $loop, $attrs, $varijacija ): void {
		unset( $attrs );
		$p = $varijacija instanceof WC_Product ? $varijacija : wc_get_product( $varijacija->ID );
		echo '<div class="form-row form-row-full farmaceut-cjenik-varijacija">';
		self::render_polja( $p, '_' . (int) $loop );
		echo '</div>';
	}

	/**
	 * @param WC_Product|null $proizvod Product.
	 * @param string          $sufiks   Field name suffix.
	 */
	private static function render_polja( ?WC_Product $proizvod, string $sufiks ): void {
		$cijena    = $proizvod ? $proizvod->get_meta( Farmaceut_Cjenik_Bazna_Cijena_Servis::META_CIJENA, true ) : '';
		$datum     = $proizvod ? $proizvod->get_meta( Farmaceut_Cjenik_Bazna_Cijena_Servis::META_DATUM, true ) : Farmaceut_Cjenik_Bazna_Cijena_Servis::DATUM_2025;
		$osnova    = $proizvod ? $proizvod->get_meta( Farmaceut_Cjenik_Bazna_Cijena_Servis::META_OSNOVA, true ) : Farmaceut_Cjenik_Bazna_Cijena_Servis::OSNOVA_2025;
		$potvrdeno = $proizvod && 'yes' === $proizvod->get_meta( Farmaceut_Cjenik_Bazna_Cijena_Servis::META_POTVRDENO, true );
		$izvor     = $proizvod ? $proizvod->get_meta( Farmaceut_Cjenik_Bazna_Cijena_Servis::META_IZVOR, true ) : '';
		$neto_k    = $proizvod ? $proizvod->get_meta( Farmaceut_Cjenik_Jedinicna_Cijena::META_KOLICINA, true ) : '';
		$neto_j    = $proizvod ? $proizvod->get_meta( Farmaceut_Cjenik_Jedinicna_Cijena::META_JEDINICA, true ) : 'g';

		$prefiks = '' === $sufiks ? '' : 'variable_';
		$name    = static function ( string $polje ) use ( $prefiks, $sufiks ) {
			if ( '' === $sufiks ) {
				return '_farmaceut_' . $polje;
			}
			return $prefiks . 'farmaceut_' . $polje . '[' . ltrim( $sufiks, '_' ) . ']';
		};

		woocommerce_wp_text_input(
			array(
				'id'                => $name( 'bazna_cijena' ),
				'name'              => $name( 'bazna_cijena' ),
				'label'             => __( 'Bazna cijena (€)', 'farmaceut-cjenik' ),
				'value'             => $cijena,
				'data_type'         => 'price',
				'wrapper_class'     => '' === $sufiks ? '' : 'form-row form-row-first',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => $name( 'bazna_datum' ),
				'name'          => $name( 'bazna_datum' ),
				'label'         => __( 'Datum bazne cijene', 'farmaceut-cjenik' ),
				'value'         => $datum,
				'placeholder'   => 'YYYY-MM-DD',
				'wrapper_class' => '' === $sufiks ? '' : 'form-row form-row-last',
			)
		);

		woocommerce_wp_select(
			array(
				'id'            => $name( 'bazna_osnova' ),
				'name'          => $name( 'bazna_osnova' ),
				'label'         => __( 'Osnova bazne cijene', 'farmaceut-cjenik' ),
				'value'         => $osnova ?: Farmaceut_Cjenik_Bazna_Cijena_Servis::OSNOVA_2025,
				'options'       => Farmaceut_Cjenik_Bazna_Cijena_Servis::labele_osnova(),
				'wrapper_class' => '' === $sufiks ? '' : 'form-row form-row-full',
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'            => $name( 'bazna_izvor' ),
				'name'          => $name( 'bazna_izvor' ),
				'label'         => __( 'Izvor / napomena', 'farmaceut-cjenik' ),
				'value'         => $izvor,
				'wrapper_class' => '' === $sufiks ? '' : 'form-row form-row-first',
			)
		);

		woocommerce_wp_checkbox(
			array(
				'id'            => $name( 'bazna_potvrdeno' ),
				'name'          => $name( 'bazna_potvrdeno' ),
				'label'         => __( 'Potvrđeno', 'farmaceut-cjenik' ),
				'value'         => $potvrdeno ? 'yes' : 'no',
				'cbvalue'       => 'yes',
				'description'   => __( 'Merchant je potvrdio povijesni iznos.', 'farmaceut-cjenik' ),
				'wrapper_class' => '' === $sufiks ? '' : 'form-row form-row-last',
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'            => $name( 'neto_kolicina' ),
				'name'          => $name( 'neto_kolicina' ),
				'label'         => __( 'Neto količina', 'farmaceut-cjenik' ),
				'value'         => $neto_k,
				'wrapper_class' => '' === $sufiks ? '' : 'form-row form-row-first',
			)
		);

		$jed_opcije = array();
		foreach ( Farmaceut_Cjenik_Jedinicna_Cijena::dopustene_jedinice() as $j ) {
			$jed_opcije[ $j ] = $j;
		}
		woocommerce_wp_select(
			array(
				'id'            => $name( 'neto_jedinica' ),
				'name'          => $name( 'neto_jedinica' ),
				'label'         => __( 'Jedinica mjere', 'farmaceut-cjenik' ),
				'value'         => $neto_j ?: 'g',
				'options'       => $jed_opcije,
				'wrapper_class' => '' === $sufiks ? '' : 'form-row form-row-last',
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'            => '' === $sufiks ? '_farmaceut_bazna_razlog' : $name( 'bazna_razlog' ),
				'name'          => '' === $sufiks ? '_farmaceut_bazna_razlog' : $name( 'bazna_razlog' ),
				'label'         => __( 'Razlog ispravka', 'farmaceut-cjenik' ),
				'description'   => __( 'Obavezno pri izmjeni potvrđene bazne cijene.', 'farmaceut-cjenik' ),
				'desc_tip'      => true,
				'wrapper_class' => '' === $sufiks ? '' : 'form-row form-row-full',
			)
		);
	}

	/**
	 * @param int $post_id Product ID.
	 */
	public static function spremi_simple( int $post_id ): void {
		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}
		$proizvod = wc_get_product( $post_id );
		if ( ! $proizvod || $proizvod->is_type( 'variable' ) ) {
			return;
		}

		check_admin_referer( 'update-post_' . $post_id );

		$cijena = isset( $_POST['_farmaceut_bazna_cijena'] ) ? wp_unslash( $_POST['_farmaceut_bazna_cijena'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' !== $cijena ) {
			$rez = Farmaceut_Cjenik_Bazna_Cijena_Servis::spremi(
				$proizvod,
				array(
					'cijena'    => $cijena,
					'datum'     => isset( $_POST['_farmaceut_bazna_datum'] ) ? sanitize_text_field( wp_unslash( $_POST['_farmaceut_bazna_datum'] ) ) : '',
					'osnova'    => isset( $_POST['_farmaceut_bazna_osnova'] ) ? sanitize_text_field( wp_unslash( $_POST['_farmaceut_bazna_osnova'] ) ) : '',
					'izvor'     => isset( $_POST['_farmaceut_bazna_izvor'] ) ? sanitize_text_field( wp_unslash( $_POST['_farmaceut_bazna_izvor'] ) ) : '',
					'potvrdeno' => isset( $_POST['_farmaceut_bazna_potvrdeno'] ) ? 'yes' : 'no',
				),
				isset( $_POST['_farmaceut_bazna_razlog'] ) ? sanitize_text_field( wp_unslash( $_POST['_farmaceut_bazna_razlog'] ) ) : ''
			);
			if ( is_wp_error( $rez ) && class_exists( 'WC_Admin_Meta_Boxes' ) ) {
				WC_Admin_Meta_Boxes::add_error( $rez->get_error_message() );
			}
		}

		$proizvod = wc_get_product( $post_id );
		if ( $proizvod ) {
			Farmaceut_Cjenik_Jedinicna_Cijena::spremi(
				$proizvod,
				isset( $_POST['_farmaceut_neto_kolicina'] ) ? wp_unslash( $_POST['_farmaceut_neto_kolicina'] ) : '', // phpcs:ignore
				isset( $_POST['_farmaceut_neto_jedinica'] ) ? sanitize_text_field( wp_unslash( $_POST['_farmaceut_neto_jedinica'] ) ) : 'g'
			);
		}
	}

	/**
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Loop.
	 */
	public static function spremi_varijacija( int $variation_id, int $loop ): void {
		if ( ! current_user_can( 'edit_product', $variation_id ) ) {
			return;
		}
		$proizvod = wc_get_product( $variation_id );
		if ( ! $proizvod ) {
			return;
		}

		$cijene = isset( $_POST['variable_farmaceut_bazna_cijena'][ $loop ] ) ? wp_unslash( $_POST['variable_farmaceut_bazna_cijena'][ $loop ] ) : ''; // phpcs:ignore
		if ( '' !== $cijene ) {
			$razlog = isset( $_POST['variable_farmaceut_bazna_razlog'][ $loop ] )
				? sanitize_text_field( wp_unslash( $_POST['variable_farmaceut_bazna_razlog'][ $loop ] ) )
				: '';

			$rez = Farmaceut_Cjenik_Bazna_Cijena_Servis::spremi(
				$proizvod,
				array(
					'cijena'    => $cijene,
					'datum'     => isset( $_POST['variable_farmaceut_bazna_datum'][ $loop ] ) ? sanitize_text_field( wp_unslash( $_POST['variable_farmaceut_bazna_datum'][ $loop ] ) ) : '',
					'osnova'    => isset( $_POST['variable_farmaceut_bazna_osnova'][ $loop ] ) ? sanitize_text_field( wp_unslash( $_POST['variable_farmaceut_bazna_osnova'][ $loop ] ) ) : '',
					'izvor'     => isset( $_POST['variable_farmaceut_bazna_izvor'][ $loop ] ) ? sanitize_text_field( wp_unslash( $_POST['variable_farmaceut_bazna_izvor'][ $loop ] ) ) : '',
					'potvrdeno' => isset( $_POST['variable_farmaceut_bazna_potvrdeno'][ $loop ] ) ? 'yes' : 'no',
				),
				$razlog
			);
			if ( is_wp_error( $rez ) && class_exists( 'WC_Admin_Meta_Boxes' ) ) {
				WC_Admin_Meta_Boxes::add_error(
					sprintf(
						/* translators: 1: variation id 2: error message */
						__( 'Varijacija #%1$d: %2$s', 'farmaceut-cjenik' ),
						$variation_id,
						$rez->get_error_message()
					)
				);
			}
		}

		$proizvod = wc_get_product( $variation_id );
		if ( $proizvod ) {
			Farmaceut_Cjenik_Jedinicna_Cijena::spremi(
				$proizvod,
				isset( $_POST['variable_farmaceut_neto_kolicina'][ $loop ] ) ? wp_unslash( $_POST['variable_farmaceut_neto_kolicina'][ $loop ] ) : '', // phpcs:ignore
				isset( $_POST['variable_farmaceut_neto_jedinica'][ $loop ] ) ? sanitize_text_field( wp_unslash( $_POST['variable_farmaceut_neto_jedinica'][ $loop ] ) ) : 'g'
			);
		}
	}

	/**
	 * Admin stranica.
	 */
	public static function stranica(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( isset( $_POST['farmaceut_cjenik_spremi_postavke'] ) ) {
			check_admin_referer( 'farmaceut_cjenik_postavke' );
			$san = Farmaceut_Cjenik_Postavke::sanitiziraj( wp_unslash( $_POST['farmaceut_cjenik_postavke'] ?? array() ) ); // phpcs:ignore
			update_option( Farmaceut_Cjenik_Postavke::OPTION, $san, false );
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Postavke spremljene.', 'farmaceut-cjenik' ) . '</p></div>';
		}

		$stat = Farmaceut_Cjenik_Bazna_Cijena_Servis::statistika();
		$meta = class_exists( 'Farmaceut_Cjenik_Cjenik_Objava' )
			? Farmaceut_Cjenik_Cjenik_Objava::meta_status()
			: array(
				'vrijeme' => '',
				'redovi'  => '',
				'status'  => '',
				'greska'  => '',
			);
		$s    = Farmaceut_Cjenik_Postavke::dohvati();
		$url  = home_url( '/cjenici/' );

		$uvoz_kljuc = 'farmaceut_cjenik_uvoz_rezultat_' . get_current_user_id();
		$uvoz       = get_transient( $uvoz_kljuc );
		if ( $uvoz ) {
			delete_transient( $uvoz_kljuc );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Farmaceut cjenik', 'farmaceut-cjenik' ); ?></h1>
			<?php if ( is_array( $uvoz ) ) : ?>
				<div class="notice notice-info">
					<p><?php echo esc_html( sprintf( __( 'Uvoz: uspješno %d redaka.', 'farmaceut-cjenik' ), (int) ( $uvoz['ok'] ?? 0 ) ) ); ?></p>
					<?php if ( ! empty( $uvoz['greske'] ) ) : ?>
						<ul>
							<?php foreach ( array_slice( $uvoz['greske'], 0, 50 ) as $g ) : ?>
								<li><?php echo esc_html( $g ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( isset( $_GET['regenerirano'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Zahtjev za regeneraciju cjenika poslan.', 'farmaceut-cjenik' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Completeness baznih cijena', 'farmaceut-cjenik' ); ?></h2>
			<ul>
				<li><?php echo esc_html( sprintf( __( 'Ukupno sellable SKU: %d', 'farmaceut-cjenik' ), $stat['ukupno'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Potvrđeno: %d', 'farmaceut-cjenik' ), $stat['potvrdeno'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Nepotvrđeno: %d', 'farmaceut-cjenik' ), $stat['nepotvrdeno'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Nedostaje: %d', 'farmaceut-cjenik' ), $stat['nedostaje'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Neispravno: %d', 'farmaceut-cjenik' ), $stat['neispravno'] ) ); ?></li>
			</ul>

			<?php if ( class_exists( 'Farmaceut_Cjenik_Cjenik_Objava' ) ) : ?>
				<h2><?php esc_html_e( 'Javni cjenik', 'farmaceut-cjenik' ); ?></h2>
				<p>
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?></a>
				</p>
				<ul>
					<li><?php echo esc_html( sprintf( __( 'Zadnja uspješna generacija: %s', 'farmaceut-cjenik' ), $meta['vrijeme'] ?: '—' ) ); ?></li>
					<li><?php echo esc_html( sprintf( __( 'Broj redaka: %s', 'farmaceut-cjenik' ), $meta['redovi'] !== '' ? (string) $meta['redovi'] : '—' ) ); ?></li>
					<li><?php echo esc_html( sprintf( __( 'Status: %s', 'farmaceut-cjenik' ), $meta['status'] ?: '—' ) ); ?></li>
					<?php if ( ! empty( $meta['greska'] ) ) : ?>
						<li style="color:#b32d2e;"><?php echo esc_html( $meta['greska'] ); ?></li>
					<?php endif; ?>
				</ul>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="farmaceut_cjenik_regeneriraj" />
					<?php wp_nonce_field( 'farmaceut_cjenik_regeneriraj' ); ?>
					<?php submit_button( __( 'Regeneriraj cjenik', 'farmaceut-cjenik' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Uvoz baznih cijena', 'farmaceut-cjenik' ); ?></h2>
			<p><?php esc_html_e( 'CSV (UTF-8, točka-zarez): product_id ili sku; bazna_cijena; bazna_datum; bazna_osnova; izvor; potvrdeno; neto_kolicina; neto_jedinica', 'farmaceut-cjenik' ); ?></p>
			<p><a href="<?php echo esc_url( FARMACEUT_CJENIK_URL . 'assets/primjer-uvoz-baznih.csv' ); ?>"><?php esc_html_e( 'Preuzmi primjer CSV', 'farmaceut-cjenik' ); ?></a></p>
			<?php Farmaceut_Cjenik_Bazna_Cijena_Uvoz::render_forma(); ?>

			<h2><?php esc_html_e( 'Identifikatori datoteke (merchant)', 'farmaceut-cjenik' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'farmaceut_cjenik_postavke' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="farmaceut_oib"><?php esc_html_e( 'OIB', 'farmaceut-cjenik' ); ?></label></th>
						<td><input name="farmaceut_cjenik_postavke[oib]" id="farmaceut_oib" value="<?php echo esc_attr( $s['oib'] ); ?>" class="regular-text" maxlength="11" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Isti cjenik za sva prodajna mjesta', 'farmaceut-cjenik' ); ?></th>
						<td><label><input type="checkbox" name="farmaceut_cjenik_postavke[isti_cjenik_svuda]" value="1" <?php checked( $s['isti_cjenik_svuda'], '1' ); ?> /> ZA_SVA_PRODAJNA_MJESTA</label></td>
					</tr>
					<tr>
						<th><label for="farmaceut_pm"><?php esc_html_e( 'Prodajno mjesto', 'farmaceut-cjenik' ); ?></label></th>
						<td><input name="farmaceut_cjenik_postavke[prodajno_mjesto]" id="farmaceut_pm" value="<?php echo esc_attr( $s['prodajno_mjesto'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="farmaceut_adresa"><?php esc_html_e( 'Adresa (za naziv datoteke)', 'farmaceut-cjenik' ); ?></label></th>
						<td><input name="farmaceut_cjenik_postavke[adresa]" id="farmaceut_adresa" value="<?php echo esc_attr( $s['adresa'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="farmaceut_oznaka"><?php esc_html_e( 'Oznaka prodajnog mjesta', 'farmaceut-cjenik' ); ?></label></th>
						<td><input name="farmaceut_cjenik_postavke[oznaka]" id="farmaceut_oznaka" value="<?php echo esc_attr( $s['oznaka'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="farmaceut_broj"><?php esc_html_e( 'Sljedeći broj pohrane', 'farmaceut-cjenik' ); ?></label></th>
						<td><input type="number" min="1" name="farmaceut_cjenik_postavke[broj_pohrane]" id="farmaceut_broj" value="<?php echo esc_attr( (string) $s['broj_pohrane'] ); ?>" /></td>
					</tr>
					<?php if ( class_exists( 'Farmaceut_Cjenik_Omnibus' ) ) : ?>
					<tr>
						<th><?php esc_html_e( 'Omnibus prikaz (30 dana)', 'farmaceut-cjenik' ); ?></th>
						<td><label><input type="checkbox" name="farmaceut_cjenik_postavke[omnibus_aktivan]" value="1" <?php checked( $s['omnibus_aktivan'], '1' ); ?> /> <?php esc_html_e( 'Na akciji prekrižena cijena = najniža u 30 dana', 'farmaceut-cjenik' ); ?></label>
						<p class="description"><?php esc_html_e( 'Kad je uključeno, umjesto redovne cijene prikazuje se Omnibus referenca (prekriženo) uz akcijsku. Postotak sniženja računa se od te reference.', 'farmaceut-cjenik' ); ?></p></td>
					</tr>
					<?php endif; ?>
				</table>
				<?php submit_button( __( 'Spremi postavke', 'farmaceut-cjenik' ), 'primary', 'farmaceut_cjenik_spremi_postavke' ); ?>
			</form>
			<p><code><?php echo esc_html( Farmaceut_Cjenik_Postavke::naziv_datoteke() . '.csv' ); ?></code></p>
		</div>
		<?php
	}

	/**
	 * Ručna regeneracija.
	 */
	public static function obrada_regeneriraj(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Nedovoljne ovlasti.', 'farmaceut-cjenik' ) );
		}
		check_admin_referer( 'farmaceut_cjenik_regeneriraj' );
		if ( class_exists( 'Farmaceut_Cjenik_Cjenik_Objava' ) ) {
			Farmaceut_Cjenik_Cjenik_Objava::generiraj_i_objavi( true );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=farmaceut-cjenik&regenerirano=1' ) );
		exit;
	}
}

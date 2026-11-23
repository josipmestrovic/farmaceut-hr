<?php
/**
 * Servis za bazne cijene proizvoda/varijacija.
 *
 * @package Farmaceut_Cjenik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Čitanje, validacija i HTML bazne cijene.
 */
class Farmaceut_Cjenik_Bazna_Cijena_Servis {

	public const META_CIJENA     = '_farmaceut_bazna_cijena';
	public const META_DATUM      = '_farmaceut_bazna_datum';
	public const META_OSNOVA     = '_farmaceut_bazna_osnova';
	public const META_POTVRDENO  = '_farmaceut_bazna_potvrdeno';
	public const META_IZVOR      = '_farmaceut_bazna_izvor';
	public const META_REVIZIJA   = '_farmaceut_bazna_revizija';

	public const OSNOVA_2025          = 'osnova_2025';
	public const OSNOVA_2026          = 'osnova_2026';
	public const OSNOVA_PRVA_PONUDA   = 'prva_ponuda';
	public const OSNOVA_AKCIJA        = 'akcija_referenca';

	public const DATUM_2025 = '2025-05-02';
	public const DATUM_2026 = '2026-09-10';

	/**
	 * Dopuštene osnove.
	 *
	 * @return string[]
	 */
	public static function dopustene_osnove(): array {
		return array(
			self::OSNOVA_2025,
			self::OSNOVA_2026,
			self::OSNOVA_PRVA_PONUDA,
			self::OSNOVA_AKCIJA,
		);
	}

	/**
	 * Labele osnova za admin.
	 *
	 * @return array<string,string>
	 */
	public static function labele_osnova(): array {
		return array(
			self::OSNOVA_2025        => __( 'Referentni datum 2. 5. 2025. (hrana, kozmetika…)', 'farmaceut-cjenik' ),
			self::OSNOVA_2026        => __( 'Referentni datum 10. 9. 2026. (ostala roba)', 'farmaceut-cjenik' ),
			self::OSNOVA_PRVA_PONUDA => __( 'Prvo stavljanje u ponudu', 'farmaceut-cjenik' ),
			self::OSNOVA_AKCIJA      => __( 'Posebno pravilo – akcija na referentni dan', 'farmaceut-cjenik' ),
		);
	}

	/**
	 * Normalizira decimalni iznos u string.
	 *
	 * @param mixed $vrijednost Raw value.
	 * @return string|null
	 */
	public static function normaliziraj_cijenu( $vrijednost ): ?string {
		if ( null === $vrijednost || '' === $vrijednost ) {
			return null;
		}
		if ( is_string( $vrijednost ) ) {
			$vrijednost = str_replace( array( ' ', ',' ), array( '', '.' ), $vrijednost );
		}
		if ( ! is_numeric( $vrijednost ) ) {
			return null;
		}
		$broj = (float) $vrijednost;
		if ( $broj < 0 ) {
			return null;
		}
		return wc_format_decimal( $broj, wc_get_price_decimals() );
	}

	/**
	 * Validira ISO datum.
	 *
	 * @param string $datum Datum.
	 * @return string|null
	 */
	public static function normaliziraj_datum( string $datum ): ?string {
		$datum = trim( $datum );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $datum ) ) {
			return null;
		}
		$d = DateTime::createFromFormat( 'Y-m-d', $datum );
		if ( ! $d || $d->format( 'Y-m-d' ) !== $datum ) {
			return null;
		}
		return $datum;
	}

	/**
	 * Validira osnovu.
	 *
	 * @param string $osnova Osnova.
	 * @return string|null
	 */
	public static function normaliziraj_osnovu( string $osnova ): ?string {
		$osnova = sanitize_key( $osnova );
		return in_array( $osnova, self::dopustene_osnove(), true ) ? $osnova : null;
	}

	/**
	 * Predloženi datum prema osnovi.
	 *
	 * @param string $osnova Osnova.
	 * @return string|null
	 */
	public static function predlozeni_datum_za_osnovu( string $osnova ): ?string {
		if ( self::OSNOVA_2025 === $osnova ) {
			return self::DATUM_2025;
		}
		if ( self::OSNOVA_2026 === $osnova ) {
			return self::DATUM_2026;
		}
		return null;
	}

	/**
	 * Dohvaća podatke bazne cijene.
	 *
	 * @param WC_Product $proizvod Product.
	 * @return array{cijena:string,datum:string,osnova:string,potvrdeno:bool,izvor:string}|null
	 */
	public static function dohvati( WC_Product $proizvod ): ?array {
		$cijena = self::normaliziraj_cijenu( $proizvod->get_meta( self::META_CIJENA, true ) );
		$datum  = self::normaliziraj_datum( (string) $proizvod->get_meta( self::META_DATUM, true ) );
		$osnova = self::normaliziraj_osnovu( (string) $proizvod->get_meta( self::META_OSNOVA, true ) );

		if ( null === $cijena || null === $datum || null === $osnova ) {
			return null;
		}

		return array(
			'cijena'     => $cijena,
			'datum'      => $datum,
			'osnova'     => $osnova,
			'potvrdeno'  => 'yes' === $proizvod->get_meta( self::META_POTVRDENO, true ),
			'izvor'      => (string) $proizvod->get_meta( self::META_IZVOR, true ),
		);
	}

	/**
	 * Sprema baznu cijenu.
	 *
	 * @param WC_Product $proizvod Product.
	 * @param array      $podaci   Data.
	 * @param string     $razlog   Reason for change.
	 * @return true|WP_Error
	 */
	public static function spremi( WC_Product $proizvod, array $podaci, string $razlog = '' ) {
		$cijena = self::normaliziraj_cijenu( $podaci['cijena'] ?? null );
		$datum  = self::normaliziraj_datum( (string) ( $podaci['datum'] ?? '' ) );
		$osnova = self::normaliziraj_osnovu( (string) ( $podaci['osnova'] ?? '' ) );

		if ( null === $cijena ) {
			return new WP_Error( 'neazna_cijena', __( 'Neispravna bazna cijena.', 'farmaceut-cjenik' ) );
		}
		if ( null === $datum ) {
			return new WP_Error( 'neispravan_datum', __( 'Neispravan datum bazne cijene.', 'farmaceut-cjenik' ) );
		}
		if ( null === $osnova ) {
			return new WP_Error( 'neispravna_osnova', __( 'Neispravna osnova bazne cijene.', 'farmaceut-cjenik' ) );
		}

		$stari = self::dohvati( $proizvod );
		$potvrdeno = ! empty( $podaci['potvrdeno'] ) && ( true === $podaci['potvrdeno'] || 'yes' === $podaci['potvrdeno'] );

		if ( $stari && $stari['potvrdeno'] && ( $stari['cijena'] !== $cijena || $stari['datum'] !== $datum || $stari['osnova'] !== $osnova ) ) {
			if ( '' === trim( $razlog ) ) {
				return new WP_Error( 'nedostaje_razlog', __( 'Za izmjenu potvrđene bazne cijene potreban je razlog.', 'farmaceut-cjenik' ) );
			}
		}

		$proizvod->update_meta_data( self::META_CIJENA, $cijena );
		$proizvod->update_meta_data( self::META_DATUM, $datum );
		$proizvod->update_meta_data( self::META_OSNOVA, $osnova );
		$proizvod->update_meta_data( self::META_POTVRDENO, $potvrdeno ? 'yes' : 'no' );
		$proizvod->update_meta_data( self::META_IZVOR, sanitize_text_field( (string) ( $podaci['izvor'] ?? '' ) ) );

		if ( $stari && ( $stari['cijena'] !== $cijena || $stari['datum'] !== $datum || $stari['osnova'] !== $osnova ) ) {
			self::dodaj_reviziju( $proizvod, $stari, array(
				'cijena' => $cijena,
				'datum'  => $datum,
				'osnova' => $osnova,
			), $razlog );
		}

		$proizvod->save();
		do_action( 'farmaceut_cjenik_promijenjen', $proizvod->get_id() );

		return true;
	}

	/**
	 * Dodaje zapis u audit listu.
	 *
	 * @param WC_Product $proizvod Product.
	 * @param array      $prije    Before.
	 * @param array      $poslije  After.
	 * @param string     $razlog   Reason.
	 */
	private static function dodaj_reviziju( WC_Product $proizvod, array $prije, array $poslije, string $razlog ): void {
		$lista = $proizvod->get_meta( self::META_REVIZIJA, true );
		if ( ! is_array( $lista ) ) {
			$lista = array();
		}
		$lista[] = array(
			'prije'  => $prije,
			'poslije'=> $poslije,
			'kada'   => gmdate( 'c' ),
			'akter'  => get_current_user_id(),
			'razlog' => sanitize_text_field( $razlog ),
		);
		if ( count( $lista ) > 20 ) {
			$lista = array_slice( $lista, -20 );
		}
		$proizvod->update_meta_data( self::META_REVIZIJA, $lista );
	}

	/**
	 * Formatira datum za prikaz (D. M. GGGG.).
	 *
	 * @param string $iso ISO date.
	 * @return string
	 */
	public static function formatiraj_datum_prikaz( string $iso ): string {
		$d = DateTime::createFromFormat( 'Y-m-d', $iso );
		if ( ! $d ) {
			return $iso;
		}
		return $d->format( 'j. n. Y.' );
	}

	/**
	 * HTML oznaka bazne cijene.
	 *
	 * @param WC_Product $proizvod Product.
	 * @param string     $kontekst Context.
	 * @return string
	 */
	public static function html( WC_Product $proizvod, string $kontekst = 'card' ): string {
		unset( $kontekst );
		$podaci = self::dohvati( $proizvod );
		if ( ! $podaci ) {
			return '';
		}

		$label = sprintf(
			/* translators: %s: formatted date */
			__( 'Cijena na %s:', 'farmaceut-cjenik' ),
			self::formatiraj_datum_prikaz( $podaci['datum'] )
		);

		return sprintf(
			'<div class="farmaceut-cjenik__bazna" data-farmaceut-cjenik-bazna><span class="farmaceut-cjenik__bazna-label">%s</span> <span class="farmaceut-cjenik__bazna-iznos">%s</span></div>',
			esc_html( $label ),
			wp_kses_post( wc_price( (float) $podaci['cijena'] ) )
		);
	}

	/**
	 * Raspon baznih cijena za varijabilni proizvod.
	 *
	 * @param WC_Product $proizvod Parent variable product.
	 * @return string
	 */
	public static function html_raspon_varijabilnog( WC_Product $proizvod ): string {
		if ( ! $proizvod->is_type( 'variable' ) ) {
			return self::html( $proizvod, 'single' );
		}

		$min = null;
		$max = null;
		foreach ( $proizvod->get_children() as $id ) {
			$v = wc_get_product( $id );
			if ( ! $v || ! $v->is_purchasable() ) {
				continue;
			}
			$p = self::dohvati( $v );
			if ( ! $p ) {
				continue;
			}
			$c = (float) $p['cijena'];
			$min = null === $min ? $c : min( $min, $c );
			$max = null === $max ? $c : max( $max, $c );
		}

		if ( null === $min ) {
			return '<div class="farmaceut-cjenik__bazna" data-farmaceut-cjenik-bazna></div>';
		}

		if ( $min === $max ) {
			$tekst = sprintf(
				/* translators: %s: price */
				__( 'Bazna cijena: %s', 'farmaceut-cjenik' ),
				wp_kses_post( wc_price( $min ) )
			);
		} else {
			$tekst = sprintf(
				/* translators: 1: min price 2: max price */
				__( 'Bazna cijena po varijaciji: od %1$s do %2$s', 'farmaceut-cjenik' ),
				wp_kses_post( wc_price( $min ) ),
				wp_kses_post( wc_price( $max ) )
			);
		}

		return '<div class="farmaceut-cjenik__bazna" data-farmaceut-cjenik-bazna><span class="farmaceut-cjenik__bazna-label">' . $tekst . '</span></div>';
	}

	/**
	 * Statistika completenessa.
	 *
	 * @return array<string,int>
	 */
	public static function statistika(): array {
		$ukupno = 0;
		$potvrdeno = 0;
		$nedostaje = 0;
		$nepotvrdeno = 0;
		$neispravno = 0;

		$ids = self::ids_sellable();
		foreach ( $ids as $id ) {
			++$ukupno;
			$p = wc_get_product( $id );
			if ( ! $p ) {
				++$neispravno;
				continue;
			}
			$cijena_raw = $p->get_meta( self::META_CIJENA, true );
			if ( '' === $cijena_raw || null === $cijena_raw ) {
				++$nedostaje;
				continue;
			}
			$podaci = self::dohvati( $p );
			if ( ! $podaci ) {
				++$neispravno;
				continue;
			}
			if ( $podaci['potvrdeno'] ) {
				++$potvrdeno;
			} else {
				++$nepotvrdeno;
			}
		}

		return array(
			'ukupno'      => $ukupno,
			'potvrdeno'   => $potvrdeno,
			'nedostaje'   => $nedostaje,
			'nepotvrdeno' => $nepotvrdeno,
			'neispravno'  => $neispravno,
		);
	}

	/**
	 * ID-evi javno dostupnih sellable proizvoda/varijacija.
	 *
	 * @return int[]
	 */
	public static function ids_sellable(): array {
		$ids = array();

		$proizvodi = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
				'type'   => array( 'simple', 'variable', 'variation' ),
				'return' => 'objects',
			)
		);

		foreach ( $proizvodi as $p ) {
			if ( $p->is_type( 'variable' ) ) {
				foreach ( $p->get_children() as $vid ) {
					$v = wc_get_product( $vid );
					if ( $v && 'publish' === $v->get_status() ) {
						$ids[] = (int) $vid;
					}
				}
				continue;
			}
			if ( $p->is_type( 'variation' ) ) {
				continue;
			}
			$ids[] = (int) $p->get_id();
		}

		return array_values( array_unique( $ids ) );
	}
}

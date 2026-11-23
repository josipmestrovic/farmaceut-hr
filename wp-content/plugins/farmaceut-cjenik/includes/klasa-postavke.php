<?php
/**
 * Postavke trgovca za naziv CSV datoteke i cjenik.
 *
 * @package Farmaceut_Cjenik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Merchant identifikatori i opcije cjenika.
 */
class Farmaceut_Cjenik_Postavke {

	public const OPTION = 'farmaceut_cjenik_postavke';

	/**
	 * Init.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'registriraj' ) );
	}

	/**
	 * Zadane postavke.
	 *
	 * @return array<string,mixed>
	 */
	public static function zadano(): array {
		return array(
			'oib'                    => '',
			'isti_cjenik_svuda'      => '1',
			'prodajno_mjesto'        => 'webshop',
			'adresa'                 => '',
			'oznaka'                 => 'WEB-01',
			'broj_pohrane'           => 1,
			'omnibus_aktivan'        => '0',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function dohvati(): array {
		$s = get_option( self::OPTION, array() );
		if ( ! is_array( $s ) ) {
			$s = array();
		}
		return array_merge( self::zadano(), $s );
	}

	/**
	 * Registracija settings API.
	 */
	public static function registriraj(): void {
		register_setting(
			'farmaceut_cjenik_postavke_grupa',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitiziraj' ),
				'default'           => self::zadano(),
			)
		);
	}

	/**
	 * @param mixed $ulaz Input.
	 * @return array<string,mixed>
	 */
	public static function sanitiziraj( $ulaz ): array {
		$trenutno = self::dohvati();
		if ( ! is_array( $ulaz ) ) {
			return $trenutno;
		}
		$oib = preg_replace( '/\D+/', '', (string) ( $ulaz['oib'] ?? '' ) );
		return array(
			'oib'               => substr( (string) $oib, 0, 11 ),
			'isti_cjenik_svuda' => empty( $ulaz['isti_cjenik_svuda'] ) ? '0' : '1',
			'prodajno_mjesto'   => sanitize_title( (string) ( $ulaz['prodajno_mjesto'] ?? 'webshop' ) ),
			'adresa'            => self::slug_naziva( (string) ( $ulaz['adresa'] ?? '' ) ),
			'oznaka'            => self::slug_naziva( (string) ( $ulaz['oznaka'] ?? 'WEB-01' ) ),
			'broj_pohrane'      => max( 1, absint( $ulaz['broj_pohrane'] ?? $trenutno['broj_pohrane'] ) ),
			'omnibus_aktivan'   => empty( $ulaz['omnibus_aktivan'] ) ? '0' : '1',
		);
	}

	/**
	 * Velika slova, bez dijakritika, podvlake.
	 *
	 * @param string $tekst Text.
	 * @return string
	 */
	public static function slug_naziva( string $tekst ): string {
		$tekst = remove_accents( $tekst );
		$tekst = strtoupper( $tekst );
		$tekst = preg_replace( '/[^A-Z0-9]+/', '_', $tekst );
		return trim( (string) $tekst, '_' );
	}

	/**
	 * Propisani naziv datoteke (bez ekstenzije).
	 *
	 * @param DateTimeInterface|null $vrijeme Timestamp.
	 * @return string
	 */
	public static function naziv_datoteke( ?DateTimeInterface $vrijeme = null ): string {
		$s       = self::dohvati();
		$vrijeme = $vrijeme ?: new DateTimeImmutable( 'now', wp_timezone() );
		$stamp   = $vrijeme->format( 'Ymd_Hi' );
		$broj    = str_pad( (string) (int) $s['broj_pohrane'], 4, '0', STR_PAD_LEFT );
		$oib     = $s['oib'] !== '' ? $s['oib'] : '00000000000';

		if ( '1' === $s['isti_cjenik_svuda'] ) {
			return sprintf( '%s_ZA_SVA_PRODAJNA_MJESTA_ROBA_%s_%s', $oib, $broj, $stamp );
		}

		return sprintf(
			'%s_%s_%s_%s_ROBA_%s_%s',
			$oib,
			self::slug_naziva( (string) $s['prodajno_mjesto'] ),
			self::slug_naziva( (string) $s['adresa'] ),
			self::slug_naziva( (string) $s['oznaka'] ),
			$broj,
			$stamp
		);
	}

	/**
	 * Povećava broj pohrane nakon uspješne objave.
	 */
	public static function povecaj_broj_pohrane(): void {
		$s = self::dohvati();
		$s['broj_pohrane'] = (int) $s['broj_pohrane'] + 1;
		update_option( self::OPTION, $s, false );
	}

	/**
	 * Je li Omnibus UI aktivan.
	 */
	public static function omnibus_aktivan(): bool {
		$s = self::dohvati();
		return '1' === ( $s['omnibus_aktivan'] ?? '0' );
	}
}

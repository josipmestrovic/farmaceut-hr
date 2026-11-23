<?php
/**
 * Raspored regeneracije cjenika.
 *
 * @package Farmaceut_Cjenik
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dirty flag, debounce, cron.
 */
class Farmaceut_Cjenik_Cjenik_Raspored {

	public const HOOK_DEBOUNCE = 'farmaceut_cjenik_debounce_generacija';
	public const HOOK_PERIODIC = 'farmaceut_cjenik_periodicna_generacija';
	public const HOOK_JUTARNJI = 'farmaceut_cjenik_jutarnja_provjera';

	/**
	 * Init.
	 */
	public static function init(): void {
		add_action( 'farmaceut_cjenik_promijenjen', array( __CLASS__, 'na_promjenu' ) );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'na_promjenu_proizvoda' ) );
		add_action( 'woocommerce_update_product_variation', array( __CLASS__, 'na_promjenu_proizvoda' ) );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'na_promjenu_proizvoda' ) );
		add_action( 'woocommerce_new_product_variation', array( __CLASS__, 'na_promjenu_proizvoda' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'na_status' ), 10, 3 );

		add_action( self::HOOK_DEBOUNCE, array( __CLASS__, 'pokreni_generaciju' ) );
		add_action( self::HOOK_PERIODIC, array( __CLASS__, 'pokreni_generaciju' ) );
		add_action( self::HOOK_JUTARNJI, array( __CLASS__, 'pokreni_generaciju' ) );

		self::rasporedi_poslove();
	}

	/**
	 * @param int $id Product ID.
	 */
	public static function na_promjenu( $id = 0 ): void {
		unset( $id );
		Farmaceut_Cjenik_Cjenik_Objava::oznaci_dirty();
		self::zakazi_debounce();
	}

	/**
	 * @param int $id Product ID.
	 */
	public static function na_promjenu_proizvoda( int $id ): void {
		unset( $id );
		self::na_promjenu();
	}

	/**
	 * @param string  $novi New status.
	 * @param string  $stari Old status.
	 * @param WP_Post $post Post.
	 */
	public static function na_status( string $novi, string $stari, $post ): void {
		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
			return;
		}
		if ( $novi === $stari ) {
			return;
		}
		self::na_promjenu();
	}

	/**
	 * Debounce ~60 s.
	 */
	public static function zakazi_debounce(): void {
		$ts = time() + 60;
		if ( function_exists( 'as_schedule_single_action' ) ) {
			if ( ! as_next_scheduled_action( self::HOOK_DEBOUNCE ) ) {
				as_schedule_single_action( $ts, self::HOOK_DEBOUNCE );
			}
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK_DEBOUNCE ) ) {
			wp_schedule_single_event( $ts, self::HOOK_DEBOUNCE );
		}
	}

	/**
	 * Periodični poslovi.
	 */
	public static function rasporedi_poslove(): void {
		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			if ( ! as_next_scheduled_action( self::HOOK_PERIODIC ) ) {
				as_schedule_recurring_action( time() + 300, 15 * MINUTE_IN_SECONDS, self::HOOK_PERIODIC );
			}
		} elseif ( ! wp_next_scheduled( self::HOOK_PERIODIC ) ) {
			wp_schedule_event( time() + 300, 'farmaceut_cjenik_15min', self::HOOK_PERIODIC );
		}

		$sutra_10 = self::sljedeci_jutarnji_timestamp();
		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			if ( ! as_next_scheduled_action( self::HOOK_JUTARNJI ) ) {
				as_schedule_recurring_action( $sutra_10, DAY_IN_SECONDS, self::HOOK_JUTARNJI );
			}
		} elseif ( ! wp_next_scheduled( self::HOOK_JUTARNJI ) ) {
			wp_schedule_event( $sutra_10, 'daily', self::HOOK_JUTARNJI );
		}

		add_filter(
			'cron_schedules',
			static function ( $schedules ) {
				$schedules['farmaceut_cjenik_15min'] = array(
					'interval' => 15 * MINUTE_IN_SECONDS,
					'display'  => __( 'Svakih 15 minuta (Farmaceut cjenik)', 'farmaceut-cjenik' ),
				);
				return $schedules;
			}
		);
	}

	/**
	 * @return int
	 */
	private static function sljedeci_jutarnji_timestamp(): int {
		$tz   = wp_timezone();
		$sada = new DateTimeImmutable( 'now', $tz );
		$cilj = $sada->setTime( 10, 30, 0 );
		if ( $cilj <= $sada ) {
			$cilj = $cilj->modify( '+1 day' );
		}
		return $cilj->getTimestamp();
	}

	/**
	 * Uklanja cron pri deaktivaciji.
	 */
	public static function ukloni_poslove(): void {
		wp_clear_scheduled_hook( self::HOOK_DEBOUNCE );
		wp_clear_scheduled_hook( self::HOOK_PERIODIC );
		wp_clear_scheduled_hook( self::HOOK_JUTARNJI );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK_DEBOUNCE );
			as_unschedule_all_actions( self::HOOK_PERIODIC );
			as_unschedule_all_actions( self::HOOK_JUTARNJI );
		}
	}

	/**
	 * Worker.
	 */
	public static function pokreni_generaciju(): void {
		$dirty = get_option( Farmaceut_Cjenik_Cjenik_Objava::OPTION_DIRTY );
		$meta  = Farmaceut_Cjenik_Cjenik_Objava::meta_status();
		if ( ! $dirty && 'ok' === ( $meta['status'] ?? '' ) && current_action() === self::HOOK_PERIODIC ) {
			// Periodična: ipak usporedi hash (scheduled sale).
			Farmaceut_Cjenik_Cjenik_Objava::generiraj_i_objavi( false );
			return;
		}
		Farmaceut_Cjenik_Cjenik_Objava::generiraj_i_objavi( false );
	}
}

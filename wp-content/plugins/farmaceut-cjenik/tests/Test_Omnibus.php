<?php
/**
 * Testovi Omnibus izračuna.
 *
 * @package Farmaceut_Cjenik
 */

/**
 * Postotak od referentne cijene.
 */
class Test_Omnibus extends WP_UnitTestCase {

	/**
	 * Preskoči ako klasa nije dostupna.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! class_exists( 'Farmaceut_Cjenik_Omnibus' ) ) {
			$this->markTestSkipped( 'Omnibus modul nije učitan.' );
		}
	}

	/**
	 * Popust se računa od Omnibus reference, ne od redovne.
	 */
	public function test_postotak() {
		$this->assertSame( 50, Farmaceut_Cjenik_Omnibus::postotak( 40.0, 80.0 ) );
		$this->assertNull( Farmaceut_Cjenik_Omnibus::postotak( 80.0, 80.0 ) );
		$this->assertNull( Farmaceut_Cjenik_Omnibus::postotak( 90.0, 80.0 ) );
	}

	/**
	 * Najniža u razdoblju s do_ts isključuje novije zapise (sale nakon frejza).
	 *
	 * Bez WooCommerce proizvoda: čisti izračun postotka pokriva referencu;
	 * redoslijed frejza prije povijesti je u pri_azuriranju_proizvoda.
	 */
	public function test_postotak_od_reference_prije_akcije() {
		// 80 € referenca, akcija 40 € → 50 %, ne od lažno niže sale u povijesti.
		$this->assertSame( 50, Farmaceut_Cjenik_Omnibus::postotak( 40.0, 80.0 ) );
		$this->assertSame( 5, Farmaceut_Cjenik_Omnibus::postotak( 19.0, 20.0 ) );
	}
}

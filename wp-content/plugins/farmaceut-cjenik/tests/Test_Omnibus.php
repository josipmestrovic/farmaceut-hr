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
}

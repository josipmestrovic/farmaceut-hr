<?php
/**
 * Testovi jedinične cijene.
 *
 * @package Farmaceut_Cjenik
 */

/**
 * Izračun cijene za jedinicu mjere.
 */
class Test_Jedinicna_Cijena extends WP_UnitTestCase {

	/**
	 * Preskoči ako klasa nije dostupna.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! class_exists( 'Farmaceut_Cjenik_Jedinicna_Cijena' ) ) {
			$this->markTestSkipped( 'Plugin farmaceut-cjenik nije učitan.' );
		}
	}

	/**
	 * 60 g za 22,50 € → 37,50 € / 100 g.
	 */
	public function test_izracun_po_100g() {
		$r = Farmaceut_Cjenik_Jedinicna_Cijena::izracunaj( 22.5, 60.0, 'g' );
		$this->assertIsArray( $r );
		$this->assertSame( '100 g', $r['oznaka'] );
		$this->assertEqualsWithDelta( 37.5, $r['iznos'], 0.001 );
	}

	/**
	 * Pakiranja ispod 50 g su izuzeta.
	 */
	public function test_izuzeca_male_kolicine() {
		$this->assertTrue( Farmaceut_Cjenik_Jedinicna_Cijena::je_izuzeto( 40.0, 'g' ) );
		$this->assertFalse( Farmaceut_Cjenik_Jedinicna_Cijena::je_izuzeto( 60.0, 'g' ) );
		$this->assertTrue( Farmaceut_Cjenik_Jedinicna_Cijena::je_izuzeto( 30.0, 'ml' ) );
	}
}

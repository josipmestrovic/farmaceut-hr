<?php
/**
 * Testovi validacije bazne cijene.
 *
 * @package Farmaceut_Cjenik
 */

/**
 * Unit/integracijski testovi servisa bazne cijene.
 */
class Test_Bazna_Cijena_Servis extends WP_UnitTestCase {

	/**
	 * Preskoči ako plugin nije učitan.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! class_exists( 'Farmaceut_Cjenik_Bazna_Cijena_Servis' ) ) {
			$this->markTestSkipped( 'Plugin farmaceut-cjenik nije učitan.' );
		}
	}

	/**
	 * Datum mora biti valjan ISO Y-m-d.
	 */
	public function test_normaliziraj_datum() {
		$this->assertSame( '2025-05-02', Farmaceut_Cjenik_Bazna_Cijena_Servis::normaliziraj_datum( '2025-05-02' ) );
		$this->assertNull( Farmaceut_Cjenik_Bazna_Cijena_Servis::normaliziraj_datum( '02.05.2025' ) );
		$this->assertNull( Farmaceut_Cjenik_Bazna_Cijena_Servis::normaliziraj_datum( '2025-13-01' ) );
	}

	/**
	 * Samo poznate osnove su dopuštene.
	 */
	public function test_normaliziraj_osnovu() {
		$this->assertSame( 'osnova_2025', Farmaceut_Cjenik_Bazna_Cijena_Servis::normaliziraj_osnovu( 'osnova_2025' ) );
		$this->assertSame( 'prva_ponuda', Farmaceut_Cjenik_Bazna_Cijena_Servis::normaliziraj_osnovu( 'prva_ponuda' ) );
		$this->assertNull( Farmaceut_Cjenik_Bazna_Cijena_Servis::normaliziraj_osnovu( 'nepoznato' ) );
	}

	/**
	 * Predloženi datumi za referentne osnove.
	 */
	public function test_predlozeni_datum() {
		$this->assertSame( '2025-05-02', Farmaceut_Cjenik_Bazna_Cijena_Servis::predlozeni_datum_za_osnovu( 'osnova_2025' ) );
		$this->assertSame( '2026-09-10', Farmaceut_Cjenik_Bazna_Cijena_Servis::predlozeni_datum_za_osnovu( 'osnova_2026' ) );
		$this->assertNull( Farmaceut_Cjenik_Bazna_Cijena_Servis::predlozeni_datum_za_osnovu( 'prva_ponuda' ) );
	}

	/**
	 * Format prikaza datuma.
	 */
	public function test_formatiraj_datum_prikaz() {
		$this->assertSame( '2. 5. 2025.', Farmaceut_Cjenik_Bazna_Cijena_Servis::formatiraj_datum_prikaz( '2025-05-02' ) );
	}
}

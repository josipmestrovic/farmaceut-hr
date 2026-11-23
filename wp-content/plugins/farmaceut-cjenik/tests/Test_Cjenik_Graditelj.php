<?php
/**
 * Testovi CSV graditelja.
 *
 * @package Farmaceut_Cjenik
 */

/**
 * Zaglavlja i CSV serializacija.
 */
class Test_Cjenik_Graditelj extends WP_UnitTestCase {

	/**
	 * Preskoči ako klasa nije dostupna.
	 */
	public function set_up() {
		parent::set_up();
		if ( ! class_exists( 'Farmaceut_Cjenik_Cjenik_Graditelj' ) ) {
			$this->markTestSkipped( 'Modul cjenika nije učitan.' );
		}
	}

	/**
	 * Redoslijed stupaca prema pravilniku.
	 */
	public function test_redoslijed_zaglavlja() {
		$z = Farmaceut_Cjenik_Cjenik_Graditelj::zaglavlja();
		$this->assertSame(
			array(
				'naziv',
				'sifra',
				'marka',
				'neto_kolicina',
				'jedinica_mjere',
				'maloprodajna_cijena',
				'posebni_oblik_prodaje',
				'cijena_za_jedinicu_mjere',
				'barkod',
				'ean',
				'bazna_cijena',
			),
			$z
		);
	}

	/**
	 * CSV koristi točku-zarez i sadrži zaglavlje.
	 */
	public function test_csv_string() {
		$csv = Farmaceut_Cjenik_Cjenik_Graditelj::u_csv_string(
			array(
				array(
					'naziv'                    => 'Test',
					'sifra'                    => 'SKU1',
					'marka'                    => 'Brand',
					'neto_kolicina'            => '60',
					'jedinica_mjere'           => 'g',
					'maloprodajna_cijena'      => '22.50',
					'posebni_oblik_prodaje'    => '0',
					'cijena_za_jedinicu_mjere' => '37.50',
					'barkod'                   => '',
					'ean'                      => '',
					'bazna_cijena'             => '22.50',
				),
			)
		);
		$this->assertStringContainsString( 'naziv;sifra;marka', $csv );
		$this->assertStringContainsString( 'Test;SKU1;Brand', $csv );
	}
}

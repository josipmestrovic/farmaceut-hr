<?php
/**
 * Integracijski testovi teme Farmaceut.
 *
 * @package Farmaceut
 */

/**
 * Provjerava stvarnu WordPress integraciju teme farmaceut.
 */
class Test_Tema_Farmaceut extends WP_UnitTestCase {

	/**
	 * WordPress prepoznaje i aktivira temu farmaceut.
	 */
	public function test_tema_je_aktivna_i_prepoznata() {
		$this->assertSame( 'farmaceut', get_template() );
		$this->assertSame( 'farmaceut', get_stylesheet() );

		$tema = wp_get_theme();
		$this->assertTrue( $tema->exists(), 'Tema farmaceut mora postojati.' );
		$this->assertSame( 'Farmaceut', $tema->get( 'Name' ) );
	}

	/**
	 * Tema registrira lokaciju navigacije glavni_izbornik.
	 */
	public function test_registriran_glavni_izbornik() {
		$lokacije = get_registered_nav_menus();

		$this->assertIsArray( $lokacije );
		$this->assertArrayHasKey( 'glavni_izbornik', $lokacije );
	}

	/**
	 * Tema deklarira očekivane WordPress mogućnosti.
	 */
	public function test_podrska_teme() {
		$this->assertTrue( current_theme_supports( 'title-tag' ) );
		$this->assertTrue( current_theme_supports( 'post-thumbnails' ) );
	}

	/**
	 * Sadržaj se sprema kroz WP API, a tema enqueuea vlastiti stil.
	 */
	public function test_sadrzaj_i_stil_teme() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Testna objava Farmaceut',
				'post_content' => 'Sadržaj za integracijski test.',
				'post_status'  => 'publish',
				'post_type'    => 'post',
			)
		);

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		$post = get_post( $post_id );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( 'Testna objava Farmaceut', $post->post_title );
		$this->assertSame( 'Sadržaj za integracijski test.', $post->post_content );

		$this->go_to( get_permalink( $post_id ) );
		$this->assertQueryTrue( 'is_single', 'is_singular' );

		do_action( 'wp_enqueue_scripts' );
		$this->assertTrue(
			wp_style_is( 'farmaceut-glavni', 'enqueued' ),
			'Tema mora učitati stil farmaceut-glavni.'
		);
	}
}

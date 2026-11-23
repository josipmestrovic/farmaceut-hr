<?php
/**
 * Podnožje teme Farmaceut.
 *
 * @package Farmaceut
 */
?>
<footer class="podnozje" role="contentinfo">
	<p class="podnozje__tekst">
		&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
	</p>
</footer>

<?php wp_footer(); ?>
</body>
</html>

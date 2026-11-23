<?php
/**
 * Osnovni predložak teme Farmaceut.
 *
 * @package Farmaceut
 */

get_header();
?>

<main id="main">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">
				<header class="unos__zaglavlje">
					<?php
					if ( is_singular() ) {
						the_title( '<h1 class="unos__naslov">', '</h1>' );
					} else {
						the_title(
							sprintf(
								'<h2 class="unos__naslov"><a href="%s" rel="bookmark">',
								esc_url( get_permalink() )
							),
							'</a></h2>'
						);
					}
					?>
				</header>

				<div class="unos__sadrzaj">
					<?php
					if ( is_singular() ) {
						the_content();

						wp_link_pages(
							array(
								'before' => '<nav class="stranice-unosa" aria-label="' . esc_attr__( 'Stranice unosa', 'farmaceut' ) . '"><span>' . esc_html__( 'Stranice:', 'farmaceut' ) . '</span>',
								'after'  => '</nav>',
							)
						);
					} else {
						the_excerpt();
					}
					?>
				</div>
			</article>
		<?php endwhile; ?>

		<?php
		the_posts_pagination(
			array(
				'mid_size'  => 1,
				'prev_text' => __( 'Prethodno', 'farmaceut' ),
				'next_text' => __( 'Sljedeće', 'farmaceut' ),
			)
		);
		?>
	<?php else : ?>
		<section class="nema-sadrzaja">
			<h1><?php esc_html_e( 'Sadržaj nije pronađen', 'farmaceut' ); ?></h1>
			<p><?php esc_html_e( 'Traženi sadržaj trenutno nije dostupan.', 'farmaceut' ); ?></p>
		</section>
	<?php endif; ?>
</main>

<?php
get_footer();

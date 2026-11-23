<?php
/**
 * Zaglavlje teme Farmaceut.
 *
 * @package Farmaceut
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="preskoci-navigaciju" href="#main"><?php esc_html_e( 'Preskoči na sadržaj', 'farmaceut' ); ?></a>

<header class="zaglavlje" role="banner">
	<div class="zaglavlje__identitet">
		<?php if ( has_custom_logo() ) : ?>
			<?php the_custom_logo(); ?>
		<?php else : ?>
			<?php if ( is_front_page() && is_home() ) : ?>
				<h1 class="zaglavlje__naziv">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
				</h1>
			<?php else : ?>
				<p class="zaglavlje__naziv">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<?php if ( has_nav_menu( 'glavni_izbornik' ) ) : ?>
		<nav class="glavna-navigacija" aria-label="<?php esc_attr_e( 'Glavni izbornik', 'farmaceut' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'glavni_izbornik',
					'container'      => false,
					'menu_class'     => 'glavna-navigacija__lista',
					'depth'          => 1,
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>
	<?php endif; ?>
</header>

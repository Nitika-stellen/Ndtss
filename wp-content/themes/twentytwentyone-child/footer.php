<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_One
 * @since Twenty Twenty-One 1.0
 */

?>
			</main><!-- #main -->
		</div><!-- #primary -->
	</div><!-- #content -->



	<footer id="colophon" class="site-footer">
	<?php get_template_part( 'template-parts/footer/footer-widgets' ); ?>
		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<nav aria-label="<?php esc_attr_e( 'Secondary menu', 'twentytwentyone' ); ?>" class="footer-navigation">
				<ul class="footer-navigation-wrapper">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'footer',
							'items_wrap'     => '%3$s',
							'container'      => false,
							'depth'          => 1,
							'link_before'    => '<span>',
							'link_after'     => '</span>',
							'fallback_cb'    => false,
						)
					);
					?>
				</ul><!-- .footer-navigation-wrapper -->
			</nav><!-- .footer-navigation -->
		<?php endif; ?>
		<div class="site-info">
			<div class="site-name">
				<?php if ( has_custom_logo() ) : ?>
					<div class="site-logo"><?php the_custom_logo(); ?></div>
				<?php else : ?>
					<?php if ( get_bloginfo( 'name' ) && get_theme_mod( 'display_title_and_tagline', true ) ) : ?>
						<?php if ( is_front_page() && ! is_paged() ) : ?>
							<?php bloginfo( 'name' ); ?>
						<?php else : ?>
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
			</div><!-- .site-name -->

			<?php
			if ( function_exists( 'the_privacy_policy_link' ) ) {
				the_privacy_policy_link( '<div class="privacy-policy">', '</div>' );
			}
			?>

			<div class="powered-by">
				<?php
				printf(
					/* translators: %s: WordPress. */
					esc_html__( '© 2025 by Non-Destructive Testing Society ( Singapore). All Rights Reserved.', 'twentytwentyone' ),
					
				);
				?>
			</div><!-- .powered-by -->

		</div><!-- .site-info -->
	</footer><!-- #colophon -->


</div><!-- #page -->
<!-- Scroll to Top Button -->
<a href="#" class="scroll-to-top" id="scrollToTopBtn">
    ↑
</a>


<?php wp_footer(); ?>

<script>

document.addEventListener('DOMContentLoaded', function () {
    function applyFutureDateRestriction() {
        setTimeout(function() { // Ensure Datepicker is initialized
            jQuery('.disable-today .ginput_container_date input').each(function () {
                jQuery(this).datepicker({
                    minDate: 1 // Disable today and past dates, allowing only future dates
                });
                console.log("minDate applied to:", jQuery(this).attr("id"));
            });
        }, 500);
    }

    applyFutureDateRestriction(); // Apply on initial load

    // Reapply when Gravity Forms reloads fields via AJAX
    if (typeof gform !== 'undefined') {
        gform.addAction('gform_post_render', function() {
            setTimeout(applyFutureDateRestriction, 0); // Ensures immediate execution
        });
    }
});
</script>


</body>
</html>

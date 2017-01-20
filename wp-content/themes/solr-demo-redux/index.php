<?php get_header(); ?>

	<div class="site-content">

		<?php if ( have_posts() ) : ?>

			<div class="row">
				<div class="columns medium-8 medium-centered">

				<?php while( have_posts() ) : the_post(); ?>

					<article <?php post_class(); ?>

						<header class="entry-header">
							<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						</header>

						<div class="entry-content">
							<?php the_excerpt(); ?>
						</div>

					</article>

				<?php endwhile; ?>

				</div>

			</div>

		<?php endif; ?>

	</div>

<?php get_footer(); ?>
<?php get_header(); ?>

	<div class="site-content">

		<div class="row">
			<div class="columns medium-8 medium-centered">
				<form method="GET">
					<input type="text" name="s" value="<?php echo get_search_query(); ?>" />
					<input type="submit" value="Search" class="button" />
				</form>
			</div>
		</div>

		<?php if ( have_posts() ) : ?>

			<div class="row">
				<div class="columns medium-8 medium-centered">

				<div class="row small-up-1 medium-up-3">

					<?php while( have_posts() ) : the_post(); ?>

						<div class="column">

							<article <?php post_class(); ?>

								<header class="entry-header">
									<h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
								</header>

								<div class="entry-meta">
									<p>
									<?php
										$fields = array();
										if ( $year = get_post_meta( get_the_ID(), 'year', true ) ) {
											$fields['Year'] = '<a href="' . add_query_arg( 'year', $year, home_url( '/' ) ) . '">' . esc_html( $year ) . '</a>';
										}
										if ( $rating = get_post_meta( get_the_ID(), 'rating', true ) ) {
											$fields['Rating'] = '<a href="' . add_query_arg( 'rating', $rating, home_url( '/' ) ) . '">' . esc_html( $rating ) . '</a>';
										}
									foreach( $fields as $label => $link ) {
										echo '<strong>' . $label . ': </strong> ' . $link . ' ';
									} ?>
									</p>
								</div>

							</article>

						</div>

					<?php endwhile; ?>

				</div>

				<?php echo sdr_get_template_part( 'pagination' ); ?>

				</div>

			</div>

		<?php endif; ?>

	</div>

<?php get_footer(); ?>

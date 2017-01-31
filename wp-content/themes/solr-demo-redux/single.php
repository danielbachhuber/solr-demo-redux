<?php get_header(); ?>

	<div class="site-content">

		<?php if ( have_posts() ) : ?>

			<div class="row">
				<div class="columns medium-8 medium-centered">

				<?php while( have_posts() ) : the_post();
					$post = get_post();
					?>

					<article <?php post_class(); ?>>

						<header class="entry-header">
							<h2><?php the_title(); ?></h2>
						</header>

						<div class="entry-meta">
							<?php foreach( sdr_get_movie_meta() as $k ) :
								$values = get_post_meta( $post->ID, $k );
								if ( empty( $values ) ) {
									continue;
								} ?>
								<p><strong><?php echo ucwords( $k ); ?>:</strong>
									<?php foreach( $values as $i => $value ) :
										$filter_link = add_query_arg( $k, rawurlencode( $value ), home_url() );
										if ( $i !== 0 ) {
											echo ', ';
										}
									?><a href="<?php echo esc_url( $filter_link ); ?>"><?php echo esc_html( $value ); ?></a><?php endforeach; ?>
								</p>
							<?php endforeach;
								foreach( sdr_get_movie_tax() as $tax ) :
									if ( ! get_the_terms( $post, $tax ) ) {
										continue;
									} ?>
									<p><strong><?php echo ucwords( $tax ); ?>s:</strong> <?php the_terms( $post->ID, $tax ); ?></p>
									<?php
								endforeach;
							?>
						</div>

						<div class="entry-content">
							<?php the_content(); ?>
						</div>

					</article>

				<?php endwhile; ?>

				</div>

			</div>

		<?php endif; ?>

	</div>

<?php get_footer(); ?>

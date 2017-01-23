<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="profile" href="http://gmpg.org/xfn/11">
<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">

<?php wp_head(); ?>

</head>

<body <?php body_class(); ?>>

	<header class="site-header">
		<div class="row">
			<div class="columns medium-8 medium-centered">
				<div class="row">
					<div class="columns medium-9">
						<a class="site-title" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo bloginfo( 'name' ); ?></a>
						<?php if ( isset( $wp_query->sdr_total_time ) ) : ?>
							<div><strong>Query time:</strong> <?php echo esc_html( round( $wp_query->sdr_total_time, 3 ) ); ?> seconds for <?php echo (int) $wp_query->found_posts; ?> records</div>
							<canvas id="query-time-chart" height="75" width="300"></canvas>
							<?php
								$query_times = isset( $_SESSION['solr-query-times'] ) ? $_SESSION['solr-query-times'] : array();
								$query_times[] = array(
									'solr_enabled'    => isset( $_SESSION['solr-enabled'] ) && 'on' === $_SESSION['solr-enabled'] ? 'on' : 'off',
									'query_time'      => $wp_query->sdr_total_time,
									'request_uri'     => $_SERVER['REQUEST_URI'],
								);
								$query_times = array_slice( $query_times, -20, 20 );
								$_SESSION['solr-query-times'] = $query_times;
								$query_time_data = wp_list_pluck( $query_times, 'query_time' );
								$query_time_data = array_map( function( $val ){
									return round( $val, 3 );
								}, $query_time_data );
							?>
							<script>
								(function($){
									$(document).ready(function(){
										var ctx = $('#query-time-chart');
										var chart = new Chart( ctx, {
											type: 'line',
											data: {
												labels: Array( 20 ),
												datasets: [
													{
														label: 'Query Time',
														data: <?php echo json_encode( $query_time_data ) . PHP_EOL; ?>
													}
												]
											},
											options: {}
										});
									});
								}(jQuery))
							</script>
						<?php endif; ?>
					</div>
					<div class="columns medium-3">
						<div class="switch large">
							<input class="switch-input" id="solr-enabled" type="checkbox" name="solr-enabled" <?php if ( isset( $_SESSION['solr-enabled'] ) && 'on' === $_SESSION['solr-enabled'] ) echo 'checked="checked"'; ?>>
							<label class="switch-paddle" for="solr-enabled">
								<span class="switch-active" aria-hidden="true">Solr Enabled</span>
								<span class="switch-inactive" aria-hidden="true">Solr Disabled</span>
							</label>
						</div>
					</div>
				</div>
			</div>
		</div>
	</header>


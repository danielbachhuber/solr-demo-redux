<?php

/**
 * On the 'init' hook:
 *
 * - Register the 'movie' custom post type.
 * - Register the 'genre' and 'language' custom taxonomies.
 *  - Show an admin column for each taxonomy so the data is easier to reference.
 */
add_action( 'init', function(){
	register_post_type( 'movie', array(
		'labels' => array(
			'name'           => 'Movies',
			'singular_name'  => 'Movie',
		),
		'public' => true,
	) );
	register_taxonomy( 'genre', array( 'movie' ), array(
		'labels' => array(
			'name'           => 'Genres',
			'singular_name'  => 'Genre',
		),
		'show_admin_column' => true,
	) );
	register_taxonomy( 'language', array( 'movie' ), array(
		'labels' => array(
			'name'           => 'Languages',
			'singular_name'  => 'Language',
		),
		'show_admin_column' => true,
	) );
	register_taxonomy( 'country', array( 'movie' ), array(
		'labels' => array(
			'name'           => 'Countries',
			'singular_name'  => 'Country',
		),
		'show_admin_column' => true,
	) );
});

/**
 * Disable Pantheon_Cache::cache_add_headers()
 *
 * Pantheon normally caches the site in Varnish for logged-out visitors, but
 * we want to make sure it's fully disabled.
 */
if ( class_exists( 'Pantheon_Cache' ) ) {
	remove_action( 'send_headers', array( Pantheon_Cache::instance(), 'cache_add_headers' ) );
}

/**
 * Early on the 'init' hook:
 *
 * - Start a PHP session if one isn't already started. PHP sessions are used
 * to track whether Solr should be enabled for a given request.
 * - Handle a POST request to enable or disable Solr for a session.
 * - If Solr is disabled, ensure Solr Power's automatic query filtering is disabled.
 */
add_action( 'init', function() {
	global $wpdb;
	// Bail early in the admin, because we don't want any of this code to apply.
	if ( is_admin() ) {
		return;
	}
	// Start a PHP session if one isn't already started.
	if ( ! session_id() ) {
		session_start();
	}
	// Handle a POST request to enable or disable Solr for a session.
	if ( isset( $_POST['action'] ) && 'solr-enabled-form' === $_POST['action'] ) {
		$error_message = false;
		$solr_enabled = isset( $_POST['solr-enabled'] ) && 'on' === $_POST['solr-enabled'] ? 'on' : 'off';
		if ( ! class_exists( 'SolrPower_Api' ) ) {
			$error_message = 'Solr Power is not installed / activated.';
		} elseif ( ! SolrPower_Api::get_instance()->ping_server() ) {
			$error_message = 'Cannot ping Solr server.';
		}
		if ( $error_message && 'on' === $solr_enabled ) {
			wp_die( $error_message );
		}
		$_SESSION['solr-enabled'] = $solr_enabled;
	}
	$env = getenv( 'PANTHEON_ENVIRONMENT' );
	if ( $env && 'local' !== $env && ! isset( $_SESSION['query-cache-disabled'] ) ) {
		$wpdb->query( 'SET GLOBAL query_cache_size=0;' );
		$wpdb->query( 'SET GLOBAL query_cache_type=OFF;' );
		$_SESSION['query-cache-disabled'] = true;
	}
	// If Solr is disabled, ensure Solr Power's automatic query filtering is disabled.
	if ( empty( $_SESSION['solr-enabled'] ) || 'off' === $_SESSION['solr-enabled'] ) {
		if ( class_exists( 'SolrPower_WP_Query' ) ) {
			remove_action( 'init', array( SolrPower_WP_Query::get_instance(), 'setup' ) );
		}
	}
}, 9 ); // Before SolrPower_WP_Query runs its initialization

/**
 * Send the nocache header on every page request
 *
 * Doing so helps to ensure Pantheon's Varnish full page cache is bypassed.
 */
add_filter( 'wp_headers', function( $headers ){
	$headers = array_merge( $headers, wp_get_nocache_headers() );
	return $headers;
}, 100 );

/**
 * Enqueue scripts and styles specific to the frontend of the site
 */
add_action( 'wp_enqueue_scripts', function() {
	$path = '/assets/css/style.css';
	// Automatically bust the stylesheet cache by appending the file modification time
	$mtime = filemtime( get_stylesheet_directory() . $path );
	wp_enqueue_style( 'solr-demo-redux', get_stylesheet_directory_uri() . $path, false, $mtime );
	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.4.0/Chart.bundle.min.js' );
});

/**
 * Register our post meta keys as query variables
 *
 * Registering query variables tells WordPress that URLs like /?creator=Google
 * are safe to pass to WP_Query.
 */
add_filter( 'query_vars', function( $query_vars ){
	$query_vars = array_merge( $query_vars, sdr_get_movie_meta() );
	return $query_vars;
});

/**
 * Modify the main WP_Query on the frontend
 *
 * The 'pre_get_posts' action permits modification of the WP_Query object
 * before query parameters are transformed to a SQL query.
 */
add_action( 'pre_get_posts', function( $query ) {
	if ( is_admin() ) {
		return;
	}

	// Only ever query against our 'movie' custom post type.
	// Posts and pages aren't used on this site.
	$query->set( 'post_type', 'movie' );

	// If the user has switched their session from MySQL to Solr,
	// ensure Solr Power is enabled for the query
	if ( isset( $_SESSION['solr-enabled'] )
		&& 'on' === $_SESSION['solr-enabled'] ) {
		$query->set( 'solr_integrate', true );
	}

	// Inspect query params to see if any post meta keys are present
	// If post meta keys are present, they'll need to be handled as a meta query.
	$meta_query = array();
	foreach( sdr_get_movie_meta() as $key ) {
		if ( $value = $query->get( $key ) ) {
			$meta_query[] = array(
				'key'     => $key,
				'value'   => $value,
			);
		}
	}
	if ( ! empty( $meta_query ) ) {
		$query->set( 'meta_query', $meta_query );
	}

	// 'year' is a potential query parameter we want to make sure WordPress
	// handles as a meta query, not a date query
	if ( isset( $query->query['year'] ) ) {
		unset( $query->query['year'] );
	}
	if ( isset( $query->query_vars['year'] ) ) {
		unset( $query->query_vars['year'] );
	}
});

/**
 * Start timer for WP_Query's fetching of data
 */
add_filter( 'posts_request', function( $request, $query ){
	$query->sdr_start_time = microtime( true );
	return $request;
}, 1, 2 );

/**
 * End timer for WP_Query's fetching of data
 */
add_filter( 'posts_results', function( $posts, $query ){
	if ( isset( $query->sdr_start_time ) ) {
		$query->sdr_total_time = microtime( true ) - $query->sdr_start_time;
	}
	return $posts;
}, 10, 2 );

/**
 * Prevent WordPress' canonical redirect feature from redirecting
 * ?year=2014 to /2014/. We need the former for our query parsing to work.
 */
add_filter( 'redirect_canonical', function( $redirect_url ){
	if ( is_year() ) {
		return false;
	}
	return $redirect_url;
});

/**
 * Get an array of post meta keys used as a part of the movie data.
 *
 * Each of these keys represents an attribute that can be present in the
 * original data set.
 *
 * Post meta is used when a key's value is expected to be pretty much anything.
 *
 * @return array
 */
function sdr_get_movie_meta() {
	return array( 'year', 'rating', 'runtime', 'director', 'writer', 'actors' );
}

/**
 * Get an array of custom taxonomy slugs used as a part of the movie data.
 *
 * Each of these keys represents an attribute that can be present in the
 * original data set.
 *
 * Custom taxonomies are used when a key's value is expected to be of a limited
 * set of options.
 *
 * @return array
 */
function sdr_get_movie_tax() {
	return array( 'genre', 'language', 'country' );
}

/**
 * Get a template part rendered with an optional set of template variables.
 *
 * @return string
 */
function sdr_get_template_part( $template, $vars = array() ) {
	$full_path = get_template_directory() . '/parts/' . $template . '.php';
	if ( ! file_exists( $full_path ) ) {
		return '';
	}

	ob_start();
	// @codingStandardsIgnoreStart
	if ( ! empty( $vars ) ) {
		extract( $vars );
	}
	// @codingStandardsIgnoreEnd
	include $full_path;
	return ob_get_clean();
}

/**
 * Load the movie JSON importer WP-CLI command in WP-CLI context
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/cli.php';
}

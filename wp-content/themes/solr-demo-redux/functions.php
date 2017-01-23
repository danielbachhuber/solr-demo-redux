<?php

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/cli.php';
}

function sdr_get_book_meta() {
	return array( 'creator', 'sponsor', 'publisher', 'year' );
}

function sdr_get_book_tax() {
	return array( 'collection', 'subject' );
}

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

add_action( 'init', function(){
	register_post_type( 'book', array(
		'labels' => array(
			'name'           => 'Books',
			'singular_name'  => 'Book',
		),
		'public' => true,
	) );
	register_taxonomy( 'collection', array( 'book' ), array(
		'labels' => array(
			'name'           => 'Collections',
			'singular_name'  => 'Collection',
		),
		'show_admin_column' => true,
	) );
	register_taxonomy( 'subject', array( 'book' ), array(
		'labels' => array(
			'name'           => 'Subjects',
			'singular_name'  => 'Subject',
		),
		'show_admin_column' => true,
	) );
});

if ( class_exists( 'Pantheon_Cache' ) ) {
	remove_action( 'send_headers', array( Pantheon_Cache::instance(), 'cache_add_headers' ) );
}

add_action( 'init', function() {
	if ( ! session_id() ) {
		session_start();
	}
	if ( isset( $_POST['solr-enabled'] )
		&& in_array( $_POST['solr-enabled'], array( 'on', 'off' ), true ) ) {
		$_SESSION['solr-enabled'] = $_POST['solr-enabled'];
	}
});

add_filter( 'wp_headers', function( $headers ){
	$headers = array_merge( $headers, wp_get_nocache_headers() );
	return $headers;
}, 100 );

add_action( 'wp_enqueue_scripts', function() {
	$path = '/assets/css/style.css';
	$mtime = filemtime( get_stylesheet_directory() . $path );
	wp_enqueue_style( 'solr-demo-redux', get_stylesheet_directory_uri() . $path, false, $mtime );
	wp_enqueue_script( 'jquery' );
});

add_filter( 'query_vars', function( $query_vars ){
	$query_vars = array_merge( $query_vars, sdr_get_book_meta() );
	return $query_vars;
});

add_action( 'pre_get_posts', function( $query ) {
	if ( is_admin() ) {
		return;
	}
	$query->set( 'post_type', 'book' );
	$meta_query = array();
	foreach( sdr_get_book_meta() as $key ) {
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
});

add_filter( 'posts_request', function( $request, $query ){
	$query->sdr_start_time = microtime( true );
	return $request;
}, 1, 2 );

add_filter( 'posts_results', function( $posts, $query ){
	if ( isset( $query->sdr_start_time ) ) {
		$query->sdr_total_time = microtime( true ) - $query->sdr_start_time;
	}
	return $posts;
}, 10, 2 );

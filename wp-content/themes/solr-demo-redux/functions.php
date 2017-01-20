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

add_filter( 'wp_headers', function( $headers ){
	$headers = array_merge( $headers, wp_get_nocache_headers() );
	return $headers;
});

add_action( 'wp_enqueue_scripts', function() {
	$path = '/assets/css/style.css';
	$mtime = filemtime( get_stylesheet_directory() . $path );
	wp_enqueue_style( 'solr-demo-redux', get_stylesheet_directory_uri() . $path, false, $mtime );
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

<?php

/**
 * Import books from JSON file
 *
 * <file>
 * : JSON file to import
 */
WP_CLI::add_command( 'import-books', function( $args ){
	list( $file ) = $args;

	if ( ! file_exists( $file ) ) {
		WP_CLI::error( "File doesn't exist" );
	}

	$f = fopen( $file, 'r' );
	$buffer = '';
	$eol = '},' . PHP_EOL;
	$count = 0;
	while ( ! feof( $f ) ) {
		$buffer .= fread( $f, 8192 );
		$bits = explode( $eol, $buffer );
		$buffer = array_pop( $bits );
		foreach( $bits as $book ) {
			if ( '[' === $book[0] ) {
				$book = substr( $book, 1 );
			}
			$book = json_decode( $book . '}', true );
			if ( $book ) {
				$post_id = wp_insert_post( array(
					'post_title'      => $book['BookMeta_Title'],
					'post_status'     => 'publish',
					'post_type'       => 'book',
				) );
				if ( $post_id ) {
					foreach( sdr_get_book_meta() as $key ) {
						$k = 'BookMeta_' . ucwords( $key );
						if ( isset( $book[ $k ] ) ) {
							update_post_meta( $post_id, $key, $book[ $k ] );
						}
					}
					foreach( sdr_get_book_tax() as $tax ) {
						$k = 'BookMeta_' . ucwords( $tax ) . 's';
						if ( isset( $book[ $k ] ) ) {
							$bits = explode( ';', $book[ $k ] );
							$bits = array_map( 'trim', $bits );
							wp_set_object_terms( $post_id, $bits, $tax );
						}
					}
					$count++;
					echo '.';
				}
			}
		}
	}
	fclose( $f );
	echo PHP_EOL;
	WP_CLI::success( "Imported {$count} books." );
});
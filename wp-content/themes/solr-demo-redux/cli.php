<?php


/**
 * Import movies from a SQL table
 */
WP_CLI::add_command( 'import-movies', function( $args ){
	global $wpdb;

	$limit = 100;
	$offset = get_option( 'sdr_import_offset', 0 );
	if ( $offset ) {
		WP_CLI::log( "Restarting import at offset {$offset}" );
	}
	$count = $wpdb->get_var( "SELECT COUNT(id) FROM plots" );
	$message = 'Importing plots';
	$progress = WP_CLI\Utils\make_progress_bar( $message, $count );
	do {
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM plots LIMIT %d,%d", $offset, $limit ) );
		foreach( $results as $result ) {
			$title = trim( $result->Title );
			if ( '"' === substr( $title, 0, 1 ) && '"' === substr( $title, -1, 1 ) ) {
				$title = substr( $title, 1, strlen( $title ) - 2 );
			}
			$post_id = wp_insert_post( array(
				'post_title'          => $title,
				'post_content'        => trim( $result->Plot ),
				'post_type'           => 'movie',
				'post_status'         => 'publish',
			) );
			if ( $post_id ) {
				update_post_meta( $post_id, 'year', trim( $result->Year ) );
			}
			$progress->tick();
		}
		$offset += $limit;
		update_option( 'sdr_import_offset', $offset );
	} while( count( $results ) );
	$progress->finish();
	delete_option( 'sdr_import_offset' );
	WP_CLI::success( "Import complete." );
});

/**
 * Enrich movie data with OMDB API
 *
 * [<id>...]
 * : Enrich specific post ids.
 *
 * [--all]
 * : Enrich all movies in the database.
 *
 * [--force]
 * : Forcefully enrich movies that have already been enriched.
 *
 * [--paged=<paged>]
 * : Start enrichment at a specific point of pagination.
 */
WP_CLI::add_command( 'enrich-movies', function( $args, $assoc_args ){

	$all = WP_CLI\Utils\get_flag_value( $assoc_args, 'all' );
	if ( ! $all && ! $args ) {
		WP_CLI::error( "Please specify one or more post ids, or use --all" );
	}
	$force = WP_CLI\Utils\get_flag_value( $assoc_args, 'force' );
	$paged = WP_CLI\Utils\get_flag_value( $assoc_args, 'paged', 1 );

	$enrich_movie = function( $post_id ) use ( $force ) {
		$title = html_entity_decode( get_the_title( $post_id ) );
		$post_mention = "'{$title}' ({$post_id})";
		if ( get_post_meta( $post_id, 'enriched', true ) && ! $force ) {
			WP_CLI::log( "{$post_mention} is already enriched." );
			return;
		}
		$request_args = array(
			't'       => rawurlencode( $title ),
			'r'       => 'json',
		);
		if ( $year = get_post_meta( $post_id, 'year', true ) ) {
			$request_args['year'] = $year;
		}
		$start_time = microtime( true );
		$response = wp_remote_get( add_query_arg( $request_args, 'http://www.omdbapi.com/' ) );
		$fetch_time = microtime( true ) - $start_time;
		if ( is_wp_error( $response ) ) {
			WP_CLI::warning( $response->get_error_message() );
			return;
		} elseif ( 200 !== ( $code = wp_remote_retrieve_response_code( $response ) ) ) {
			WP_CLI::warning( "Received {$code} response code - {$post_mention}." );
			return;
		}
		$data = wp_remote_retrieve_body( $response );
		$data = json_decode( $data, true );
		if ( 'False' === $data['Response'] ) {
			WP_CLI::warning( "Failed to retrieve data for {$post_mention}: {$data['Error']}" );
			return;
		}
		if ( $data['Title'] !== $title ) {
			WP_CLI::log( "Response '{$data['Title']}' doesn't match {$post_mention}. Skipping enrichment." );
			return;
		}
		$fields = array(
			'Rated'     => 'rating',
			'Runtime'   => 'runtime',
			'Genre'     => 'genre',
			'Director'  => 'director',
			'Writer'    => 'writer',
			'Actors'    => 'actors',
			'Language'  => 'language',
			'Country'   => 'country',
			'Poster'    => 'poster',
		);
		$added_fields = array();
		$start_time = microtime( true );
		foreach( $fields as $rf => $pf ) {
			if ( 'N/A' === $data[ $rf ] ) {
				continue;
			}
			switch ( $pf ) {
				// case 'genre':
				// case 'language':
				// case 'country':
				// 	$terms = explode( ', ', $data[ $rf ] );
				// 	wp_set_post_terms( $post_id, $terms, $pf );
				// 	break;
				case 'director':
				case 'writer':
				case 'actors':
					delete_post_meta( $post_id, $pf );
					$terms = explode( ', ', $data[ $rf ] );
					foreach( $terms as $term ) {
						add_post_meta( $post_id, $pf, $term );
					}
					break;
				default:
					update_post_meta( $post_id, $pf, $data[ $rf ] );
					break;
			}
			$added_fields[] = $pf;
		}
		$insert_time = microtime( true ) - $start_time;
		$insert_time = round( $insert_time, 3 );
		$fetch_time = round( $fetch_time, 3 );
		$added_fields = $added_fields ? implode( ', ', $added_fields ) : 'None';
		WP_CLI::log( "Enriched {$post_mention} in {$fetch_time}s fetch, {$insert_time}s insert with fields: {$added_fields}" );
		update_post_meta( $post_id, 'enriched', true );
	};

	if ( $all ) {
		do {
			$query = new WP_Query( array(
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'post_type'      => 'movie',
				'posts_per_page' => 200,
				'paged'          => $paged,
			) );
			WP_CLI::log( '' );
			WP_CLI::log( 'Starting page ' . $paged );
			WP_CLI::log( '' );
			foreach( $query->posts as $post ) {
				$enrich_movie( $post->ID );
			}
			$paged++;
			WP_CLI\Utils\wp_clear_object_cache();
		} while( count( $query->posts ) );
	} else {
		$query = new WP_Query( array(
			'post__in'      => array_map( 'intval', $args ),
			'orderby'       => 'post__in',
			'post_type'     => 'movie',
		) );
		foreach( $query->posts as $post ) {
			$enrich_movie( $post->ID );
		}
	}
	WP_CLI::success( "Movie enrichment complete." );
});

/**
 * Prune posts that aren't enriched
 */
WP_CLI::add_command( 'prune-unenriched', function(){
	$unenriched = $total = 0;
	$query = new WP_Query( array(
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'post_type'      => 'movie',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
	foreach( $query->posts as $post_id ) {
		$title = html_entity_decode( get_the_title( $post_id ) );
		$post_mention = "'{$title}' ({$post_id})";
		if ( get_post_meta( $post_id, 'enriched', true ) ) {
			WP_CLI::log( "{$post_mention} is enriched, skipping." );
		} else {
			WP_CLI::log( "Deleting {$post_mention}, which is missing enrichment." );
			wp_delete_post( $post_id, true );
			$unenriched++;
		}
		$total++;
		if ( $total % 200 === 0 ) {
			WP_CLI\Utils\wp_clear_object_cache();
		}
	}
	WP_CLI::success( "Pruned {$unenriched} unenriched movies of {$total} movies" );
});


<?php


/**
 * Import movies from a SQL table
 */
WP_CLI::add_command( 'import-movies', function( $args ){
	global $wpdb;

	$limit = 100;
	$offset = 0;
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
	} while( count( $results ) );
	$progress->finish();
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
 */
WP_CLI::add_command( 'enrich-movies', function( $args, $assoc_args ){

	$all = WP_CLI\Utils\get_flag_value( $assoc_args, 'all' );
	if ( ! $all && ! $args ) {
		WP_CLI::error( "Please specify one or more post ids, or use --all" );
	}
	$force = WP_CLI\Utils\get_flag_value( $assoc_args, 'force' );

	$enrich_movie = function( $post_id ) use ( $force ) {
		$title = get_the_title( $post_id );
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
		$response = wp_remote_get( add_query_arg( $request_args, 'http://www.omdbapi.com/' ) );
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
		$added_fields = 0;
		foreach( $fields as $rf => $pf ) {
			if ( 'N/A' === $data[ $rf ] ) {
				continue;
			}
			switch ( $pf ) {
				case 'genre':
				case 'language':
				case 'country':
					$terms = explode( ', ', $data[ $rf ] );
					wp_set_post_terms( $post_id, $terms, $pf );
					break;
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
			$added_fields++;
		}
		WP_CLI::log( "Enriched post '{$title}' with {$added_fields} fields." );
		update_post_meta( $post_id, 'enriched', true );
	};

	if ( $all ) {
		$paged = 1;
		do {
			$query = new WP_Query( array(
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'post_type'      => 'movie',
				'posts_per_page' => 200,
				'paged'          => $paged,
			) );
			foreach( $query->posts as $post ) {
				$enrich_movie( $post->ID );
			}
			$paged++;
			wp_clear_object_cache();
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

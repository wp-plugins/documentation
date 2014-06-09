<?php
/**
 * class-documentation-search.php
 *
 * Copyright (c) 2014 "kento" Karim Rahimpur www.itthinx.com
 *
 * This code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 *
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This header and all notices must be kept intact.
 *
 * @author Karim Rahimpur
 * @package documentation
 * @since 1.1.0
 */


/**
 * Documentation search facilities.
 */
class Documentation_Search {

	const SEARCH_TOKEN  = 'document-search';
	const SEARCH_QUERY  = 'document-query';
	const LIMIT         = 'limit';
	const DEFAULT_LIMIT = 10;
	const TITLE         = 'title';
	const EXCERPT       = 'excerpt';
	const CONTENT       = 'content';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'wp_init' ) );
		add_shortcode( 'documentation_search_form', array( __CLASS__, 'documentation_search_form' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'wp_enqueue_scripts' ) );
// 		add_action( 'wp_ajax_document_search', array( __CLASS__, 'ajax' ) );
// 		add_action( 'wp_ajax_nopriv_document_Search', array( __CLASS__, 'ajax' ) );

	}

	public static function wp_enqueue_scripts() {
		wp_register_script( 'typewatch', DOCUMENTATION_PLUGIN_URL . 'js/jquery.typewatch.js', array( 'jquery' ), DOCUMENTATION_CORE_VERSION, true );
		wp_register_script( 'document-search', DOCUMENTATION_PLUGIN_URL . 'js/document-search.js', array( 'jquery', 'typewatch' ), DOCUMENTATION_CORE_VERSION, true );
		wp_register_style( 'document-search', DOCUMENTATION_PLUGIN_URL . 'css/document-search.css', array(), DOCUMENTATION_CORE_VERSION );
	}

	/**
	 * Handles a search request and renders results as JSON encoded string.
	 * @todo add order options
	 */
	public static function wp_init() {

		if ( isset( $_REQUEST[self::SEARCH_TOKEN] ) && !empty( $_REQUEST[self::SEARCH_QUERY] ) ) {

			global $wpdb;

			$title       = !empty( $_REQUEST[self::TITLE] ) ? intval( $_REQUEST[self::TITLE] ) > 0 : true;
			$excerpt     = !empty( $_REQUEST[self::EXCERPT] ) ? intval( $_REQUEST[self::EXCERPT] ) > 0 : false;
			$content     = !empty( $_REQUEST[self::CONTENT] ) ? intval( $_REQUEST[self::CONTENT] ) > 0 : false;
			$limit       = !empty( $_REQUEST[self::LIMIT] ) ? intval( $_REQUEST[self::LIMIT] ) : self::DEFAULT_LIMIT;
			$numberposts = intval( apply_filters( 'documentation_search_limit', $limit ) );

			if ( !$title && !$excerpt && !$content ) {
				$title = true;
			}

			// We are using prepare, just apply like_escape ...
			$like = '%' . like_escape( $_REQUEST[self::SEARCH_QUERY] ) . '%';
			// ... otherwise (if not using prepare) we must:
			// $like = '%' . esc_sql( like_escape( $_REQUEST[self::SEARCH_QUERY] ) ) . '%';

			$args   = array();
			$params = array();
			if ( $title ) {
				$args[] = ' post_title LIKE %s ';
				$params[] = $like;
			}
			if ( $excerpt ) {
				$args[] = ' post_excerpt LIKE %s ';
				$params[] = $like;
			}
			if ( $content ) {
				$args[] = ' post_content LIKE %s ';
				$params[] = $like;
			}

			$query = $wpdb->prepare(
				sprintf( "SELECT ID FROM $wpdb->posts WHERE post_type = 'document' AND post_status = 'publish' AND ( %s )", implode( ' OR ', $args ) ),
				$params
			);

			// Obtain a preliminary result set. This is not limited.
			$results = $wpdb->get_results( $query );

			$include = array();
			if ( !empty( $results ) && is_array( $results ) ) {
				foreach ( $results as $result ) {
					$include[] = $result->ID;
				}
			}

			$results = array();
			$post_ids = array();
			if ( count( $include ) > 0 ) {
				// Run it through get_posts() so that the normal process for obtaining
				// posts and taking account filters etc can be applied.
				$posts = get_posts( array(
					'post_type' => 'document',
					'post_status' => 'publish',
					'include' => $include,
					'numberposts' => $numberposts
				) );
				foreach( $posts as $post ) {
					$post_ids[] = $post->ID;

					$results[$post->ID] = array(
						'id'    => $post->ID,
						'url'   => get_permalink( $post->ID ),
						'title' => get_the_title( $post )
					);
				}
			}

			echo json_encode( $results );
			exit;
		}
	}

	/**
	 * Shortcode handler, renders a documentation search form.
	 * 
	 * Enqueues required scripts and styles.
	 * 
	 * @param array $args none accepted
	 * @param array $content not used
	 * @return string form HTML
	 */
	public static function documentation_search_form( $args = array(), $content = '' ) {

		wp_enqueue_script( 'typewatch' );
		wp_enqueue_script( 'document-search' );
		wp_enqueue_style( 'document-search' );

		$output = '';

		$documentation_search = true;

		$n          = rand();
		$search_id  = 'documentation-search-' . $n;
		$field_id   = 'documentation-search-field-' . $n;
		$results_id = 'documentation-search-results-' .$n;

		$output .= sprintf( '<div id="%s" class="documentation-search">', esc_attr( $search_id ) );

		$output .= '<div class="documentation-search-form">';
		$output .= '<form action="">';
		$output .= '<div>';
		$output .= sprintf(
			'<input id="%s" type="text" class="documentation-search-field" />',
			esc_attr( $field_id )
		);
		$output .= '</div>';
		$output .= '</form>';
		$output .= '</div>'; // .documentation-search-form

		$output .= '<div class="documentation-search-results">';
		$output .= '</div>'; // .documentation-search-results

		$output .= '</div>'; // .documentation-search

		$output .= '<script type="text/javascript">';
		$output .= 'if ( typeof jQuery !== "undefined" ) {';
		$output .= 'jQuery(document).ready(function(){';
		$output .= sprintf(
			'jQuery("#%s").typeWatch( {
				callback: function (value) { documentationSearch(\'%s\', \'%s\', \'%s\', \'%s\', value); },
				wait: 750,
				highlight: true,
				captureLength: 2
			} );',
			esc_attr( $field_id ), // jQuery selector for the input field
			esc_attr( $field_id ), // jQuery selector for the input field passed to documentationSearch()
			esc_attr( $search_id ), // container selector
			esc_attr( $search_id . ' div.documentation-search-results' ), // results container selector
			esc_js( admin_url( 'admin-ajax.php' ) ) // post target URL
		);
		$output .= '});'; // ready
		$output .= '}'; // if
		$output .= '</script>';

		return $output;
	}

}
Documentation_Search::init();

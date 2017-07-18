<?php


namespace Phormig\Migrate;


class Migrate {
	private $settings;


	/**
	 * Migrate constructor.
	 */
	public function __construct( $settings ) {

		$this->settings = $settings;
	}


	public function migrate() {

		// Don't migrate on ajax
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return false;
		}

		// Make sure our post types exist
		if ( ! post_type_exists( $this->settings['post_type'] ) || ! post_type_exists( 'phort_post' ) ) {
			return false;
		}


		$this->migrate_portfolio_post_type();
		$this->migrate_portfolio_categories();
		$this->migrate_menu();


		// Disable the old Portfolio Post Type
		deactivate_plugins( $this->settings['plugin'] );

		// Double refresh rewrite rules
		flush_rewrite_rules( true );
		flush_rewrite_rules( true );

		return true;
	}


	/**
	 * Migrate Post Type `portfolio` to `phort_post`
	 *
	 * Cannot do this with a DB Query because it might mess up meta configuration.
	 * Instead, get all posts and update their post type individually.
	 */
	function migrate_portfolio_post_type() {

		$posts = get_posts(
			[
				'post_type'   => $this->settings['post_type'],
				'numberposts' => - 1,
			]
		);

		foreach ( $posts as $post ) {
			if ( $post->post_type === $this->settings['post_type'] ) {

				// Migrate Post Meta
				$this->migrate_post_meta( $post );

				// Migrate Post Type
				$post->post_type = 'phort_post';
				wp_update_post( $post );


			}

		}


	}


	public function migrate_portfolio_categories() {


		global $wpdb;

		$wpdb->update( 'wp_term_taxonomy', [ 'taxonomy' => 'phort_post_category' ], [ 'taxonomy' => $this->settings['taxonomy'] ] );


	}


	public function migrate_menu() {

		global $wpdb;


		// Rename from `portfolio_category` to `phort_post_category`
		$wpdb->update(
			'wp_postmeta',
			[ 'meta_value' => 'phort_post_category' ],
			[ 'meta_value' => $this->settings['taxonomy'] ]
		);

		// Rename from `portfolio` to `phort_post`
		$wpdb->update(
			'wp_postmeta',
			[ 'meta_value' => 'phort_post' ],
			[ 'meta_value' => $this->settings['post_type'] ]
		);


	}


	public function migrate_post_meta( $post ) {

		$image_ids = get_post_meta( $post->ID, $this->settings['gallery_key'], true );

		/**
		 * Convert to Photography Portfolio Format
		 */
		$images = [];
		foreach ( $image_ids as $id ) {
			$images[ $id ] = wp_get_attachment_image_url( $id, 'full' );
		}

		update_post_meta( $post->ID, 'phort_gallery', $images );
	}

}
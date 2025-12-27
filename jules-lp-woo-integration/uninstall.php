<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package   LearnPress_WooCommerce_Integration
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if the user has opted to clean up data on uninstall.
$cleanup = get_option( 'jlwi_cleanup_on_uninstall' );

if ( $cleanup ) {
	// Get all LearnPress courses.
	$args = array(
		'post_type'      => 'lp_course',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	);

	$courses_query = new WP_Query( $args );
	$course_ids    = $courses_query->posts;

	// If there are courses, loop through them and delete the associated products.
	if ( ! empty( $course_ids ) ) {
		foreach ( $course_ids as $course_id ) {
			$product_id = get_post_meta( $course_id, '_jlwi_product_id', true );

			if ( $product_id ) {
				// true to permanently delete, false to move to trash.
				wp_delete_post( $product_id, true );
			}

			// Delete the meta key from the course as well.
			delete_post_meta( $course_id, '_jlwi_product_id' );
		}
	}

	// Delete the cleanup option itself.
	delete_option( 'jlwi_cleanup_on_uninstall' );
}

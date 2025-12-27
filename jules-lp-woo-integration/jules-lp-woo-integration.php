<?php
/**
 * Plugin Name:       Jules' LearnPress WooCommerce Integration
 * Plugin URI:        https://example.com/
 * Description:       A custom integration for LearnPress and WooCommerce to allow courses to be sold as products.
 * Version:           1.0.0
 * Author:            A.Babaei
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jules-lp-woo-integration
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Run a bulk sync once on plugin activation.
 * This ensures all existing courses are synced without needing manual intervention.
 */
function jlwi_activate_plugin() {
	// Check if the initial sync has already been done to prevent re-running on every activation.
	if ( ! get_option( 'jlwi_initial_sync_done' ) ) {
		$args = array(
			'post_type'      => 'lp_course',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$courses_query = new WP_Query( $args );
		$course_ids    = $courses_query->posts;

		if ( ! empty( $course_ids ) ) {
			foreach ( $course_ids as $course_id ) {
				jlwi_sync_course_to_product( $course_id );
			}
		}

		// Set a flag so this doesn't run again.
		update_option( 'jlwi_initial_sync_done', true );
	}
}
register_activation_hook( __FILE__, 'jlwi_activate_plugin' );

/**
 * Clean up the initial sync flag on deactivation.
 * This allows the automatic sync to run again if the plugin is reactivated.
 */
function jlwi_deactivate_plugin() {
	delete_option( 'jlwi_initial_sync_done' );
}
register_deactivation_hook( __FILE__, 'jlwi_deactivate_plugin' );

/**
 * Core function to sync a LearnPress course to a WooCommerce product.
 *
 * @param int $course_id The ID of the LearnPress course.
 */
function jlwi_sync_course_to_product( $course_id ) {
	// Get the course post object.
	$course = get_post( $course_id );

	// If the course doesn't exist or is not a course, do nothing.
	if ( ! $course || 'lp_course' !== $course->post_type ) {
		return;
	}

	// Get the linked product ID from the course meta.
	$product_id = get_post_meta( $course_id, '_jlwi_product_id', true );

	// Check if the product exists and is a valid product.
	$product = $product_id ? wc_get_product( $product_id ) : false;

	// If the product doesn't exist, create a new one.
	if ( ! $product ) {
		$product = new WC_Product_Simple();
	}

	// Sync the product data from the course.
	$product->set_name( $course->post_title );
	$product->set_description( $course->post_content );
	$product->set_short_description( $course->post_excerpt ); // Or use post_excerpt for short description

	// LearnPress course price.
	$course_data = learn_press_get_course( $course_id );
	if ( $course_data ) {
		$price = $course_data->get_price();
		$product->set_regular_price( $price );
		$product->set_price( $price );
	}

	// Set product to be virtual.
	$product->set_virtual( true );

	// Set product visibility to hidden.
	$product->set_catalog_visibility( 'hidden' );

	// Sync the featured image.
	$thumbnail_id = get_post_thumbnail_id( $course_id );
	if ( $thumbnail_id ) {
		$product->set_image_id( $thumbnail_id );
	}

	// Save the product data.
	$product_id = $product->save();

	// If the product was saved successfully, update the post meta.
	if ( $product_id ) {
		update_post_meta( $course_id, '_jlwi_product_id', $product_id );
		update_post_meta( $product_id, '_jlwi_course_id', $course_id );
	}
}

/**
 * Automatically sync the course when it is saved.
 *
 * @param int $course_id The ID of the course being saved.
 */
function jlwi_trigger_sync_on_save( $course_id ) {
	// If this is an autosave, our form has not been submitted, so we don't want to do anything.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Check the user's permissions.
	if ( ! current_user_can( 'edit_post', $course_id ) ) {
		return;
	}

	// Check if sync is enabled for this course.
	$sync_enabled = get_post_meta( $course_id, '_jlwi_sync_enabled', true );

	// Default to 'yes' for newly created courses if the meta field doesn't exist yet.
	if ( '' === $sync_enabled ) {
		$sync_enabled = 'yes';
		update_post_meta( $course_id, '_jlwi_sync_enabled', 'yes' );
	}

	if ( 'yes' === $sync_enabled ) {
		// Sync the course to the product.
		jlwi_sync_course_to_product( $course_id );
	}
}
add_action( 'save_post_lp_course', 'jlwi_trigger_sync_on_save' );

/**
 * Trash the associated product when a course is deleted.
 *
 * @param int $post_id The ID of the post being deleted.
 */
function jlwi_trash_product_on_course_delete( $post_id ) {
	// Check if the post being deleted is a LearnPress course.
	if ( 'lp_course' !== get_post_type( $post_id ) ) {
		return;
	}

	// Get the linked product ID.
	$product_id = get_post_meta( $post_id, '_jlwi_product_id', true );

	// If a product is linked, trash it.
	if ( $product_id ) {
		wp_trash_post( $product_id );
	}
}
add_action( 'before_delete_post', 'jlwi_trash_product_on_course_delete' );

/**
 * Enroll the user in the course when the order is completed.
 *
 * @param int $order_id The ID of the WooCommerce order.
 */
function jlwi_enroll_user_on_purchase( $order_id ) {
	// Get the order object.
	$order = wc_get_order( $order_id );

	// If the order doesn't exist, do nothing.
	if ( ! $order ) {
		return;
	}

	// Get the user ID from the order.
	$user_id = $order->get_user_id();

	// If there is no user associated with the order, do nothing.
	if ( ! $user_id ) {
		return;
	}

	// Loop through the order items.
	foreach ( $order->get_items() as $item ) {
		// Get the product ID.
		$product_id = $item->get_product_id();

		// Get the linked course ID from the product meta.
		$course_id = get_post_meta( $product_id, '_jlwi_course_id', true );

		// If a course is linked, enroll the user.
		if ( $course_id ) {
			learn_press_enroll_user_course( $user_id, $course_id );
		}
	}
}
add_action( 'woocommerce_order_status_completed', 'jlwi_enroll_user_on_purchase' );

/**
 * Redirect the "Take this Course" button to the WooCommerce checkout.
 *
 * @param string $url The original URL of the button.
 * @param int    $course_id The ID of the course.
 * @return string The modified URL.
 */
function jlwi_redirect_take_course_button( $url, $course_id ) {
	// Get the linked product ID.
	$product_id = get_post_meta( $course_id, '_jlwi_product_id', true );

	// If a product is linked, change the URL to the WooCommerce add-to-cart URL.
	if ( $product_id ) {
		$url = wc_get_checkout_url() . '?add-to-cart=' . $product_id;
	}

	return $url;
}
add_filter( 'learn-press/course-add-to-cart-redirect-url', 'jlwi_redirect_take_course_button', 10, 2 );

/**
 * Add LearnPress courses to the WooCommerce coupon product search.
 *
 * @param array $products The array of found products.
 * @return array The modified array of found products.
 */
function jlwi_add_courses_to_coupon_search( $products ) {
	$search_term = isset( $_GET['term'] ) ? wc_clean( wp_unslash( $_GET['term'] ) ) : '';

	if ( empty( $search_term ) ) {
		return $products;
	}

	$args = array(
		'post_type'      => 'lp_course',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		's'              => $search_term,
	);

	$courses_query = new WP_Query( $args );

	if ( $courses_query->have_posts() ) {
		while ( $courses_query->have_posts() ) {
			$courses_query->the_post();
			$course_id = get_the_ID();
			$product_id = get_post_meta( $course_id, '_jlwi_product_id', true );

			if ( $product_id && ! isset( $products[ $product_id ] ) ) {
				$product = wc_get_product( $product_id );
				if ( is_object( $product ) ) {
					// Prepending [Course] to make it clear in the search results
					$products[ $product_id ] = '[Course] ' . $product->get_formatted_name();
				}
			}
		}
		wp_reset_postdata();
	}

	return $products;
}
add_filter( 'woocommerce_json_search_found_products', 'jlwi_add_courses_to_coupon_search' );

/**
 * Include the admin page logic.
 */
require_once plugin_dir_path( __FILE__ ) . 'admin/admin-page.php';

/**
 * Add the top-level admin menu page.
 */
function jlwi_add_main_admin_menu() {
	add_menu_page(
		__( 'LP Woo Integration', 'jules-lp-woo-integration' ),
		__( 'LP Woo Integration', 'jules-lp-woo-integration' ),
		'manage_woocommerce', // A capability that store managers and admins have.
		'jules-lp-woo-integration',
		'jlwi_render_admin_page',
		'dashicons-admin-links',
		30
	);
}
add_action( 'admin_menu', 'jlwi_add_main_admin_menu' );

/**
 * Register the settings for the cleanup option.
 */
function jlwi_register_settings() {
	register_setting( 'jlwi_settings_group', 'jlwi_cleanup_on_uninstall' );
}
add_action( 'admin_init', 'jlwi_register_settings' );

/**
 * Enqueue admin scripts for the settings page.
 *
 * @param string $hook The current admin page hook.
 */
function jlwi_enqueue_admin_scripts( $hook ) {
	// Only load scripts on our plugin's settings page.
	if ( 'learnpress_page_jules-lp-woo-integration' !== $hook ) {
		return;
	}

    wp_enqueue_script(
        'jlwi-selective-sync',
        plugin_dir_url( __FILE__ ) . 'admin/selective-sync.js',
        array( 'jquery' ),
        '1.0.0',
        true
    );
    wp_localize_script(
        'jlwi-selective-sync',
        'jlwi_ajax_vars',
        array(
            'nonce' => wp_create_nonce( 'jlwi_ajax_nonce' ),
        )
    );
}
add_action( 'admin_enqueue_scripts', 'jlwi_enqueue_admin_scripts' );

/**
 * AJAX handler for syncing selected courses.
 */
function jlwi_sync_selected_ajax() {
    check_ajax_referer( 'jlwi_ajax_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'Permission denied.' ) );
    }

    $course_ids = isset( $_POST['course_ids'] ) ? array_map( 'intval', $_POST['course_ids'] ) : array();

    if ( empty( $course_ids ) ) {
        wp_send_json_error( array( 'message' => 'No courses selected.' ) );
    }

    $synced_count = 0;
    foreach ( $course_ids as $course_id ) {
        jlwi_sync_course_to_product( $course_id );
        update_post_meta( $course_id, '_jlwi_sync_enabled', 'yes' );
        $synced_count++;
    }

    wp_send_json_success( array( 'message' => sprintf( '%d courses synced successfully.', $synced_count ) ) );
}
add_action( 'wp_ajax_jlwi_sync_selected', 'jlwi_sync_selected_ajax' );

/**
 * AJAX handler for unsyncing selected courses.
 */
function jlwi_unsync_selected_ajax() {
    check_ajax_referer( 'jlwi_ajax_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => 'Permission denied.' ) );
    }

    $course_ids = isset( $_POST['course_ids'] ) ? array_map( 'intval', $_POST['course_ids'] ) : array();

    if ( empty( $course_ids ) ) {
        wp_send_json_error( array( 'message' => 'No courses selected.' ) );
    }

    $unsynced_count = 0;
    foreach ( $course_ids as $course_id ) {
        $product_id = get_post_meta( $course_id, '_jlwi_product_id', true );
        if ( $product_id ) {
            wp_trash_post( $product_id ); // Move product to trash
        }
        update_post_meta( $course_id, '_jlwi_sync_enabled', 'no' );
        $unsynced_count++;
    }

    wp_send_json_success( array( 'message' => sprintf( '%d courses unsynced successfully.', $unsynced_count ) ) );
}
add_action( 'wp_ajax_jlwi_unsync_selected', 'jlwi_unsync_selected_ajax' );

/**
 * AJAX handler to start the synchronization process.
 * Gathers all course IDs to be processed.
 */
function jlwi_start_sync_ajax() {
	check_ajax_referer( 'jlwi_sync_nonce', 'nonce' );

	$args = array(
		'post_type'      => 'lp_course',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	);
	$courses_query = new WP_Query( $args );
	$course_ids    = $courses_query->posts;

	if ( empty( $course_ids ) ) {
		wp_send_json_error( array( 'message' => 'No courses found to sync.' ) );
	}

	wp_send_json_success( array( 'courses' => $course_ids ) );
}
add_action( 'wp_ajax_jlwi_start_sync', 'jlwi_start_sync_ajax' );

/**
 * AJAX handler to process a single batch of courses.
 */
function jlwi_process_batch_ajax() {
	check_ajax_referer( 'jlwi_sync_nonce', 'nonce' );

	if ( ! isset( $_POST['courses'] ) || ! is_array( $_POST['courses'] ) ) {
		wp_send_json_error( array( 'message' => 'Invalid course data provided.' ) );
	}

	$courses_batch = array_map( 'intval', $_POST['courses'] );
	$processed_count = 0;

	foreach ( $courses_batch as $course_id ) {
		if ( $course_id > 0 ) {
			jlwi_sync_course_to_product( $course_id );
			$processed_count++;
		}
	}

	wp_send_json_success( array( 'processed_count' => $processed_count ) );
}
add_action( 'wp_ajax_jlwi_process_batch', 'jlwi_process_batch_ajax' );

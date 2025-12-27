<?php
/**
 * Plugin Name:       Jules' LearnPress WooCommerce Integration
 * Plugin URI:        https://example.com/
 * Description:       A custom integration for LearnPress and WooCommerce to allow courses to be sold as products.
 * Version:           1.0.0
 * Author:            Jules
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

	// Sync the course to the product.
	jlwi_sync_course_to_product( $course_id );
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
 * Add the admin menu page.
 */
function jlwi_add_admin_menu() {
	add_submenu_page(
		'learn_press',
		__( 'WooCommerce Integration', 'jules-lp-woo-integration' ),
		__( 'Woo Integration', 'jules-lp-woo-integration' ),
		'manage_options',
		'jules-lp-woo-integration',
		'jlwi_render_settings_page'
	);
}
add_action( 'admin_menu', 'jlwi_add_admin_menu' );

/**
 * Render the admin settings page.
 */
function jlwi_render_settings_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'LearnPress WooCommerce Integration', 'jules-lp-woo-integration' ); ?></h1>
		<p><?php esc_html_e( 'Use the tools below to manage the integration between LearnPress and WooCommerce.', 'jules-lp-woo-integration' ); ?></p>

		<form method="post" action="">
			<h2><?php esc_html_e( 'Manual Synchronization', 'jules-lp-woo-integration' ); ?></h2>
			<p><?php esc_html_e( 'Click the button below to manually sync all existing LearnPress courses to WooCommerce products. This is useful for initial setup or if you suspect some courses are out of sync.', 'jules-lp-woo-integration' ); ?></p>
			<?php wp_nonce_field( 'jlwi_bulk_sync_nonce', 'jlwi_bulk_sync_nonce_field' ); ?>
			<p>
				<button type="submit" name="jlwi_bulk_sync" class="button button-primary">
					<?php esc_html_e( 'Bulk Sync Courses', 'jules-lp-woo-integration' ); ?>
				</button>
			</p>
		</form>

		<hr>

		<form method="post" action="options.php">
			<?php settings_fields( 'jlwi_settings_group' ); ?>
			<?php do_settings_sections( 'jlwi-settings-section' ); ?>
			<h2><?php esc_html_e( 'Uninstall Settings', 'jules-lp-woo-integration' ); ?></h2>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Cleanup on Uninstall', 'jules-lp-woo-integration' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="jlwi_cleanup_on_uninstall" value="1" <?php checked( get_option( 'jlwi_cleanup_on_uninstall' ), 1 ); ?> />
							<?php esc_html_e( 'Enable this to remove all associated WooCommerce products and plugin data when the plugin is deleted.', 'jules-lp-woo-integration' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'This action is irreversible.', 'jules-lp-woo-integration' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Register the settings for the cleanup option.
 */
function jlwi_register_settings() {
	register_setting( 'jlwi_settings_group', 'jlwi_cleanup_on_uninstall' );
}
add_action( 'admin_init', 'jlwi_register_settings' );

/**
 * Handle the bulk sync request.
 */
function jlwi_handle_bulk_sync() {
	if ( isset( $_POST['jlwi_bulk_sync'] ) && isset( $_POST['jlwi_bulk_sync_nonce_field'] ) && wp_verify_nonce( $_POST['jlwi_bulk_sync_nonce_field'], 'jlwi_bulk_sync_nonce' ) ) {
		$args = array(
			'post_type'      => 'lp_course',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$courses_query = new WP_Query( $args );
		$course_ids = $courses_query->posts;
		$synced_count = 0;

		if ( ! empty( $course_ids ) ) {
			foreach ( $course_ids as $course_id ) {
				jlwi_sync_course_to_product( $course_id );
				$synced_count++;
			}
		}

		add_action( 'admin_notices', function() use ( $synced_count ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php printf( esc_html__( 'Successfully synced %d courses.', 'jules-lp-woo-integration' ), intval( $synced_count ) ); ?></p>
			</div>
			<?php
		} );
	}
}
add_action( 'admin_init', 'jlwi_handle_bulk_sync' );

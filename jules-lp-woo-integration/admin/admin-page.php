<?php
/**
 * Admin settings page for the LearnPress WooCommerce Integration plugin.
 *
 * @package Jules_LP_Woo_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Renders the admin settings page.
 */
function jlwi_render_admin_page() {
    // Get the selected category filter
    $selected_category = isset( $_GET['course_category'] ) ? sanitize_text_field( $_GET['course_category'] ) : '';

    // Prepare arguments for WP_Query
    $args = array(
        'post_type'      => 'lp_course',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    );

    // Add taxonomy query if a category is selected
    if ( ! empty( $selected_category ) ) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'course_category',
                'field'    => 'slug',
                'terms'    => $selected_category,
            ),
        );
    }

    $courses_query = new WP_Query( $args );

    // Get all course categories for the filter dropdown
    $course_categories = get_terms( array(
        'taxonomy'   => 'course_category',
        'hide_empty' => true,
    ) );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'LearnPress WooCommerce Integration', 'jules-lp-woo-integration' ); ?></h1>
        <p><?php esc_html_e( 'Manage the synchronization of LearnPress courses to WooCommerce products.', 'jules-lp-woo-integration' ); ?></p>

        <div id="jlwi-feedback-message" class="notice" style="display:none;"></div>

        <div class="jlwi-controls">
            <form method="get">
                <input type="hidden" name="page" value="jules-lp-woo-integration" />
                <select name="course_category" id="course_category_filter">
                    <option value=""><?php esc_html_e( 'All Categories', 'jules-lp-woo-integration' ); ?></option>
                    <?php if ( ! is_wp_error( $course_categories ) && ! empty( $course_categories ) ) : ?>
                        <?php foreach ( $course_categories as $category ) : ?>
                            <option value="<?php echo esc_attr( $category->slug ); ?>" <?php selected( $selected_category, $category->slug ); ?>>
                                <?php echo esc_html( $category->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'jules-lp-woo-integration' ); ?>">
            </form>

            <div class="jlwi-bulk-actions">
                <button id="jlwi-sync-selected" class="button button-primary"><?php esc_html_e( 'Sync Selected Courses', 'jules-lp-woo-integration' ); ?></button>
                <button id="jlwi-unsync-selected" class="button"><?php esc_html_e( 'Unsync Selected Courses', 'jules-lp-woo-integration' ); ?></button>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <input id="cb-select-all-1" type="checkbox">
                    </td>
                    <th class="manage-column"><?php esc_html_e( 'Course Title', 'jules-lp-woo-integration' ); ?></th>
                    <th class="manage-column"><?php esc_html_e( 'Category', 'jules-lp-woo-integration' ); ?></th>
                    <th class="manage-column"><?php esc_html_e( 'Price', 'jules-lp-woo-integration' ); ?></th>
                    <th class="manage-column"><?php esc_html_e( 'Sync Status', 'jules-lp-woo-integration' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $courses_query->have_posts() ) : ?>
                    <?php while ( $courses_query->have_posts() ) : $courses_query->the_post(); ?>
                        <?php
                        $course_id = get_the_ID();
                        $course = learn_press_get_course( $course_id );
                        $price = $course ? $course->get_price_html() : __( 'N/A', 'jules-lp-woo-integration' );
                        $product_id = get_post_meta( $course_id, '_jlwi_product_id', true );
                        $sync_status = ( $product_id && get_post_type( $product_id ) === 'product' )
                            ? '<span style="color:green;">' . __( 'Synced', 'jules-lp-woo-integration' ) . '</span>'
                            : '<span style="color:red;">' . __( 'Not Synced', 'jules-lp-woo-integration' ) . '</span>';
                        $categories = get_the_terms( $course_id, 'course_category' );
                        $category_names = ! is_wp_error( $categories ) && ! empty( $categories ) ? wp_list_pluck( $categories, 'name' ) : array();
                        ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="course_ids[]" value="<?php echo esc_attr( $course_id ); ?>">
                            </th>
                            <td><?php the_title(); ?></td>
                            <td><?php echo esc_html( implode( ', ', $category_names ) ); ?></td>
                            <td><?php echo wp_kses_post( $price ); ?></td>
                            <td><?php echo wp_kses_post( $sync_status ); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <?php wp_reset_postdata(); ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e( 'No courses found.', 'jules-lp-woo-integration' ); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

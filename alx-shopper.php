<?php
/**
 * Plugin Name: ALEEX Shopper
 * Description: A customizable product search plugin for WooCommerce, featuring user-friendly front-end dropdowns and analytics capabilities.
 * Version: 1.0
 * Author: poopoo23
 * License: GPL2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'ALX_SHOPPER_VERSION', '1.0' );
define( 'ALX_SHOPPER_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALX_SHOPPER_URL', plugin_dir_url( __FILE__ ) );

// Include necessary files
require_once ALX_SHOPPER_DIR . 'includes/class-alx-shopper-frontend.php';
require_once ALX_SHOPPER_DIR . 'includes/class-alx-shopper-analytics.php';
require_once ALX_SHOPPER_DIR . 'includes/class-alx-shopper-search.php';
new Alx_Shopper_Search();



add_action('admin_menu', 'alx_shopper_add_admin_menu');
function alx_shopper_add_admin_menu() {
    add_menu_page(
        'A[LEE]X Shopper',
        'A[LEE]X Shopper',
        'manage_options',
        'alx-shopper',
        'alx_shopper_dashboard_page',
        'data:image/svg+xml;base64,' . base64_encode('
            <svg width="20" height="20" viewBox="0 0 20 20" fill="orange" xmlns="http://www.w3.org/2000/svg">
                <circle cx="10" cy="10" r="10" fill="orange"/>
                <text x="50%" y="55%" text-anchor="middle" fill="white" font-size="10" font-family="Arial" dy=".3em">A</text>
            </svg>
        '),
        2
    );
    add_submenu_page(
        'alx-shopper',
        'A[LEE]X Shopper Settings',
        'Settings',
        'manage_options',
        'alx-shopper-settings',
        'alx_shopper_settings_page'
    );
}

function alx_shopper_dashboard_page() {
    echo '<div class="wrap"><h1>A[LEE]X Shopper Dashboard</h1><p>Welcome to the A[LEE]X Shopper plugin!</p></div>';
}

// Settings page callback
function alx_shopper_settings_page() {
    // Get all options
    $num = intval(get_option('alx_shopper_num_dropdowns', 2));
    $titles = get_option('alx_shopper_dropdown_titles', []);
    $mapping = get_option('alx_shopper_dropdown_attributes', []);
    $values = get_option('alx_shopper_dropdown_values', []);
    $orders = get_option('alx_shopper_dropdown_value_order', []);
    $attribute_taxonomies = function_exists('wc_get_attribute_taxonomies') ? wc_get_attribute_taxonomies() : [];

    ?>
    <div class="wrap">
        <h1>A[LEE]X Shopper Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('alx_shopper_settings_group'); ?>
            <table class="form-table">
                
                <tr>
                    <th scope="row">Product Categories for Search</th>
                    <td>
                        <?php alx_shopper_categories_callback(); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Number of Dropdowns (2-5)</th>
                    <td>
                        <input type="number" min="2" max="5" name="alx_shopper_num_dropdowns" value="<?php echo esc_attr($num); ?>" />
                        <p class="description">Change this to instantly update the number of dropdowns below.</p>
                    </td>
                </tr>
            </table>
            <hr>
            <h2>Dropdown Filters</h2>
            <?php
            // Always render 5, hide extra with JS
            for ($i = 0; $i < 5; $i++) {
                $row_style = ($i >= $num) ? 'display:none;' : '';
                echo '<div class="alx-dynamic-row" data-index="'.$i.'" style="margin-bottom:30px;'.$row_style.'">';
                echo '<h3>Dropdown '.($i+1).'</h3>';

                // Title
                $val = isset($titles[$i]) ? esc_attr($titles[$i]) : '';
                echo '<label>Title: </label>';
                echo '<input type="text" name="alx_shopper_dropdown_titles['.$i.']" value="'.$val.'" placeholder="Dropdown '.($i+1).' Title" style="width:250px;" /><br><br>';

                // Attribute
                echo '<label>Attribute: </label>';
                echo '<select class="alx-dropdown-attribute" name="alx_shopper_dropdown_attributes['.$i.']">';
                echo '<option value="">-- Select Attribute --</option>';
                foreach ($attribute_taxonomies as $tax) {
                    $attr_name = wc_attribute_taxonomy_name($tax->attribute_name);
                    $selected = (isset($mapping[$i]) && $mapping[$i] === $attr_name) ? 'selected' : '';
                    echo "<option value='{$attr_name}' {$selected}>".esc_html($tax->attribute_label)."</option>";
                }
                echo '</select><br><br>';

                // Values
                echo '<label>Values:</label><br>';
                $attr = isset($mapping[$i]) ? $mapping[$i] : '';
                $selected_values = isset($values[$i]) ? (array)$values[$i] : [];
                if ($attr) {
                    $terms = get_terms([
                        'taxonomy' => $attr,
                        'hide_empty' => false,
                    ]);
                    if (!empty($terms) && !is_wp_error($terms)) {
                        echo '<select class="alx-dropdown-values" name="alx_shopper_dropdown_values['.$i.'][]" multiple style="min-width:250px; height:100px;">';
                        foreach ($terms as $term) {
                            $sel = in_array($term->term_id, $selected_values) ? 'selected' : '';
                            echo "<option value='{$term->term_id}' {$sel}>{$term->name}</option>";
                        }
                        echo '</select>';
                    } else {
                        echo '<em>No terms found for this attribute.</em>';
                    }
                } else {
                    echo '<em>No attribute selected.</em>';
                }
                echo '<br><br>';

                // Order (drag-and-drop)
                $orders_for_this = isset($orders[$i]) ? (array)$orders[$i] : $selected_values;
                if ($attr && !empty($selected_values)) {
                    $terms = get_terms([
                        'taxonomy' => $attr,
                        'include' => $selected_values,
                        'hide_empty' => false,
                    ]);
                    // Order terms as per $orders_for_this
                    $ordered_terms = [];
                    foreach ($orders_for_this as $term_id) {
                        foreach ($terms as $term) {
                            if ($term->term_id == $term_id) {
                                $ordered_terms[] = $term;
                            }
                        }
                    }
                    // Add missing terms (in case of new selections)
                    foreach ($terms as $term) {
                        if (!in_array($term, $ordered_terms)) {
                            $ordered_terms[] = $term;
                        }
                    }
                    echo '<label>Order (drag to reorder):</label><br>';
                    echo '<ul class="alx-sortable" data-index="'.$i.'" style="margin-bottom:10px; background:#f9f9f9; padding:10px; min-width:250px;">';
                    // "Any" option as a draggable item
                    $any_selected = (isset($orders_for_this[0]) && $orders_for_this[0] === 'any') ? 'checked' : '';
                    echo '<li class="alx-sortable-any"><label><input type="checkbox" name="alx_orders['.$i.'][]" value="any" '.$any_selected.'> Any</label></li>';
                    foreach ($ordered_terms as $term) {
                        echo '<li class="alx-sortable-item" data-term="'.$term->term_id.'">'.esc_html($term->name);
                        echo '<input type="hidden" name="alx_orders['.$i.'][]" value="'.$term->term_id.'">';
                        echo '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<em>No values selected.</em>';
                }
                echo '<br><hr>';
                echo '</div>';
            }
            ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', 'alx_shopper_register_settings');
function alx_shopper_register_settings() {
    register_setting('alx_shopper_settings_group', 'alx_shopper_num_dropdowns');
    register_setting('alx_shopper_settings_group', 'alx_shopper_categories');
    register_setting('alx_shopper_settings_group', 'alx_shopper_dropdown_titles');
    register_setting('alx_shopper_settings_group', 'alx_shopper_dropdown_attributes');
    register_setting('alx_shopper_settings_group', 'alx_shopper_dropdown_values');
    register_setting('alx_shopper_settings_group', 'alx_shopper_dropdown_value_order');
    

    add_settings_section('alx_shopper_main_section', 'Main Settings', null, 'alx-shopper-settings');

    add_settings_field(
        'alx_shopper_num_dropdowns',
        'Number of Dropdowns (2-5)',
        'alx_shopper_num_dropdowns_callback',
        'alx-shopper-settings',
        'alx_shopper_main_section'
    );

    add_settings_field(
        'alx_shopper_categories',
        'Product Categories for Search',
        'alx_shopper_categories_callback',
        'alx-shopper-settings',
        'alx_shopper_main_section'
    );

    add_settings_field(
        'alx_shopper_dropdown_titles',
        'Dropdown Titles',
        'alx_shopper_dropdown_titles_callback',
        'alx-shopper-settings',
        'alx_shopper_main_section'
    );

    add_settings_field(
        'alx_shopper_dropdown_attributes',
        'Dropdown Attribute Mapping',
        'alx_shopper_dropdown_attributes_callback',
        'alx-shopper-settings',
        'alx_shopper_main_section'
    );

    add_settings_field(
        'alx_shopper_dropdown_values',
        'Dropdown Value Selection',
        'alx_shopper_dropdown_values_callback',
        'alx-shopper-settings',
        'alx_shopper_main_section'
    );

    add_settings_field(
        'alx_shopper_dropdown_value_order',
        'Dropdown Value Order',
        'alx_shopper_dropdown_value_order_callback',
        'alx-shopper-settings',
        'alx_shopper_main_section'
    );

    add_settings_field(
        'alx_shopper_enable_email_results',
        'Enable Email Results Option',
        function() {
            $enabled = get_option('alx_shopper_enable_email_results', false);
            echo '<input type="checkbox" name="alx_shopper_enable_email_results" value="1" '.checked($enabled, 1, false).' /> Allow users to email results to themselves';
        },
        'alx-shopper-settings',
        'alx_shopper_main_section'
    );
}

function alx_shopper_num_dropdowns_callback() {
    $value = get_option('alx_shopper_num_dropdowns', 2);
    echo '<input type="number" min="2" max="5" name="alx_shopper_num_dropdowns" value="' . esc_attr($value) . '" />';
}

function alx_shopper_categories_callback() {
    $selected = (array) get_option('alx_shopper_categories', []);
    $categories = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ]);
    if (empty($categories) || is_wp_error($categories)) {
        echo 'No product categories found.';
        return;
    }
    echo '<select name="alx_shopper_categories[]" multiple style="min-width:250px; height:100px;">';
    foreach ($categories as $cat) {
        $selected_attr = in_array($cat->term_id, $selected) ? 'selected' : '';
        echo "<option value='{$cat->term_id}' {$selected_attr}>{$cat->name}</option>";
    }
    echo '</select>';
    echo '<br><small>Hold Cmd (Mac) or Ctrl (Windows) to select multiple categories.</small>';
}

function alx_shopper_dropdown_titles_callback() {
    $titles = get_option('alx_shopper_dropdown_titles', []);
    $num = intval(get_option('alx_shopper_num_dropdowns', 2));
    for ($i = 0; $i < $num; $i++) {
        $val = isset($titles[$i]) ? esc_attr($titles[$i]) : '';
        echo '<input type="text" name="alx_shopper_dropdown_titles['.$i.']" value="'.$val.'" placeholder="Dropdown '.($i+1).' Title" style="margin-bottom:5px; width:250px;" /><br />';
    }
    echo '<p class="description">Set a title for each dropdown (shown on the front end).</p>';
}

function alx_shopper_dropdown_attributes_callback() {
    $mapping = get_option('alx_shopper_dropdown_attributes', []);
    $num = intval(get_option('alx_shopper_num_dropdowns', 2));
    if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
        echo 'WooCommerce is required for this feature.';
        return;
    }
    $attribute_taxonomies = wc_get_attribute_taxonomies();
    if ( empty( $attribute_taxonomies ) ) {
        echo 'No global attributes found.';
        return;
    }
    // Build attribute options
    $options = [];
    foreach ( $attribute_taxonomies as $tax ) {
        $attr_name = wc_attribute_taxonomy_name( $tax->attribute_name );
        $label = esc_html( $tax->attribute_label );
        $options[$attr_name] = $label;
    }
    // Render a select for each dropdown
    for ($i = 0; $i < $num; $i++) {
        $selected = isset($mapping[$i]) ? esc_attr($mapping[$i]) : '';
        echo '<label>Dropdown '.($i+1).': </label>';
        echo '<select name="alx_shopper_dropdown_attributes['.$i.']" style="min-width:200px;">';
        echo '<option value="">-- Select Attribute --</option>';
        foreach ($options as $attr_name => $label) {
            $sel = ($selected === $attr_name) ? 'selected' : '';
            echo "<option value='{$attr_name}' {$sel}>{$label}</option>";
        }
        echo '</select><br />';
    }
    echo '<p class="description">Assign a WooCommerce attribute to each dropdown filter.</p>';
}

function alx_shopper_dropdown_values_callback() {
    $mapping = get_option('alx_shopper_dropdown_attributes', []);
    $values = get_option('alx_shopper_dropdown_values', []);
    $num = intval(get_option('alx_shopper_num_dropdowns', 2));

    for ($i = 0; $i < $num; $i++) {
        $attr = isset($mapping[$i]) ? $mapping[$i] : '';
        if (!$attr) {
            echo '<p>Dropdown '.($i+1).': <em>No attribute selected.</em></p>';
            continue;
        }
        $taxonomy = $attr;
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);
        echo '<strong>Dropdown '.($i+1).' Values:</strong><br>';
        if (empty($terms) || is_wp_error($terms)) {
            echo '<em>No terms found for this attribute.</em><br>';
            continue;
        }
        $selected = isset($values[$i]) ? (array)$values[$i] : [];
        echo '<select name="alx_shopper_dropdown_values['.$i.'][]" multiple style="min-width:250px; height:100px;">';
        foreach ($terms as $term) {
            $sel = in_array($term->term_id, $selected) ? 'selected' : '';
            echo "<option value='{$term->term_id}' {$sel}>{$term->name}</option>";
        }
        echo '</select><br><br>';
    }
    echo '<p class="description">Select which values will be available in each dropdown. Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</p>';
}

function alx_shopper_dropdown_value_order_callback() {
    $mapping = get_option('alx_shopper_dropdown_attributes', []);
    $values = get_option('alx_shopper_dropdown_values', []);
    $orders = get_option('alx_shopper_dropdown_value_order', []);
    $num = intval(get_option('alx_shopper_num_dropdowns', 2));

    for ($i = 0; $i < $num; $i++) {
        $attr = isset($mapping[$i]) ? $mapping[$i] : '';
        if (!$attr) {
            echo '<p>Dropdown '.($i+1).': <em>No attribute selected.</em></p>';
            continue;
        }
        $selected = isset($values[$i]) ? (array)$values[$i] : [];
        if (empty($selected)) {
            echo '<p>Dropdown '.($i+1).': <em>No values selected.</em></p>';
            continue;
        }
        // Get term objects for selected values
        $terms = get_terms([
            'taxonomy' => $attr,
            'include' => $selected,
            'hide_empty' => false,
        ]);
        // Use saved order or default to selected order
        $order = isset($orders[$i]) ? (array) $orders[$i] : $selected;
        // Build ordered list of terms
        $ordered_terms = [];
        foreach ($order as $term_id) {
            foreach ($terms as $term) {
                if ($term->term_id == $term_id) {
                    $ordered_terms[] = $term;
                }
            }
        }
        // Add any missing terms (in case of new selections)
        foreach ($terms as $term) {
            if (!in_array($term, $ordered_terms)) {
                $ordered_terms[] = $term;
            }
        }
        echo '<label>Dropdown '.($i+1).' Value Order:</label><br>';
        echo '<ul class="alx-sortable" data-input="alx_shopper_dropdown_value_order['.$i.']">';
        // "Any" option
        $any_selected = (isset($order[0]) && $order[0] === 'any') ? 'checked' : '';
        echo '<li class="alx-sortable-any"><label><input type="checkbox" name="alx_shopper_dropdown_value_order['.$i.'][]" value="any" '.$any_selected.'> Any</label></li>';
        foreach ($ordered_terms as $term) {
            echo '<li class="alx-sortable-item" data-term="'.$term->term_id.'">'.esc_html($term->name);
            echo '<input type="hidden" name="alx_shopper_dropdown_value_order['.$i.'][]" value="'.$term->term_id.'">';
            echo '</li>';
        }
        echo '</ul><br>';
    }
    echo '<p class="description">Drag to reorder values. "Any" will allow users to search for any value in this dropdown.</p>';
}

add_action('admin_enqueue_scripts', function() {
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_script('alx-shopper-admin', ALX_SHOPPER_URL . 'assets/js/alx-shopper-admin.js', ['jquery', 'jquery-ui-sortable'], ALX_SHOPPER_VERSION, true);
    wp_enqueue_style('alx-shopper-admin', ALX_SHOPPER_URL . 'assets/css/alx-shopper-admin.css', [], ALX_SHOPPER_VERSION);
});

// Enqueue frontend scripts and styles
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script(
        'alx-shopper-frontend',
        ALX_SHOPPER_URL . 'assets/js/shopper-script.js', // <- Use the correct file here
        ['jquery'],
        ALX_SHOPPER_VERSION,
        true
    );
    wp_localize_script('alx-shopper-frontend', 'alxShopperAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'enable_email_results' => (bool) get_option('alx_shopper_enable_email_results', false),
    ]);
    wp_enqueue_style(
        'alx-shopper-style',
        ALX_SHOPPER_URL . 'assets/css/shopper-style.css',
        [],
        ALX_SHOPPER_VERSION
    );
});

add_action('wp_ajax_alx_get_attribute_terms', function() {
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
    $index = isset($_POST['index']) ? intval($_POST['index']) : 0;

    if (!$taxonomy) {
        echo '<em>No attribute selected.</em>';
        wp_die();
    }

    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ]);

    if (empty($terms) || is_wp_error($terms)) {
        echo '<em>No terms found for this attribute.</em>';
        wp_die();
    }

    // Output the sortable list for drag-and-drop ordering
    echo '<ul class="alx-sortable" data-input="alx_shopper_dropdown_value_order['.$index.']">';
    // "Any" option
    echo '<li class="alx-sortable-any"><label><input type="checkbox" name="alx_shopper_dropdown_value_order['.$index.'][]" value="any"> Any</label></li>';
    foreach ($terms as $term) {
        echo '<li class="alx-sortable-item" data-term="'.$term->term_id.'">'.esc_html($term->name);
        echo '<input type="hidden" name="alx_shopper_dropdown_value_order['.$index.'][]" value="'.$term->term_id.'">';
        echo '</li>';
    }
    echo '</ul>';

    wp_die();
});

add_action('wp_ajax_alx_shopper_filter', 'alx_shopper_filter_ajax');
add_action('wp_ajax_nopriv_alx_shopper_filter', 'alx_shopper_filter_ajax');

function alx_shopper_filter_ajax() {
    ob_start();
    $filter_id = isset($_POST['alx_filter_id']) ? sanitize_key($_POST['alx_filter_id']) : 'default';
    $config = alx_shopper_get_filter_config($filter_id);

    // Fallback to global config if CPT config not found
    if (!$config) {
        $num = intval(get_option('alx_shopper_num_dropdowns', 2));
        $mapping = get_option('alx_shopper_dropdown_attributes', []);
        $categories = (array) get_option('alx_shopper_categories', []);
    } else {
        $num = isset($config['num']) ? intval($config['num']) : 2;
        $mapping = isset($config['mapping']) ? $config['mapping'] : [];
        $categories = isset($config['categories']) ? (array)$config['categories'] : [];
    }

    $tax_query = [];
    if (!empty($categories)) {
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $categories,
        ];
    }
    for ($i = 0; $i < $num; $i++) {
        $attr = isset($mapping[$i]) ? $mapping[$i] : '';
        $val = isset($_POST["alx_dropdown_$i"]) ? $_POST["alx_dropdown_$i"] : '';
        // Accept 0 as a valid value
        if ($attr !== '' && $val !== '' && $val !== 'any') {
            $tax_query[] = [
                'taxonomy' => $attr,
                'field'    => 'term_id',
                'terms'    => [ intval($val) ],
            ];
        }
    }

    $args = [
        'post_type'      => 'product',
        'posts_per_page' => 12,
        'tax_query'      => $tax_query,
    ];

    $products = new WP_Query($args);

    if ($products->have_posts()) {
        echo '<div class="alx-shopper-results">';
        while ($products->have_posts()) {
            $products->the_post();
            global $product;
            ?>
            <div class="alx-shopper-product">
                <a href="<?php the_permalink(); ?>">
                    <?php echo $product->get_image(); ?>
                    <h3><?php the_title(); ?></h3>
                </a>
                <div class="alx-shopper-price"><?php echo $product->get_price_html(); ?></div>
                <div class="alx-shopper-actions">
                    <button class="alx-shopper-btn quick-view" data-id="<?php echo get_the_ID(); ?>">Quick View</button>
                    <a class="alx-shopper-btn view-product" href="<?php the_permalink(); ?>" target="_blank">View Product</a>
                </div>
            </div>
            <?php
        }
        echo '</div>';
        wp_reset_postdata();
    }

    $html = ob_get_clean();
    echo $html;
    wp_die();
}

function alx_shopper_shortcode($atts = []) {
    $atts = shortcode_atts(['id' => 'default'], $atts);
    $filter_id = sanitize_key($atts['id']);
    global $alx_shopper_current_filter_id, $alx_shopper_current_filter_config;
    $alx_shopper_current_filter_id = $filter_id;
    $alx_shopper_current_filter_config = alx_shopper_get_filter_config($filter_id);
    ob_start();
    include ALX_SHOPPER_DIR . 'templates/shopper-main.php';
    return ob_get_clean();
}
add_shortcode('alx_shopper', 'alx_shopper_shortcode');

add_action('wp_ajax_alx_quick_view', 'alx_quick_view_callback');
add_action('wp_ajax_nopriv_alx_quick_view', 'alx_quick_view_callback');

function alx_quick_view_callback() {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if (!$product_id) {
        echo 'Product not found.';
        wp_die();
    }
    $product = wc_get_product($product_id);
    if (!$product) {
        echo 'Product not found.';
        wp_die();
    }
    // Output your quick view HTML here
    echo '<div class="alx-quick-view-modal-inner">';
    echo '<h2>' . esc_html($product->get_name()) . '</h2>';
    echo get_the_post_thumbnail($product_id, 'large');
    echo '<div class="alx-quick-view-price">' . $product->get_price_html() . '</div>';
    echo '<div class="alx-shopper-actions">';
    echo '<button class="alx-shopper-btn quick-view" data-id="' . esc_attr($product_id) . '">Quick View</button> ';
    echo '<a href="' . esc_url(get_permalink($product_id)) . '" target="_blank" class="alx-shopper-btn view-product">View Full Product</a>';
    echo '</div>';
    echo '</div>';
    wp_die();
}

add_action('admin_init', function() {
    if (
        isset($_POST['option_page']) &&
        $_POST['option_page'] === 'alx_shopper_settings_group' &&
        isset($_POST['submit'])
    ) {
        if (!isset($_POST['alx_shopper_enable_email_results'])) {
            update_option('alx_shopper_enable_email_results', 0);
        }
    }
});

add_action('wp_ajax_alx_shopper_send_results_email', 'alx_shopper_send_results_email');
add_action('wp_ajax_nopriv_alx_shopper_send_results_email', 'alx_shopper_send_results_email');
function alx_shopper_send_results_email() {
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    if (!is_email($email)) {
        wp_send_json_error(['message' => 'Invalid email address.']);
    }

    $filter_id = isset($_POST['alx_filter_id']) ? sanitize_key($_POST['alx_filter_id']) : 'default';
    $config = alx_shopper_get_filter_config($filter_id);

    if (!$config) {
        $num = intval(get_option('alx_shopper_num_dropdowns', 2));
        $mapping = get_option('alx_shopper_dropdown_attributes', []);
        $categories = (array) get_option('alx_shopper_categories', []);
    } else {
        $num = isset($config['num']) ? intval($config['num']) : 2;
        $mapping = isset($config['mapping']) ? $config['mapping'] : [];
        $categories = isset($config['categories']) ? (array)$config['categories'] : [];
    }

    $tax_query = [];
    if (!empty($categories)) {
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $categories,
        ];
    }
    for ($i = 0; $i < $num; $i++) {
    $attr = isset($mapping[$i]) ? $mapping[$i] : '';
    $val = isset($_POST["alx_dropdown_$i"]) ? $_POST["alx_dropdown_$i"] : '';
    // Accept 0 as a valid value
    if ($attr !== '' && $val !== '' && $val !== 'any') {
        $tax_query[] = [
            'taxonomy' => $attr,
            'field'    => 'term_id',
            'terms'    => [ intval($val) ], // <-- force integer for term_id
        ];
    }
}

    $args = [
        'post_type'      => 'product',
        'posts_per_page' => 12,
        'tax_query'      => $tax_query,
    ];
    $products = new WP_Query($args);

    // Suggested matches: match any (not all) of the selected dropdowns
    $suggested_query = [];
    if (!empty($categories)) {
        $suggested_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $categories,
        ];
    }
       
    $suggested_tax = [];
    for ($i = 0; $i < $num; $i++) {
        $attr = isset($mapping[$i]) ? $mapping[$i] : '';
        $val = isset($_POST["alx_dropdown_$i"]) ? sanitize_text_field($_POST["alx_dropdown_$i"]) : '';
        if ($attr !== '' && $val !== '' && $val !== 'any') {
            $suggested_tax[] = [
                'taxonomy' => $attr,
                'field'    => 'term_id',
                'terms'    => [$val],
            ];
        }
    }
    if (!empty($suggested_tax)) {
        $suggested_query[] = array_merge(['relation' => 'OR'], $suggested_tax);
    }
    $suggested_args = [
        'post_type'      => 'product',
        'posts_per_page' => 12,
        'tax_query'      => $suggested_query,
    ];
    $suggested_products = new WP_Query($suggested_args);

    $body = '<html><body style="font-family:Arial,sans-serif;">';

    // Exact matches section
    if ($products->have_posts()) {
        $body .= '<h2 style="color:#2196F3;">Exact Matches</h2><ul>';
        while ($products->have_posts()) {
            $products->the_post();
            $product = wc_get_product(get_the_ID());
            $body .= '<li style="margin-bottom:18px;">';
            $body .= '<a href="' . esc_url(get_permalink()) . '" style="font-size:1.1em;font-weight:bold;color:#333;text-decoration:none;">' . esc_html(get_the_title()) . '</a><br>';
            $body .= $product->get_image('thumbnail') . '<br>';
            $body .= '<span style="color:#222;">Price: ' . $product->get_price_html() . '</span><br>';
            $body .= '</li>';
        }
        $body .= '</ul>';
        wp_reset_postdata();
    } else {
        $body .= '<h2 style="color:#2196F3;">Exact Matches</h2><p>No exact matches found.</p>';
    }

    // Suggested matches section (exclude already listed exact matches)
    $exact_ids = [];
    if (isset($products->posts)) {
        foreach ($products->posts as $p) {
            $exact_ids[] = $p->ID;
        }
    }
    $suggested_added = false;
    if ($suggested_products->have_posts()) {
        while ($suggested_products->have_posts()) {
            $suggested_products->the_post();
            if (in_array(get_the_ID(), $exact_ids)) continue;
            if (!$suggested_added) {
                $body .= '<h2 style="color:#FF9800;">Suggested Matches</h2><ul>';
                $suggested_added = true;
            }
            $product = wc_get_product(get_the_ID());
            $body .= '<li style="margin-bottom:18px;">';
            $body .= '<a href="' . esc_url(get_permalink()) . '" style="font-size:1.1em;font-weight:bold;color:#333;text-decoration:none;">' . esc_html(get_the_title()) . '</a><br>';
            $body .= $product->get_image('thumbnail') . '<br>';
            $body .= '<span style="color:#222;">Price: ' . $product->get_price_html() . '</span><br>';
            $body .= '</li>';
        }
        if ($suggested_added) {
            $body .= '</ul>';
        }
        wp_reset_postdata();
    }
    if (!$suggested_added) {
        $body .= '<h2 style="color:#FF9800;">Suggested Matches</h2><p>No suggested matches found.</p>';
    }

    $body .= '<p style="margin-top:30px;font-size:0.95em;color:#888;">Sent from ' . esc_html(get_bloginfo('name')) . '</p>';
    $body .= '</body></html>';

    $subject = 'Your Product Matches from ' . get_bloginfo('name');
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    $sent = wp_mail($email, $subject, $body, $headers);

    if ($sent) {
        wp_send_json_success();
    } else {
        wp_send_json_error(['message' => 'Could not send email.']);
    }
}

// 1. Register the custom post type for filters as a submenu under the main dashboard menu
add_action('init', function() {
    register_post_type('alx_shopper_filter', [
        'label' => 'Shopper Filters',
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'alx-shopper', // <-- This makes it a submenu
        'supports' => ['title'],
    ]);
});

// 2. Add meta boxes for filter settings
add_action('add_meta_boxes', function() {
    add_meta_box(
        'alx_shopper_filter_settings',
        'Filter Settings',
        'alx_shopper_filter_settings_metabox',
        'alx_shopper_filter',
        'normal',
        'default'
    );
});

function alx_shopper_filter_settings_metabox($post) {
    $num = get_post_meta($post->ID, '_alx_num', true) ?: 2;
    $mapping = get_post_meta($post->ID, '_alx_mapping', true) ?: [];
    $categories = get_post_meta($post->ID, '_alx_categories', true) ?: [];
    $titles = get_post_meta($post->ID, '_alx_titles', true) ?: [];
    $values = get_post_meta($post->ID, '_alx_values', true) ?: [];
    $orders = get_post_meta($post->ID, '_alx_orders', true) ?: [];
    $attribute_taxonomies = function_exists('wc_get_attribute_taxonomies') ? wc_get_attribute_taxonomies() : [];

    wp_nonce_field('alx_shopper_filter_save', 'alx_shopper_filter_nonce');

    // --- Add this block at the top of the metabox ---
    $shortcode = '[alx_shopper id="' . esc_attr($post->post_name) . '"]';
    echo '<div style="margin-bottom:15px;"><strong>Shortcode:</strong> ';
    echo '<input type="text" readonly value="' . esc_attr($shortcode) . '" style="width:300px;" onclick="this.select();" />';
    echo '<br><small>Copy and paste this shortcode to use this filter on any page.</small></div>';
    // --- End block ---

    ?>
    <p>
        <label>Number of Dropdowns (2-5):</label>
        <input type="number" name="alx_num" min="2" max="5" value="<?php echo esc_attr($num); ?>">
    </p>
    <p>
        <label>Categories:</label><br>
        <?php
        $all_cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        echo '<select name="alx_categories[]" multiple style="min-width:250px; height:100px;">';
        foreach ($all_cats as $cat) {
            $selected = in_array($cat->term_id, (array)$categories) ? 'selected' : '';
            echo "<option value='{$cat->term_id}' $selected>{$cat->name}</option>";
        }
        echo '</select>';
        ?>
        <br><small>Hold Cmd (Mac) or Ctrl (Windows) to select multiple categories.</small>
    </p>
    <hr>
    <?php
    for ($i = 0; $i < $num; $i++) {
        $title = isset($titles[$i]) ? esc_attr($titles[$i]) : '';
        $selected_attr = isset($mapping[$i]) ? $mapping[$i] : '';
        echo "<h4>Dropdown " . ($i+1) . "</h4>";
        echo "<label>Title: <input type='text' name='alx_titles[$i]' value='$title'></label><br>";
        echo "<label>Attribute: <select name='alx_mapping[$i]'>";
        echo "<option value=''>-- Select Attribute --</option>";
        foreach ($attribute_taxonomies as $tax) {
            $attr_name = wc_attribute_taxonomy_name($tax->attribute_name);
            $sel = ($selected_attr === $attr_name) ? 'selected' : '';
            echo "<option value='$attr_name' $sel>{$tax->attribute_label}</option>";
        }
        echo "</select></label><br><br>";

        // Values
        $attr = isset($mapping[$i]) ? $mapping[$i] : '';
        $selected_values = isset($values[$i]) ? (array)$values[$i] : [];
        if ($attr) {
            $terms = get_terms([
                'taxonomy' => $attr,
                'hide_empty' => false,
            ]);
            if (!empty($terms) && !is_wp_error($terms)) {
                echo '<label>Values:</label><br>';
                echo '<select name="alx_values['.$i.'][]" multiple style="min-width:250px; height:100px;">';
                foreach ($terms as $term) {
                    $sel = in_array($term->term_id, $selected_values) ? 'selected' : '';
                    echo "<option value='{$term->term_id}' {$sel}>{$term->name}</option>";
                }
                echo '</select><br><br>';
            }
        }
        // Order
        $orders_for_this = isset($orders[$i]) ? (array)$orders[$i] : $selected_values;
        if ($attr && !empty($selected_values)) {
            $terms = get_terms([
                'taxonomy' => $attr,
                'include' => $selected_values,
                'hide_empty' => false,
            ]);
            // Order terms as per $orders_for_this
            $ordered_terms = [];
            foreach ($orders_for_this as $term_id) {
                foreach ($terms as $term) {
                    if ($term->term_id == $term_id) {
                        $ordered_terms[] = $term;
                    }
                }
            }
            // Add missing terms (in case of new selections)
            foreach ($terms as $term) {
                if (!in_array($term, $ordered_terms)) {
                    $ordered_terms[] = $term;
                }
            }
            echo '<label>Order (drag to reorder):</label><br>';
            echo '<ul class="alx-sortable" data-index="'.$i.'" style="margin-bottom:10px; background:#f9f9f9; padding:10px; min-width:250px;">';
            // "Any" option as a draggable item
            $any_selected = (isset($orders_for_this[0]) && $orders_for_this[0] === 'any') ? 'checked' : '';
            echo '<li class="alx-sortable-any"><label><input type="checkbox" name="alx_orders['.$i.'][]" value="any" '.$any_selected.'> Any</label></li>';
            foreach ($ordered_terms as $term) {
                echo '<li class="alx-sortable-item" data-term="'.$term->term_id.'">'.esc_html($term->name);
                echo '<input type="hidden" name="alx_orders['.$i.'][]" value="'.$term->term_id.'">';
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '<hr>';
    }
    // --- Add this block here ---
    $enable_email = get_post_meta($post->ID, '_alx_enable_email_results', true);
    echo '<p><label><input type="checkbox" name="alx_enable_email_results" value="1" ' . checked($enable_email, 1, false) . ' /> Allow users to email results to themselves</label></p>';
    // --- End block ---
    ?>
    <button type="button" class="button button-primary" id="alx-save-refresh"><?php esc_html_e('Save & Refresh', 'alx-shopper'); ?></button>
    <span id="alx-save-refresh-status" style="margin-left:10px;"></span>
    <script>
    jQuery(document).ready(function($) {
        $('#alx-save-refresh').on('click', function() {
            var $btn = $(this);
            var $form = $btn.closest('form');
            var formData = $form.serialize();
            $btn.prop('disabled', true);
            $('#alx-save-refresh-status').text('Saving...');
            $.post(ajaxurl, formData + '&action=alx_shopper_save_filter_ajax', function(response) {
                if (response.success) {
                    $('#alx-save-refresh-status').text('Saved! Refreshing...');
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    $('#alx-save-refresh-status').text('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    $btn.prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php
}

// 3. Save meta box data
add_action('save_post_alx_shopper_filter', function($post_id) {
    if (!isset($_POST['alx_shopper_filter_nonce']) || !wp_verify_nonce($_POST['alx_shopper_filter_nonce'], 'alx_shopper_filter_save')) return;
    update_post_meta($post_id, '_alx_num', intval($_POST['alx_num']));
    update_post_meta($post_id, '_alx_mapping', array_map('sanitize_text_field', $_POST['alx_mapping'] ?? []));
    update_post_meta($post_id, '_alx_categories', array_map('intval', $_POST['alx_categories'] ?? []));
    update_post_meta($post_id, '_alx_titles', array_map('sanitize_text_field', $_POST['alx_titles'] ?? []));
    update_post_meta($post_id, '_alx_values', $_POST['alx_values'] ?? []);
    update_post_meta($post_id, '_alx_orders', $_POST['alx_orders'] ?? []);
});

// 4. Update your shortcode and AJAX/email handlers to load config from the CPT
function alx_shopper_get_filter_config($filter_id) {
    $post = get_page_by_path($filter_id, OBJECT, 'alx_shopper_filter');
    if (!$post) return false;
    return [
        'num' => get_post_meta($post->ID, '_alx_num', true) ?: 2,
        'mapping' => get_post_meta($post->ID, '_alx_mapping', true) ?: [],
        'categories' => get_post_meta($post->ID, '_alx_categories', true) ?: [],
        'titles' => get_post_meta($post->ID, '_alx_titles', true) ?: [],
        'values' => get_post_meta($post->ID, '_alx_values', true) ?: [],
        'orders' => get_post_meta($post->ID, '_alx_orders', true) ?: [],
        'enable_email_results' => get_post_meta($post->ID, '_alx_enable_email_results', true) ? true : false, // <-- Add this line
    ];
}

// Example usage in AJAX/email handler:
$filter_id = isset($_POST['alx_filter_id']) ? sanitize_key($_POST['alx_filter_id']) : 'default';
$config = alx_shopper_get_filter_config($filter_id);
// Fallback to default config if needed

add_action('wp_ajax_alx_shopper_save_filter_ajax', function() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }
    $post_id = isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0;
    if (!$post_id) {
        wp_send_json_error(['message' => 'Invalid post ID']);
    }
    // Simulate save_post logic
    if (!isset($_POST['alx_shopper_filter_nonce']) || !wp_verify_nonce($_POST['alx_shopper_filter_nonce'], 'alx_shopper_filter_save')) {
        wp_send_json_error(['message' => 'Nonce check failed']);
    }
    update_post_meta($post_id, '_alx_num', intval($_POST['alx_num']));
    update_post_meta($post_id, '_alx_mapping', array_map('sanitize_text_field', $_POST['alx_mapping'] ?? []));
    update_post_meta($post_id, '_alx_categories', array_map('intval', $_POST['alx_categories'] ?? []));
    update_post_meta($post_id, '_alx_titles', array_map('sanitize_text_field', $_POST['alx_titles'] ?? []));
    update_post_meta($post_id, '_alx_values', $_POST['alx_values'] ?? []);
    update_post_meta($post_id, '_alx_orders', $_POST['alx_orders'] ?? []);
    wp_send_json_success();
});


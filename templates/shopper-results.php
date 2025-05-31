<?php
global $alx_shopper_current_filter_config, $alx_shopper_current_filter_id;

// Prefer CPT config if available
if ($alx_shopper_current_filter_config && is_array($alx_shopper_current_filter_config)) {
    $num = intval($alx_shopper_current_filter_config['num']);
    $mapping = $alx_shopper_current_filter_config['mapping'];
    $categories = isset($alx_shopper_current_filter_config['categories']) ? (array)$alx_shopper_current_filter_config['categories'] : [];
} else {
    $num = intval(get_option('alx_shopper_num_dropdowns', 2));
    $mapping = get_option('alx_shopper_dropdown_attributes', []);
    $categories = (array) get_option('alx_shopper_categories', []);
}

$tax_query = [];

// Filter by selected categories
if (!empty($categories)) {
    $tax_query[] = [
        'taxonomy' => 'product_cat',
        'field'    => 'term_id',
        'terms'    => $categories,
    ];
}

// Filter by each dropdown (attribute)
for ($i = 0; $i < $num; $i++) {
    $attr = isset($mapping[$i]) ? $mapping[$i] : '';
    $val = isset($_POST["alx_dropdown_$i"]) ? sanitize_text_field($_POST["alx_dropdown_$i"]) : '';
    if ($attr !== '' && (string)$val !== '' && (string)$val !== 'any') {
        // Convert term name to term ID
        $term = get_term_by('name', $val, $attr);
        if ($term && !is_wp_error($term)) {
            $tax_query[] = [
                'taxonomy' => $attr,
                'field'    => 'term_id',
                'terms'    => [$term->term_id],
            ];
        }
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
            <?php if ( function_exists( 'wc_get_product' ) ) : ?>
                <div class="alx-shopper-price">
                    <?php echo $product->get_price_html(); ?>
                </div>
            <?php endif; ?>
            <div class="alx-shopper-actions">
                <button class="quick-view" data-id="<?php echo get_the_ID(); ?>">Quick View</button>
                <a class="view-product" href="<?php the_permalink(); ?>" target="_blank">View Product</a>
            </div>
        </div>
        <?php
    }
    echo '</div>';
    wp_reset_postdata();
} else {
    echo '<p>No products found matching your filters.</p>';
}

// Collect filter keys, values, and labels (for display/debug)
$filter_keys = [];
$filter_vals = [];
$filter_labels = [];
for ($i = 0; $i < $num; $i++) {
    $attr_key = isset($mapping[$i]) ? $mapping[$i] : '';
    $attr_val = isset($_POST["alx_dropdown_$i"]) ? sanitize_text_field($_POST["alx_dropdown_$i"]) : '';
    $label = isset($_POST["alx_dropdown_{$i}_label"]) ? sanitize_text_field($_POST["alx_dropdown_{$i}_label"]) : $attr_key;
    $filter_keys[] = $attr_key;
    $filter_vals[] = ($attr_key && $attr_val && $attr_val !== 'any') ? $attr_val : null;
    $filter_labels[] = $label;
}

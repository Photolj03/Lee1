<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Alx_Shopper_Search {
    public function __construct() {
        add_action('wp_ajax_alx_shopper_filter', [$this, 'ajax_find_products_with_relaxation']);
        add_action('wp_ajax_nopriv_alx_shopper_filter', [$this, 'ajax_find_products_with_relaxation']);
    }

    public function ajax_find_products_with_relaxation() {
        // Allow filtering of max suggestions and posts per query
        $max_suggestions = apply_filters('alx_shopper_max_suggestions', 3);

        // Get filter config from CPT
        $filter_id = isset($_POST['alx_filter_id']) ? sanitize_key($_POST['alx_filter_id']) : 'default';
        $config = function_exists('alx_shopper_get_filter_config') ? alx_shopper_get_filter_config($filter_id) : false;
        if (!$config) {
            wp_send_json_error(['message' => 'Invalid filter configuration.']);
        }

        // 1. Collect filters in order
        $filter_keys = [];
        $filter_vals = [];
        $filter_labels = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'alx_dropdown_') === 0 && strpos($key, '_attribute') === false && strpos($key, '_label') === false) {
                $attr_key = isset($_POST[$key . '_attribute']) ? sanitize_text_field($_POST[$key . '_attribute']) : '';
                $attr_val = sanitize_text_field($value);
                $label = isset($_POST[$key . '_label']) && !empty($_POST[$key . '_label'])
                    ? sanitize_text_field($_POST[$key . '_label'])
                    : ucwords(str_replace(['pa_', '_'], ['', ' '], $attr_key));
                $filter_keys[] = $attr_key;

                // Use term ID directly
                if ($attr_key && $attr_val !== '' && $attr_val !== 'any') {
                    $filter_vals[] = intval($attr_val);
                } else {
                    $filter_vals[] = null;
                }
                $filter_labels[] = $label;
            }
        }

        $categories = isset($config['categories']) ? $config['categories'] : [];
        if (!is_array($categories)) {
            $categories = [$categories];
        }

        $suggestions = [];
        $used_ids = [];
        $explanations = [];
        $relaxation_used = false;

        // 2. Strict match
        $filters = [];
        foreach ($filter_keys as $i => $key) {
            if ($key && $filter_vals[$i]) $filters[$key] = $filter_vals[$i];
        }
        $strict_products = $this->find_products_with_relaxation($filters, $categories);

        // Add all exact matches
        foreach ($strict_products as $pid) {
            $suggestions[] = $pid;
            $used_ids[$pid] = true;
            $explanations[$pid] = 'Exact match for your requirements.';
        }

        $num_exact = count($strict_products);

        // 3. Relax one filter at a time (from last to first)
        $num_filters = count($filter_keys);
        for ($relax = $num_filters - 1; $relax >= 0 && count($suggestions) < $max_suggestions; $relax--) {
            if (!$filter_keys[$relax] || !$filter_vals[$relax]) continue;
            $all_terms = $this->get_all_terms_for_tax($filter_keys[$relax]);
            if (empty($all_terms)) continue;
            if ($relax === 0) {
                $selected_index = array_search($filter_vals[$relax], $all_terms);
                foreach ($all_terms as $idx => $term_id) {
                    if ($idx <= $selected_index) continue;
                    $relaxed = $filters;
                    $relaxed[$filter_keys[$relax]] = $term_id;
                    $products = $this->find_products_with_relaxation($relaxed, $categories);
                    foreach ($products as $pid) {
                        if (!isset($used_ids[$pid])) {
                            $from_term = get_term($filter_vals[$relax], $filter_keys[$relax]);
                            $to_term = get_term($term_id, $filter_keys[$relax]);
                            $explanations[$pid] = 'Changed "' . $filter_labels[$relax] . '" from "' . ($from_term ? $from_term->name : '') . '" to "' . ($to_term ? $to_term->name : '') . '".';
                            $suggestions[] = $pid;
                            $used_ids[$pid] = true;
                            $relaxation_used = true;
                            if (count($suggestions) >= $max_suggestions) break 2;
                        }
                    }
                }
            } else {
                foreach ($all_terms as $term_id) {
                    if ($term_id == $filter_vals[$relax]) continue;
                    $relaxed = $filters;
                    $relaxed[$filter_keys[$relax]] = $term_id;
                    $products = $this->find_products_with_relaxation($relaxed, $categories);
                    foreach ($products as $pid) {
                        if (!isset($used_ids[$pid])) {
                            $from_term = get_term($filter_vals[$relax], $filter_keys[$relax]);
                            $to_term = get_term($term_id, $filter_keys[$relax]);
                            $explanations[$pid] = 'Changed "' . $filter_labels[$relax] . '" from "' . ($from_term ? $from_term->name : '') . '" to "' . ($to_term ? $to_term->name : '') . '".';
                            $suggestions[] = $pid;
                            $used_ids[$pid] = true;
                            $relaxation_used = true;
                            if (count($suggestions) >= $max_suggestions) break 2;
                        }
                    }
                }
            }
        }

        // 4. If still not enough, relax two filters at a time (nested loops)
        if (count($suggestions) < $max_suggestions && $num_filters > 1) {
            for ($i = $num_filters - 1; $i >= 0; $i--) {
                for ($j = $num_filters - 1; $j >= 0; $j--) {
                    if ($i == $j || !$filter_keys[$i] || !$filter_keys[$j] || !$filter_vals[$i] || !$filter_vals[$j]) continue;
                    $terms_i = $this->get_all_terms_for_tax($filter_keys[$i]);
                    $terms_j = $this->get_all_terms_for_tax($filter_keys[$j]);
                    if (empty($terms_i) || empty($terms_j)) continue;
                    if ($i === 0) {
                        $selected_index_i = array_search($filter_vals[$i], $terms_i);
                    }
                    if ($j === 0) {
                        $selected_index_j = array_search($filter_vals[$j], $terms_j);
                    }
                    foreach ($terms_i as $idx_i => $term_i) {
                        if ($i === 0 && $idx_i <= $selected_index_i) continue;
                        if ($i !== 0 && $term_i == $filter_vals[$i]) continue;
                        foreach ($terms_j as $idx_j => $term_j) {
                            if ($j === 0 && $idx_j <= $selected_index_j) continue;
                            if ($j !== 0 && $term_j == $filter_vals[$j]) continue;
                            $relaxed = $filters;
                            $relaxed[$filter_keys[$i]] = $term_i;
                            $relaxed[$filter_keys[$j]] = $term_j;
                            $products = $this->find_products_with_relaxation($relaxed, $categories);
                            foreach ($products as $pid) {
                                if (!isset($used_ids[$pid])) {
                                    $from_term_i = get_term($filter_vals[$i], $filter_keys[$i]);
                                    $to_term_i = get_term($term_i, $filter_keys[$i]);
                                    $from_term_j = get_term($filter_vals[$j], $filter_keys[$j]);
                                    $to_term_j = get_term($term_j, $filter_keys[$j]);
                                    $explanations[$pid] = 'Changed "' . $filter_labels[$i] . '" from "' . ($from_term_i ? $from_term_i->name : '') . '" to "' . ($to_term_i ? $to_term_i->name : '') . '"'
                                        . ' and "' . $filter_labels[$j] . '" from "' . ($from_term_j ? $from_term_j->name : '') . '" to "' . ($to_term_j ? $to_term_j->name : '') . '".';
                                    $suggestions[] = $pid;
                                    $used_ids[$pid] = true;
                                    $relaxation_used = true;
                                    if (count($suggestions) >= $max_suggestions) break 5;
                                }
                            }
                        }
                    }
                }
            }
        }

        // 5. Build results
        $results = [];
        $num_suggested = count($suggestions) - $num_exact;
        foreach ($suggestions as $product_id) {
            $product = wc_get_product($product_id);
            $results[] = [
                'id'        => $product_id,
                'title'     => get_the_title($product_id),
                'permalink' => get_permalink($product_id),
                'image'     => get_the_post_thumbnail_url($product_id, 'medium'),
                'price_html'=> $product ? $product->get_price_html() : '',
                'explanation' => isset($explanations[$product_id]) ? $explanations[$product_id] : '',
            ];
        }

        $message = '';
        if ($num_exact > 0) {
            $message = "We found {$num_exact} exact match" . ($num_exact > 1 ? 'es' : '') .
                ($num_suggested > 0 ? " and {$num_suggested} suggested match" . ($num_suggested > 1 ? 'es' : '') : '') . '.';
        } elseif ($num_suggested > 0) {
            $message = "We found {$num_suggested} suggested match" . ($num_suggested > 1 ? 'es' : '') . '.';
        } else {
            $message = "No products found.";
        }

        wp_send_json_success([
            'results' => $results,
            'relaxation_used' => $relaxation_used,
            'message' => $message,
            'num_exact' => $num_exact,
            'num_suggested' => $num_suggested,
        ]);
    }

    // Helper to get all term IDs for a taxonomy
    private function get_all_terms_for_tax($taxonomy) {
        if (empty($taxonomy)) return [];
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        if (is_wp_error($terms) || empty($terms)) return [];
        return wp_list_pluck($terms, 'term_id');
    }

    public function find_products_with_relaxation($filters = [], $categories = []) {
        $tax_query = [];

        foreach ($filters as $taxonomy => $term_id) {
            if (empty($taxonomy) || empty($term_id)) continue;
            $tax_query[] = [
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => [$term_id],
            ];
        }

        if (!empty($categories)) {
            $tax_query[] = [
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $categories,
            ];
        }

        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }

        $posts_per_page = apply_filters('alx_shopper_posts_per_page', 12);

        $args = [
            'post_type'      => 'product',
            'posts_per_page' => $posts_per_page,
            'post_status'    => 'publish',
            'tax_query'      => $tax_query,
            'fields'         => 'ids',
        ];

        $query = new WP_Query($args);
        return $query->posts;
    }
}

<?php
/*
 * Plugin Name: Custom Product Options
 * Plugin URI: http://ronaldoroy.com
 * Description: Adds extra options selection to WooCommerce product pages with validation and order tracking.
 * Version: 1.0.0
 * Author: Ronaldo Roy
 * Author URI: http://ronaldoroy.com
 * License: GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function my_plugin_enqueue_styles() {
    wp_enqueue_style(
        'my-plugin-styles', // Handle for the stylesheet
        plugin_dir_url(__FILE__) . 'css/style.css', // Path to the CSS file
        array(), // No dependencies
        filemtime(plugin_dir_path(__FILE__) . 'css/style.css'), // Versioning based on the file modification time
        'all' // Media type (can be changed if needed)
    );
}

// Enqueue styles for both admin and frontend
add_action('admin_enqueue_scripts', 'my_plugin_enqueue_styles');
add_action('wp_enqueue_scripts', 'my_plugin_enqueue_styles');


// Add Meta Box for Custom Product Options
add_action('add_meta_boxes', 'add_custom_options_meta_box');
function add_custom_options_meta_box() {
    add_meta_box(
        'custom_product_options', // ID
        'Custom Product Options', // Title
        'render_custom_options_meta_box', // Callback
        'product', // Post type
        'normal', // Context
        'high' // Priority
    );
}

function render_custom_options_meta_box($post) {
    // Retrieve existing values
    $options = get_post_meta($post->ID, '_custom_product_options', true) ?: [];
    $minSelections = get_post_meta($post->ID, '_custom_min_selections', true) ?: 0;
    $maxSelections = get_post_meta($post->ID, '_custom_max_selections', true) ?: 0;

    // Nonce field for security
    wp_nonce_field('save_custom_options', 'custom_options_nonce');
    
    // Wrap the elements in a container for layout
    echo '<div class="custom-options-meta-box-layout">';
    
    // Left section for the textarea
    echo '<div class="custom-options-textarea">';
    echo '<label for="custom_product_options">Options (one per line):</label>';
    echo '<textarea id="custom_product_options" name="custom_product_options" rows="5">' . esc_textarea(implode("\n", $options)) . '</textarea>';
    echo '</div>';
    
    // Right section for min/max selections
    echo '<div class="custom-options-selections">';
    echo '<label for="custom_min_selections">Minimum Selections:</label>';
    echo '<input type="number" id="custom_min_selections" name="custom_min_selections" value="' . esc_attr($minSelections) . '"><br>';
    echo '<label for="custom_max_selections">Maximum Selections:</label>';
    echo '<input type="number" id="custom_max_selections" name="custom_max_selections" value="' . esc_attr($maxSelections) . '"><br>';
    echo '</div>';

    echo '</div>'; // Close the layout container
}


// Save Custom Options
add_action('save_post_product', 'save_custom_options');
function save_custom_options($post_id) {
    if (!isset($_POST['custom_options_nonce']) || !wp_verify_nonce($_POST['custom_options_nonce'], 'save_custom_options')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['custom_product_options'])) {
        $options = explode("\n", sanitize_textarea_field($_POST['custom_product_options']));
        $options = array_map('trim', $options);
        $options = array_filter($options);
        update_post_meta($post_id, '_custom_product_options', $options);
    }

    if (isset($_POST['custom_min_selections'])) {
        update_post_meta($post_id, '_custom_min_selections', intval($_POST['custom_min_selections']));
    }

    if (isset($_POST['custom_max_selections'])) {
        update_post_meta($post_id, '_custom_max_selections', intval($_POST['custom_max_selections']));
    }
}

// Display Custom Options on the Frontend
function display_custom_extra_options() {
    if (!is_product()) return;

    global $product;
    if (!$product) return;

    $options = get_post_meta($product->get_id(), '_custom_product_options', true) ?: [];
    $minSelections = get_post_meta($product->get_id(), '_custom_min_selections', true) ?: 0;
    $maxSelections = get_post_meta($product->get_id(), '_custom_max_selections', true) ?: 0;

    if (!empty($options) && is_array($options)) {
        ?>
        <h4>Aggiungi opzioni:</h4>
        <div class="extra-options-container">
    <div class="extra-options-selection">
        <label for="extra_options_select">Seleziona il Prodotto:</label>
        <select id="extra_options_select">
            <option value="">-- Seleziona il Prodotto --</option>
            <?php foreach ($options as $option) : ?>
                <option value="<?php echo esc_attr($option); ?>"><?php echo esc_html($option); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="extra-options-quantity">
        <label for="extra_options_quantity">Quantità:</label>
        <input type="number" id="extra_options_quantity" min="1" value="1">
    </div>

    <div class="extra-options-action">
        <button type="button" id="add_to_list">Aggiungi alla lista</button>
    </div>

        <p id="selection_notice" style="color: red; display: none;"></p>

        <ul id="extra_options_list" class="extra-options-list"></ul>
        <input type="hidden" name="extra_options_data" id="extra_options_data">
    </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const select = document.getElementById('extra_options_select');
                const quantityInput = document.getElementById('extra_options_quantity');
                const addButton = document.getElementById('add_to_list');
                const list = document.getElementById('extra_options_list');
                const hiddenInput = document.getElementById('extra_options_data');
                const notice = document.getElementById('selection_notice');
                const addToCartButton = document.querySelector('button.single_add_to_cart_button'); // WooCommerce Add to Cart button
                let selectedItems = [];
                const minSelections = <?php echo $minSelections; ?>;
                const maxSelections = <?php echo $maxSelections; ?>;

                // Function to update the button state and notice
                function updateButtonState() {
                    const totalQuantity = selectedItems.reduce((sum, item) => sum + item.quantity, 0);

                    if (totalQuantity === 0) {
                        // If no items are selected, allow checkout
                        addToCartButton.disabled = false;
                        addToCartButton.style.opacity = '1';
                        notice.style.display = 'none';
                    } else if (totalQuantity > 0 && totalQuantity < minSelections) {
                        // If at least 1 item is selected but not enough, block checkout
                        addToCartButton.disabled = true;
                        addToCartButton.style.opacity = '0.5';
                        notice.textContent = `Hai selezionato ${totalQuantity} prodotti. Devi selezionare almeno ${minSelections} prodotti per procedere.`;
                        notice.style.display = 'block';
                    } else if (totalQuantity > maxSelections) {
                        // If too many items are selected, block checkout
                        addToCartButton.disabled = true;
                        addToCartButton.style.opacity = '0.5';
                        notice.textContent = `Hai selezionato ${totalQuantity} prodotti. Il massimo consentito è ${maxSelections}.`;
                        notice.style.display = 'block';
                    } else {
                        // If within the allowed range, allow checkout
                        addToCartButton.disabled = false;
                        addToCartButton.style.opacity = '1';
                        notice.style.display = 'none';
                    }
                }

                // Function to update the list and hidden input
                function updateList() {
                    list.innerHTML = '';
                    selectedItems.forEach((item, index) => {
                        const listItem = document.createElement('li');
                        listItem.textContent = `${item.option} x ${item.quantity}`;
                        const removeButton = document.createElement('button');
                        removeButton.textContent = 'Rimuovi';
                        removeButton.style.marginLeft = '10px';
                        removeButton.addEventListener('click', function() {
                            selectedItems.splice(index, 1);
                            updateList();
                            updateButtonState();
                        });
                        listItem.appendChild(removeButton);
                        list.appendChild(listItem);
                    });
                    hiddenInput.value = JSON.stringify(selectedItems);
                }

                // Add item to the list
                addButton.addEventListener('click', function() {
                    const selectedOption = select.value;
                    const quantity = parseInt(quantityInput.value);

                    if (selectedOption && quantity > 0) {
                        const totalQuantity = selectedItems.reduce((sum, item) => sum + item.quantity, 0);
                        
                        if (totalQuantity + quantity > maxSelections) {
                            notice.textContent = `La quantità totale non può superare ${maxSelections}.`;
                            notice.style.display = 'block';
                            return;
                        }

                        let existingItem = selectedItems.find(item => item.option === selectedOption);
                        if (existingItem) {
                            existingItem.quantity += quantity;
                        } else {
                            selectedItems.push({ option: selectedOption, quantity: quantity });
                        }

                        updateList();
                        updateButtonState();
                        select.value = '';
                        quantityInput.value = 1;
                    }
                });

                // Block Add to Cart button if conditions are not met
                addToCartButton.addEventListener('click', function(e) {
                    const totalQuantity = selectedItems.reduce((sum, item) => sum + item.quantity, 0);

                    if (totalQuantity > 0 && (totalQuantity < minSelections || totalQuantity > maxSelections)) {
                        e.preventDefault();
                        if (totalQuantity < minSelections) {
                            notice.textContent = `Hai selezionato ${totalQuantity} prodotti. Devi selezionare almeno ${minSelections} prodotti per procedere.`;
                        } else if (totalQuantity > maxSelections) {
                            notice.textContent = `Hai selezionato ${totalQuantity} prodotti. Il massimo consentito è ${maxSelections}.`;
                        }
                        notice.style.display = 'block';
                    }
                });

                // Initial button state
                updateButtonState();
            });
        </script>
        <?php
    }
}
add_action('woocommerce_before_add_to_cart_button', 'display_custom_extra_options');

// Save Extra Options to Cart Item Data
add_filter('woocommerce_add_cart_item_data', 'save_extra_options_to_cart_item_data', 10, 3);
function save_extra_options_to_cart_item_data($cart_item_data, $product_id, $variation_id) {
    if (isset($_POST['extra_options_data']) && !empty($_POST['extra_options_data'])) {
        $extra_options = json_decode(stripslashes($_POST['extra_options_data']), true);
        
        if (is_array($extra_options)) {
            $cart_item_data['extra_options'] = $extra_options;
        }
    }
    return $cart_item_data;
}

// Display Extra Options in Cart
add_filter('woocommerce_get_item_data', 'display_extra_options_in_cart', 10, 2);
function display_extra_options_in_cart($item_data, $cart_item) {
    if (isset($cart_item['extra_options']) && !empty($cart_item['extra_options'])) {
        $display = [];
        foreach ($cart_item['extra_options'] as $option) {
            if (isset($option['option']) && isset($option['quantity'])) {
                $display[] = esc_html($option['option']) . ' x ' . esc_html($option['quantity']);
            }
        }
        $item_data[] = array(
            'key'   => __('Aggiungi opzioni', 'your-text-domain'),
            'value' => implode(', ', $display),
        );
    }
    return $item_data;
}

// Save Extra Options with Order
add_action('woocommerce_checkout_create_order_line_item', 'save_extra_options_with_order', 10, 4);
function save_extra_options_with_order($item, $cart_item_key, $values, $order) {
    if (isset($values['extra_options']) && !empty($values['extra_options'])) {
        $extra_options_list = [];

        foreach ($values['extra_options'] as $option) {
            $extra_options_list[] = esc_html($option['option']) . ' x ' . esc_html($option['quantity']);
        }

        if (!empty($extra_options_list)) {
            // Check if extra options are already set to avoid duplication
            if (!$item->get_meta('Extra Options')) {
                $item->add_meta_data(__('Aggiungi opzioni', 'your-text-domain'), implode(', ', $extra_options_list));
            }
        }
    }
}

// Display Extra Options in Order Details
add_action('woocommerce_order_item_meta_start', 'display_extra_options_in_order_details', 10, 3);
function display_extra_options_in_order_details($item_id, $item, $order) {
    // Get extra options from order item meta
    $extra_options = $item->get_meta('Extra Options');

    // Check if extra options exist and only display once
    if (!empty($extra_options) && !is_array($extra_options)) {
        echo '<div><strong>' . __('Aggiungi opzioni', 'your-text-domain') . ':</strong> ' . esc_html($extra_options) . '</div>';
    }
}


<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// Enqueue Parent Action
function chld_thm_cfg_locale_css( $uri ){
    if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
        $uri = get_template_directory_uri() . '/rtl.css';
    return $uri;
}
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );

function child_theme_configurator_css() {
    wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( 'astra-theme-css','woocommerce-layout','woocommerce-smallscreen','woocommerce-general' ) );
}
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );

// Mostrar el ID, Precio del producto y Adicionales seleccionados en la página de producto individual
add_action('woocommerce_single_product_summary', 'mostrar_product_id_precio_y_adicionales', 20);

function mostrar_product_id_precio_y_adicionales() {
    global $product;
    if (is_product()) {
        echo '<p>ID del Producto: <span id="product-id">' . $product->get_id() . '</span></p>';
        echo '<p>Precio del Producto: <span id="product-price">S/ ' . number_format($product->get_price(), 2) . '</span></p>';
        echo '<div id="selected-adicionales">
                <h4>Selecciona tus Productos Adicionales:</h4>
                <ul id="adicionales-list"></ul>
              </div>';
    }
}


// JavaScript para acordeón y botón "IR A PAGAR"
add_action('wp_enqueue_scripts', 'mi_script_acordeon');

function mi_script_acordeon() {
    wp_enqueue_script('jquery-ui-accordion');
    wp_enqueue_script('jquery');
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');

    wp_add_inline_script('jquery-ui-accordion', '
        jQuery(document).ready(function($) {
            console.log("Script cargado"); // Depuración

            // Acordeón de productos adicionales
            $("#additional-products-accordion").accordion({
                header: "h3",
                collapsible: true,
                active: false
            });

            // Función para actualizar el total
            var updateTotal = function() {
                var totalAdicionales = 0;
                var productPriceText = $("#product-price").text().replace(/[^0-9.-]+/g, "");
                var productPrice = parseFloat(productPriceText);
                var quantity = parseInt($("#product-quantity").val(), 10);

                if (isNaN(productPrice)) {
                    productPrice = 0;
                }
                
                if (isNaN(quantity) || quantity < 1) {
                    quantity = 1;
                }

                $("#adicionales-list").empty();
                $(".additional-options input[type=number], .fries-options input[type=number], .drink-options input[type=number]").each(function() {
                    var addonQuantity = parseInt($(this).val(), 10);
                    if (isNaN(addonQuantity) || addonQuantity < 0) {
                        addonQuantity = 0;
                    }

                    if (addonQuantity > 0) {
                        totalAdicionales += parseFloat($(this).data("price")) * addonQuantity;
                        var addonName = $(this).parent().text().split("-")[0].trim();
                        $("#adicionales-list").append("<li>" + addonName + ": " + addonQuantity + "</li>");
                    }
                });

                var total = (productPrice * quantity) + totalAdicionales;
                $("#button-total").text(total.toFixed(2));
            };

            // Actualizar el total cuando cambie la cantidad o los adicionales
            $("#product-quantity").on("input", updateTotal);
            $(".additional-options input[type=number], .fries-options input[type=number], .drink-options input[type=number]").on("input", updateTotal);
            updateTotal();

            // Manejar el clic en el botón "Ir a Pagar"
            $(".go-to-checkout").on("click", function(event) {
                event.preventDefault();
                var quantity = parseInt($("#product-quantity").val(), 10);
                if (isNaN(quantity) || quantity < 1) {
                    quantity = 1;
                }
                var productId = $("#product-id").text();
                var adicionales = [];
                $(".additional-options input[type=number], .fries-options input[type=number], .drink-options input[type=number]").each(function() {
                    var addonQuantity = parseInt($(this).val(), 10);
                    if (addonQuantity > 0) {
                        adicionales.push({
                            product_id: $(this).data("product-id"),
                            quantity: addonQuantity
                        });
                    }
                });
                $.post("' . esc_url(admin_url('admin-ajax.php')) . '", {
                    action: "agregar_productos_al_carrito",
                    product_id_principal: productId,
                    quantity: quantity,
                    adicionales: adicionales
                }, function(response) {
                    if (response.success) {
                        window.location.href = "' . esc_url(wc_get_checkout_url()) . '";
                    } else {
                        console.log("Error en la respuesta del servidor:", response);
                    }
                });
            });
        });
    ');
}



// Manejar la solicitud AJAX y agregar productos al carrito
add_action('wp_ajax_agregar_productos_al_carrito', 'agregar_productos_al_carrito');
add_action('wp_ajax_nopriv_agregar_productos_al_carrito', 'agregar_productos_al_carrito');

function agregar_productos_al_carrito() {
    if (isset($_POST['product_id_principal']) && !empty($_POST['product_id_principal'])) {
        $product_id_principal = intval($_POST['product_id_principal']);
        $quantity_principal = 1; // O la cantidad deseada del producto principal

        // Añadir el producto principal al carrito
        $result_principal = WC()->cart->add_to_cart($product_id_principal, $quantity_principal);

        // Mensaje de depuración para producto principal
        if ($result_principal) {
            error_log('Producto principal añadido al carrito: ' . $product_id_principal . ' Cantidad: ' . $quantity_principal);
        } else {
            error_log('Error al añadir el producto principal: ' . $product_id_principal);
        }

        // Añadir productos adicionales al carrito si existen
        $adicionales_result = [];
        if (isset($_POST['adicionales']) && !empty($_POST['adicionales'])) {
            $adicionales = $_POST['adicionales'];
            foreach ($adicionales as $adicional) {
                $product_id = intval($adicional['product_id']);
                $quantity = intval($adicional['quantity']);
                $result_adicional = WC()->cart->add_to_cart($product_id, $quantity);

                // Mensaje de depuración para adicionales
                if ($result_adicional) {
                    $adicionales_result[] = [
                        'status' => 'success',
                        'product_id' => $product_id,
                        'quantity' => $quantity
                    ];
                    error_log('Producto adicional añadido al carrito: ' . $product_id . ' Cantidad: ' . $quantity);
                } else {
                    $adicionales_result[] = [
                        'status' => 'error',
                        'product_id' => $product_id
                    ];
                    error_log('Error al añadir el producto adicional: ' . $product_id);
                }
            }
        }
    }
    // Enviar una respuesta JSON válida
    wp_send_json_success([
        'principal' => [
            'status' => $result_principal ? 'success' : 'error',
            'product_id' => $product_id_principal
        ],
        'adicionales' => $adicionales_result
    ]);
}

// TABLA DE RESUMEN

// Oculta la tabla de resumen de woocommerce
add_action('wp_head', 'ocultar_tabla_estandar_woocommerce');

function ocultar_tabla_estandar_woocommerce() {
    echo '<style>
        .woocommerce-checkout-review-order-table {
            display: none;
        }
    </style>';
}


function es_producto_adicional($product_id) {
    $productos_adicionales = [
        25, 26, 28, 29, 23, 22, 24, 27, 21, // AUMENTA TU PEDIDO
        30, 32, 31, 33, // BEBIDAS
        117, 116 // PAPAS FRITAS
    ];

    return in_array($product_id, $productos_adicionales);
}


// Mostrar el resumen de adicionales en la página de finalizar compra
add_action('woocommerce_review_order_before_payment', 'mostrar_resumen_adicionales_checkout_personalizado', 20);

function mostrar_resumen_adicionales_checkout_personalizado() {
    // Obtener los datos del carrito
    $cart = WC()->cart->get_cart();
    $subtotal = 0;

    echo '<div style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px;">';
    echo '<table style="width: 100%; border-collapse: collapse;">';
    echo '<tr style="border-bottom: 1px solid #ddd;">';
    echo '<th style="text-align: left; padding: 8px;">Producto</th>';
    echo '<th style="text-align: right; padding: 8px;">Subtotal</th>';
    echo '</tr>';

    // Variable para verificar si ya se han mostrado los adicionales
    $mostro_adicionales = false;

    // Mostrar productos principales y sus adicionales
    foreach ($cart as $cart_item) {
        $product = $cart_item['data'];
        $product_id = $product->get_id();
        $product_name = $product->get_name();
        $product_subtotal = $cart_item['line_total'];
        $subtotal += $product_subtotal;

        // Mostrar adicionales si es un producto adicional
        if (es_producto_adicional($product_id)) {
            if (!$mostro_adicionales) {
                echo '<tr style="border-bottom: 1px solid #ddd;">';
                echo '<td style="padding: 8px;" colspan="2"><strong style="font-size: 0.9rem;">Adicionales:</strong></td>';
                echo '</tr>';
                $mostro_adicionales = true;
            }
            echo '<tr>';
            echo '<td style="padding: 8px 8px 8px 16px; font-size: 0.9rem; font-weight: 400;">' . $product_name . '</td>';
            echo '<td style="text-align: right; padding: 8px; font-size: 0.9rem; font-weight: 400;">' . wc_price($product_subtotal) . '</td>';
            echo '</tr>';
        } else {
            // Mostrar producto principal
            echo '<tr style="border-bottom: 1px solid #ddd;">';
            echo '<td style="padding: 8px;">' . $product_name . '</td>';
            echo '<td style="text-align: right; padding: 8px;">' . wc_price($product_subtotal) . '</td>';
            echo '</tr>';
        }
    }

    // Mostrar subtotal y total
    echo '<tr style="border-top: 1px solid #ddd;">';
    echo '<td style="padding: 8px;"><strong>Subtotal</strong></td>';
    echo '<td style="text-align: right; padding: 8px;">' . wc_price($subtotal) . '</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td style="padding: 8px;"><strong>TOTAL</strong></td>';
    echo '<td style="text-align: right; padding: 8px;">' . wc_price(WC()->cart->get_total('float')) . '</td>';
    echo '</tr>';
    echo '</table>';
    echo '</div>';
}


// END ENQUEUE PARENT ACTION

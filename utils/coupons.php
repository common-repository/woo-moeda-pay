<?php

function get_coupons($order_id) {
  global $woocommerce;
  $order = wc_get_order($order_id);
  // GET THE ORDER COUPON ITEMS
  $order_coupons = $order->get_items('coupon');
  $coupons = array();
  $general_discount = 0;
  $product_discounts = array();
  // LOOP THROUGH ORDER COUPON ITEMS
  foreach ( $order_coupons as $item_id => $item ){

      // Retrieving the coupon ID reference
      $coupon_post_obj = get_page_by_title( $item->get_name(), OBJECT, 'shop_coupon' );
      $coupon_id = $coupon_post_obj->ID;
      $coupon = new WC_Coupon($coupon_id);
      $amount = floatval($coupon->get_amount());
      $coupon_type = $coupon->get_discount_type();
      
      if ( $coupon_type == 'fixed_product' ) {
        // get product
        $product_items = $order->get_items();
        $coupon_products = $coupon->get_product_ids();
        foreach ($product_items as $p_id => $p_item) {
          $product_id = $p_item->get_product_id();
          
          // Example: we use product_ids authorized in the coupon $cp_products
          if (in_array($product_id, $coupon_products)) {
              $productId = get_post_meta( $product_id, '_moedapay_marketplace_product_id', true );
              $product_discounts[] = array(
                  'productId' => $productId,
                  'discountType' => $coupon_type,
                  'amount' => floatval($amount) * intval($p_item->get_quantity())
              );
          }
        }
      } elseif ( $coupon_type == 'percent' ) {
        $general_discount += round(floatval($order->get_subtotal()) * floatval($amount) * 0.01, 2, PHP_ROUND_HALF_DOWN);
      } elseif ( $coupon_type == 'fixed_cart' ) {
        $general_discount += floatval($amount);
      }

      // Get the Coupon discount amounts in the order
      $coupons[] = array(
        'code' => $coupon->get_code(),
        'type' => $coupon_type,
        'amount' => $amount
      );
  }
  return array(
    'general_discount' => $general_discount,
    'coupons' => $coupons,
    'product_discounts' => $product_discounts
  );
}
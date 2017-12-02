<?php
/*
Plugin name: woocommerce-installment-gateway
Author: k1sul1
Author Email: me@kisu.li
*/

if (!defined("ABSPATH")) {
  die("No.");
}

add_action("plugins_loaded", function() {
  require_once "classes/class.wc-installments-gateway.php";

  add_filter("woocommerce_payment_gateways", function($methods) {
    $methods[] = "WC_Installments_Gateway_k1sul1";

    return $methods;
  });
});

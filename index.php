<?php
/*
Plugin name: Installment payment gateway for WooCommerce
Version: 1.0
Author: k1sul1
Author Email: me@kisu.li
*/

if (!defined("ABSPATH")) {
  die("No.");
}

add_action("plugins_loaded", function() {
  // require_once "classes/class.wc-installments-gateway.php";
  require_once "vendor/autoload.php";

  add_filter("woocommerce_payment_gateways", function($methods) {
    $methods[] = "WC_Installments_Gateway_k1sul1";

    return $methods;
  });

  add_action("woocommerce_api_callback", "k1sul1_wcigw_callback_handler");
  add_action("k1sul1-wcigw-callback", "k1sul1_wcigw_demo_callback");

  add_action("rest_api_init", function() {
    register_rest_route("wcigw/v1", "/process_payment", [
      "methods" => "POST",
      "callback" => function($response) {
        $order_id = isset($_POST["order_id"]) ? $_POST["order_id"] : null;
        $identifier = isset($_POST["identifier"]) ? $_POST["identifier"] : null;

        if ($order_id && $identifier) {
          return [
            "status" => "success",
          ];
        }

        return [
          "status" => "error",
          "message" => "Invalid data.",
        ];
      },
    ]);
  });
});

function k1sul1_wcigw_demo_callback($order) {
  wp_remote_post(get_site_url() . "/wc-api/CALLBACK", [
    "body" => [
      "order_id" => $order->get_id(),
      "order_key" => $order->get_order_key(), // Can a potential attacker generate this? Shouldn't be possible.
      "status" => "success",
    ],
    "sslverify" => defined("WP_DEBUG") ? !WP_DEBUG : true, // don't verify cert in development
  ]);
}

function k1sul1_wcigw_callback_action($response) {
  // If these values do not exist in the response, or they're not "fit" for this code,
  // you are to filter the response so this works as you want. You may also remove this with
  // remove_action("k1sul1-wcigw-callback-action", "k1sul1_wcigw_callback_action");

  $order_id = isset($response["order_id"]) ? (int) $response["order_id"] : null;
  $order_key = isset($response["order_key"]) ? $response["order_key"] : null;
  $status = isset($response["status"]) ? sanitize_text_field($response["status"]) : null;
  $order = wc_get_order($order_id);

  if (!$order_id || !$order_key || !$status || !$order) {
    return false;
  }

  $condition = apply_filters(
    "k1sul1-wcigw-callback-action-condition",
    $order_key === $order->get_order_key(),
    $response,
    $order
  );

  if ($condition) {
    switch ($status) {
      case "success":
        $order->payment_complete();
        if (function_exists("wc_reduce_order")) {
          wc_reduce_stock_levels($order->get_id());
        } else {
          $order->reduce_order_stock();
        }
      break;
    }

    do_action("k1sul1-wcigw-callback-action_complete", $order, $status);
    die("Order complete.");
  } else {
    die("Invalid.");
  }
}

function k1sul1_wcigw_callback_handler() {
    $response = apply_filters("k1sul1-wcigw-callback-response", $_POST);

    add_action("k1sul1-wcigw-callback-action", "k1sul1_wcigw_callback_action");
    do_action("k1sul1-wcigw-callback-action", $response);
  }


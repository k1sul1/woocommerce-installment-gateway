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
  // require_once "classes/class.wc-installments-gateway.php";
  require_once "vendor/autoload.php";

  add_filter("woocommerce_payment_gateways", function($methods) {
    $methods[] = "WC_Installments_Gateway_k1sul1";

    return $methods;
  });

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

function k1sul1_wcigw_callback_action($response) {
  // If these values do not exist in the response, or they're not "fit" for this code,
  // you are to filter the response so this works as you want. You may also remove this with
  // remove_action("k1sul1-wcigw-callback-action", "k1sul1_wcigw_callback_action");

  $order_id = isset($response["order_id"]) ? (int) $response["order_id"] : null;
  $status = isset($response["status"]) ? sanitize_text_field($response["status"]) : null;

  if ($order_id === null || $status === null || $order_id === 0) { // non numeric values are cast into int and 0
    return false;
  }

  $order = new WC_Order($order_id);

  switch ($status) {
    case "success":
      $order->payment_complete();
    break;
  }
}


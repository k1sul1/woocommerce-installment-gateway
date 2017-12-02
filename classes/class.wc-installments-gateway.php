<?php
class WC_Installments_Gateway_k1sul1 extends \WC_Payment_Gateway {
  public function  __construct() {
    $options = apply_filters("k1sul1-installments-gateway", [
      "id" => "installments",
      "icon" => "",
      "has_fields" => true,
      "method_title" => "Installments",
      "method_description" => "Will allow you to accept payments in parts.",
    ]);

    foreach ($options as $key => $value) {
      $this->{$key} = $value;
    }

    $this->init_form_fields();
    $this->init_settings();

    $this->title = $this->get_option("title");
    $this->description = $this->get_option("description");
    $this->instructions = $this->get_option("instructions");
    $this->identifier = $this->get_option("identifier");
    $this->payment_options = $this->get_option("payment_options");

    add_action("woocommerce_update_options_payment_gateways_{$this->id}", [
      $this,
      "process_admin_options",
    ]);
  }

  public function init_form_fields() {
    $this->form_fields = [
      "enabled" => [
        "title" => __("Enable/Disable", "woocommerce"),
        "type" => "checkbox",
        "label" => __("Enable installments", "woocommerce"),
        "default" => "yes",
      ],
      "title" => [
        "title" => __("Title", "woocommerce"),
        "type" => "text",
        "description" => __("This controls the title which the user sees during checkout.", "woocommerce"),
        "default" => __("Installments", "woocommerce"),
      ],
      "description" => [
        "title" => __("Description", "woocommerce"),
        "type" => "textarea",
        "default" => "Pay your order in parts.",
      ],
      "instructions" => [
        "title"       => __("Instructions", "woocommerce"),
        "type"        => "textarea",
        "description" => __("Instructions that will be added to the thank you page and emails.", "woocommerce"),
        "default"     => "Your credit rating will be checked and we'll get in touch if there's a problem.",
      ],

      "identifier" => [
        "title" => __("Identifier", "woocommerce"),
        "type" => "text",
        "description" => __("Identifier can be a social security number or company number.", "woocommerce"),
        "default" => "Identifier",
      ],
      /* "interest" => [
        "title" => __("Interest", "woocommerce"),
        "type" => "text",
        "description" => __("Interest in percentage.", "woocommerce"),
        "default" => 7.9,
      ],
      "billing_fee" => [
        "title" => __("Billing fee", "woocommerce"),
        "type" => "text",
        "description" => __("Monthly billing fee, without a currency.", "woocommerce"),
        "default" => 7,
      ],
      "start_fee" => [
        "title" => __("Start fee", "woocommerce"),
        "type" => "text",
        "description" => __("Fee paid initially.", "woocommerce"),
        "default" => 70,
      ], */
      "payment_options" => [
        "title" => __("Payment options", "woocommerce"),
        "type" => "textarea",
        "description" => __("These options will be listed on checkout and the user may pick whichever suits them best.", "woocommerce"),
        "default" => "60 : ((%total% + 70) * 1.079 / 60) + 7 : Pay in 60 months, %installment% / month.\n10 : ((%total% + 70) * 1.079 / 10) + 7 : Pay in 10 months, %installment% / month.",
      ],
    ];
  }

  public function payment_fields() {
    echo wpautop($this->get_description()); ?>
    <label>
      <?=$this->identifier?>
      <input type="text" name="identifier">
    </label>
    <select name="selected_payment_model">
    <?php
    $options = explode("\n", $this->payment_options);
    $currency = get_woocommerce_currency();
    $price = max(
      0,
      apply_filters(
        'woocommerce_calculated_total',
        round(WC()->cart->cart_contents_total + WC()->cart->fee_total + WC()->cart->tax_total, WC()->cart->dp ), WC()->cart
      )
    );

    foreach ($options as $row) {
      $row = explode(":", $row);
      $parts= $row[0];
      $formula = $row[1];
      $text = $row[2];

      $formula = str_replace(
        ["%total%"],
        [$price],
        $formula
      );

      $text = str_replace(
        ["%installment%"],
        [eval("return $formula;")], // validate you fucking idiot
        $text
      );

      echo "
        <option value='$parts'>
          $text
        </option>
      ";
    }
    ?>
    </select>
    <?php
  }

  public function process_payment($order_id) {
    global $woocommerce;
    $order = new WC_Order($order_id);
    error_log(print_r($_POST, true));

    // Mark as on-hold (we"re awaiting the cheque)
    // $order->update_status("on-hold", __("Awaiting cheque payment", "woocommerce"));
    $order->payment_complete();

    // Reduce stock levels
    $order->reduce_order_stock();

    // Remove cart
    $woocommerce->cart->empty_cart();

    // Return thankyou redirect
    return [
      "result" => "success",
      "redirect" => $this->get_return_url($order)
    ];
  }
}

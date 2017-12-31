<?php
class WC_Installments_Gateway_k1sul1 extends \WC_Payment_Gateway {
  public function  __construct() {
    $options = apply_filters("k1sul1-wcigw-options", [
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
    $this->direct = $this->get_option("direct");
    $this->identifier = $this->get_option("identifier");
    $this->payment_options = $this->get_option("payment_options");

    add_action("woocommerce_update_options_payment_gateways_{$this->id}", [
      $this,
      "process_admin_options",
    ]);

    if (!$this->direct) {
      // The callback doen't work when registered here. Registered globally.
      // add_action("woocommerce_api_callback", [$this, "callback_handler"]);
    }

    add_action("woocommerce_thankyou_{$this->id}", [$this, 'thankyou_page']);
    add_action("woocommerce_email_before_order_table", [$this, "email_instructions"], 10, 3);
  }

  public function thankyou_page($order_id) {
    if ($this->instructions) {
      $order = new WC_Order($order_id);
      $model = get_post_meta($order_id, "wcigw_payment_model", true);
      $installment = array_values($this->getInstallmentOptions(null, [$model]))[0]; // Don't ask.

      echo wpautop(join(
        PHP_EOL,
        apply_filters("k1sul1-wcigw-thankyou-instructions",
          [
            wptexturize($this->instructions),
            "<h3>" . __("Your payment plan was", "woocommerce") . "</h3>",
            $this->convertInstallmentPrice(
              $this->convertFormula($installment["formula"], $order->get_total()),
              $installment["text"]
            ),
          ],
          $model,
          $order
        )
      ));
    }
  }

  public function email_instructions($order, $sent_to_admin, $plain_text = false) {
    if ($this->instructions && !$sent_to_admin && $order->get_payment_method() === $this->id) {
      $order_id = $order->get_id();
      $model = get_post_meta($order_id, "wcigw_payment_model", true);
      $installment = array_values($this->getInstallmentOptions(null, [$model]))[0]; // Don't ask.

      echo wpautop(join(
        PHP_EOL,
        apply_filters("k1sul1-wcigw-email-instructions",
          [
            wptexturize($this->instructions),
            __("Your payment plan was", "woocommerce"),
            $this->convertInstallmentPrice(
              $this->convertFormula($installment["formula"], $order->get_total()),
              $installment["text"]
            ),
          ],
          $model,
          $order
        )
      ));
    }
  }

  public function init_form_fields() {
    $this->form_fields = [
      "enabled" => [
        "title" => __("Enable/Disable", "woocommerce"),
        "type" => "checkbox",
        "label" => __("Enable installments", "woocommerce"),
        "default" => "yes",
      ],
      "direct" => [
        "title" => __("Use direct payment?", "woocommerce"),
        "type" => "checkbox",
        "label" => __("Yes", "woocommerce"),
        "default" => "yes",
        "description" => __("If unticked, callback will be used instead.
        Direct payment requires immediate knowledge on whether the payment is possible.", "woocommerce"),
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
      "payment_options" => [
        "title" => __("Payment options", "woocommerce"),
        "type" => "textarea",
        "description" => __("These options will be listed on checkout and the user may pick whichever suits them best.", "woocommerce"),
        "default" => "10 : ((%total% + 70) * 1.079 / 10) + 7 : Pay in 10 months, %installment% / month.",
      ],
    ];
  }

  public function getInstallmentOptions($key = null, $options = []) {
    $options = !empty($options) ? $options : explode("\n", $this->payment_options);
    $rows = [];

    foreach ($options as $row) {
      $full = $row;
      $row = explode(":", $row);
      $parts = trim($row[0]);
      $formula = $row[1];
      $text = $row[2];

      $rows[$parts] = compact("parts", "formula", "text", "full");

      if ($key !== null && isset($rows[$key])) {
        return $rows[$key];
      }
    }

    return $rows;
  }

  public function convertFormula($formula, $price = 0) {
    return str_replace(
      ["%total%"],
      [$price],
      $formula
    );
  }

  public function convertInstallmentPrice($formula, $text = "") {
    return str_replace(
      ["%installment%"],
      [round((new \NXP\MathExecutor())->execute($formula), 2)],
      $text
    );
  }

  public function payment_fields() {
    echo wpautop($this->get_description()); ?>
    <label>
      <?=$this->identifier?>
      <input type="text" name="identifier" required>
    </label>
    <select name="selected_payment_model">
    <?php
    $price = max(
      0,
      apply_filters(
        "woocommerce_calculated_total",
        round(WC()->cart->cart_contents_total + WC()->cart->fee_total + WC()->cart->tax_total, WC()->cart->dp ),
        WC()->cart
      )
    );

    $options = $this->getInstallmentOptions();
    foreach ($options as $row) {
      $formula = $this->convertFormula($row["formula"], $price);
      $text = $this->convertInstallmentPrice($formula, $row["text"]);

      echo "
        <option value='$row[parts]'>
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

    // if the req is legitimate, these *will* be in the req.
    $selectedModel = $this->getInstallmentOptions($_POST["selected_payment_model"]);
    $identifier = $_POST["identifier"];

    // Feel free to customize the condition.
    // You could limit purchases over 10 000$ for company identifiers only.
    $condition =  apply_filters("k1sul1-wcigw-identifier-condition", false, $identifier, $order);

    if (empty($identifier) || $condition) {
      $error = apply_filters("k1sul1-wcigw-identifier-error", __("Identifier is a mandatory field.", "woocommerce"));
      $error = " " . $error;
      wc_add_notice(__("Payment error:", "woothemes") . $error, "error");
      return;
    }

    update_post_meta($order_id, "wcigw_payment_model", $selectedModel["full"]);
    $order->add_order_note("Identifier provided was $identifier.");
    $order->add_order_note("Selected payment plan was $selectedModel[formula].");


    if ($this->direct === 'yes') { // my brain melts when I look at this
      $reqMethod = apply_filters("k1sul1-wcigw-direct-reqmethod", "wp_remote_post");
      $options = apply_filters("k1su1-wcigw-direct-reqopts", [
        "method" => $reqMethod === "wp_remote_post" ? "wp_remote_post" : $reqMethod,
        "url" => get_home_url(null, "/") . "/wp-json/wcigw/v1/process_payment",
        "args" => [
          "timeout" => 30,
          "body" => [
            "order_id" => $order_id,
            "identifier" => $identifier,
            "model" => $selectedModel,
          ],
          "sslverify" => defined("WP_DEBUG") && WP_DEBUG === true ? false : true, // WP_DEBUG is only on in dev.
        ],
      ]);

      $response = $options["method"]($options["url"], $options["args"]);

      if (is_wp_error($response)) {
        $error = $response->get_error_message();

        $order->add_order_note("Direct gateway payment failed. Error: $error");
        do_action("k1sul1-wcigw-direct-unsuccessful", $response, $order);
        wc_add_notice(__("Payment error:", "woothemes") . $error, "error");
        return;
      }

      if (isset($response["body"])) {
        $response = $response["body"];
      }
      $response = json_decode($response);

      if ($response->status === "success") {
        $order->add_order_note("Direct gateway payment successful.");
        $order->payment_complete();
        $woocommerce->cart->empty_cart();

        if (function_exists("wc_reduce_order")) {
          wc_reduce_stock_levels($order->get_id());
        } else {
          $order->reduce_order_stock();
        }
      } else {
        $error = isset($response["error"]) ? $response["error"] : "";

        $order->add_order_note("Direct gateway payment failed. Error: $error");
        do_action("k1sul1-wcigw-direct-unsuccessful", $response, $order);
        wc_add_notice(__("Payment error:", "woothemes") . $error, "error");
        return;
      }
    } else {
      // Callback will be used instead.
      $order->add_order_note(__("Awaiting financer confirmation.", "woocommerce"));
      $order->update_status("on-hold", __("Awaiting financer confirmation.", "woocommerce"));
      $woocommerce->cart->empty_cart();

      do_action("k1sul1-wcigw-callback", $order);
    }

    // Reduce stock levels

    // Return thankyou redirect
    return [
      "result" => "success",
      "redirect" => $this->get_return_url($order)
    ];
  }
}

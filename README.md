# Installment payment gateway for WooCommerce

#### If you're not code savvy, you might want to steer clear of this plugin, unless the defaults work for you.

This plugin is useful for stores that want to utilize a part time payment provider that doen't have an official gateway.

Out of the box, every single order will complete ~instantly, but there's a *lot* of hooks buried in the 400ish lines of code that this plugin has.

If you want or need to check each order manually before marking it as processing, uncheck "Use direct payment?" on the plugin settings page (/wp-admin/admin.php?page=wc-settings&tab=checkout&section=installments) **and** add this code to your location of choice.

```php
add_action("plugins_loaded", function() {
  /* Disable default payment callback action */
  remove_action("woocommerce_api_callback", "k1sul1_wcigw_callback_handler");
  remove_action("k1sul1-wcigw-callback", "k1sul1_wcigw_demo_callback");
}, 11);
```

Payment options are configured on the same options page. Don't be intimidated by the syntax.
```
60 : ((%total% + 70) * 1.079 / 60) + 7 : Pay in 60 months, %installment% / month.
```
First, there's the the part amount, how many parts will the order be paid in?

Second, there's the formula, it's up to you how you want it. The formula here takes the total order amount, and adds 70 to it, as a one time fee. The interest rate is 7.9%, so the sum is multiplied with 1.079, and then divided with 60. Each installment will cost an additional 7($), so it's added to the final sum that is displayed to the customer.

Third, there's the text that's displayed to the customer inside the payment option. %installment% contains the result of your formula.

Using this formula, if you were to buy a cat with 600$ + 5$ of shipping fees, you would pay 19.14$ / month.

Other fields on the settings page control the text displayd on the payment form.

## Contributing
Yes please!

## Licence
GPL-2.0


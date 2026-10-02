<?php
if (!function_exists('report_currency_options')) {
  function report_currency_options() {
    return array('USD', 'EUR', 'IQD', 'GBP', 'AED', 'SAR');
  }
}

if (!function_exists('report_currency_symbol')) {
  function report_currency_symbol($currency) {
    $symbols = array(
      'USD' => '$',
      'EUR' => '€',
      'IQD' => 'IQD',
      'GBP' => '£',
      'AED' => 'AED',
      'SAR' => 'SAR'
    );

    return isset($symbols[$currency]) ? $symbols[$currency] : $currency;
  }
}

if (!function_exists('report_money')) {
  function report_money($amount, $currency) {
    return number_format((float)$amount, 2) . ' ' . report_currency_symbol($currency);
  }
}

if (!function_exists('report_selected_currency')) {
  function report_selected_currency($requestKey) {
    $options = report_currency_options();
    if (isset($_POST[$requestKey])) {
      $currency = strtoupper(trim($_POST[$requestKey]));
    } elseif (isset($_GET[$requestKey])) {
      $currency = strtoupper(trim($_GET[$requestKey]));
    } else {
      $currency = 'USD';
    }

    if (!in_array($currency, $options)) {
      $currency = 'USD';
    }

    return $currency;
  }
}

if (!function_exists('report_h')) {
  function report_h($value) {
    return htmlentities((string)$value, ENT_QUOTES, 'UTF-8');
  }
}
?>

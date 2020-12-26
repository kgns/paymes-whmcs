<?php

require_once __DIR__ . '/../../../init.php';
App::load_function('gateway');
App::load_function('invoice');

$gatewayModuleName = basename(__FILE__, '.php');

$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Paymes return post data
$invoiceId = $_POST['id'];
$transactionId = $_POST["payuPaymentReference"];
$paymentAmount1 = $_POST["amount"];
$paymentFee = 0;
$currency = $_POST["currency"];
$message = $_POST["message"];
$success = $_POST['status'] == '3DS_ENROLLED';

/**
 * If useInvoiceAmountAsPaid enabled, use the invoice amount, 
 * as paid amount this to avoid currency conversion issue on non-Default WHMCS Currency transaction
 */

if ($gatewayParams['useInvoiceAmountAsPaid'] == 'on') {
  $invoice_result = mysql_fetch_assoc(select_query('tblinvoices', 'total, userid', array("id"=>$order_id)));
  $invoice_amount = $invoice_result['total'];
  $paymentAmount = $invoice_amount;
}

/**
 * If tryToConvertCurrencyBack enabled
 * Try to convert amount back to Default WHMCS Currency, if not Default WHMCS Currency
 */
if ($gatewayParams['tryToConvertCurrencyBack'] == 'on') {
  try {
    $invoice_result = mysql_fetch_assoc(select_query('tblinvoices', 'total, userid', array("id"=>$order_id))); 
    $invoice_amount = $invoice_result['total'];
    $client_result = mysql_fetch_assoc(select_query('tblclients', 'currency', array("id"=>$invoice_result['userid'])));
    $currency_id = $client_result['currency'];
    $idr_currency_id = $gatewayParams['convertto'];
    if($currency_id != $idr_currency_id) {
        $converted_amount = convertCurrency(
          $paymentAmount1, 
          $idr_currency_id, 
          $currency_id
        );
    } else {
        $converted_amount = $paymentAmount1;
    }
    $paymentAmount = $converted_amount;
  } catch (Exception $e) {
    echo "fail to tryToConvertCurrencyBack";
  }
}

$transactionStatus = $success ? 'Success' : 'Failure';

$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

checkCbTransID($transactionId);

logTransaction($gatewayParams['paymentmethod'], $_POST, $transactionStatus);

$paymentSuccess = false;

if ($success) {

    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $paymentAmount,
        $paymentFee,
        $gatewayModuleName
    );

    $paymentSuccess = true;

}

callback3DSecureRedirect($invoiceId, $paymentSuccess);

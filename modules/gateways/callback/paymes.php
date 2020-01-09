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
$paymentAmount = $_POST["amount"];
$paymentFee = 0;
$currency = $_POST["currency"];
$message = $_POST["message"];
$success = $_POST['status'] == '3DS_ENROLLED';

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

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

/**
 * bu kodun calismasi icin TL'nin whmcs'de varsayilan para birimi "olmamasi" gerek. (not: paymes api basvurum onaylanmadigi icin henuz test edemedim)
 */
$result = mysql_fetch_assoc(select_query('tblinvoices', 'total, userid', array("id"=>$invoiceId))); // faturanin tutarini ve musteri id'sini alalım.
$amount = $result['total'];
# paymes'den gelen tutar fatura'daki tutar ile ayni mi? degilse whmcs'deki varsayilan para birimine cevirelim.
//$currency = getCurrency();
$result = mysql_fetch_assoc(select_query('tblclients', 'currency', array("id"=>$result['userid']))); // musterinin para birimini aldik
$currency_id = $result['currency'];
$result = mysql_fetch_array(select_query("tblcurrencies", "id", array("id"=>1))); // whmcs'deki varsayilan para birimin id'sini aldik.
$default_id = $result['id'];
if($currency_id != $default_id) { // musterinin para birimi ile whmcs'deki varsayilan para birimi ayni degilse
    $converted_amount = convertCurrency($amount, $currency_id, $default_id); // gelen tutari whmcs'deki varsayilan para birimine cevirdik
    $paymentAmount = $converted_amount; // whmcs gelen tutari dogru bir sekilde faturaya yazsin diye degiskene atadik.
} else {
    $converted_amount = $amount; // musterinin para birimi ile whmcs'deki varsayilan para birimi zaten ayniymis.
    $paymentAmount = $converted_amount; // whmcs gelen tutari dogru bir sekilde faturaya yazsin diye degiskene atadik.
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

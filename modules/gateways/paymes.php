<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function paymes_MetaData()
{
    return array(
        'DisplayName' => 'Paymes Sanal POS',
        'APIVersion' => '1.1',
    );
}

function paymes_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Paymes Sanal POS',
        ),
        'secretKey' => array(
            'FriendlyName' => 'Secret Key',
            'Type' => 'password',
            'Size' => '25',
            'Default' => '',
            'Description' => 'paym.es\'in verdiği size özel kodu (Secret Key) buraya girin',
        ),
	'useInvoiceAmountAsPaid' => array(
            'FriendlyName' => 'Fatura tutarını ödenen tutar olarak kullan',
            'Type' => 'yesno',
            'Description' => 'Bunu yalnızca varsayılan para biriminiz TL değilken ve ödenen faturada tutar uyuşmazlığı sorunuyla karşılaşırsanız kullanın, bu seçenek, ödenen tutar olarak fatura tutarını kullanır (önerilen ayar: kapalı)',
        ),
        'tryToConvertCurrencyBack' => array(
            'FriendlyName' => 'Try convert back paid amount currency',
            'Type' => 'yesno',
            'Description' => 'Bunu yalnızca varsayılan para biriniz TL değilken ve ödenen faturada tutar uyuşmazlığı sorunuyla karşılaşırsanız kullanın, bu seçenek, orijinal fatura para birimi tutarına geri çevirir. (önerilen ayar: kapalı)',
        ),
    );
}

function paymes_capture($params)
{
}

function paymes_3dsecure($params)
{
    // Paymes Secret Key
    $secretKey = $params['secretKey'];
    $useInvoiceAmountAsPaid = $params['useInvoiceAmountAsPaid'];
    $tryToConvertCurrencyBack = $params['tryToConvertCurrencyBack'];

    // Fatura bilgileri
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Kredi kartı bilgileri
    $cardNumber = $params['cardnum'];
    $cardExpiry = $params['cardexp'];
    $cardCvv = $params['cccvv'];

    // Müşteri bilgileri
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];
	$ip = json_decode($params['clientdetails']['model'], true)['ip'];

    // Sistem parametreleri
    $companyName = $params['companyname'];
    $moduleName = $params['paymentmethod'];
    $langPayNow = $params['langpaynow'];

	if(!empty($address2)) {
		$address1 .= " " . $address2;
	}

	if(empty($city)) {
		$city = $params['clientdetails']['state'];
	}

	if (empty($ip)) {
		$ip = getRandomIP();
	}

	// Paymes API inputları
	$data = array(
		"secret" => $secretKey,
		"operationId" => $invoiceId,
		"number" => $cardNumber,
		"installmentsNumber" => 1,
		"expiryMonth" => substr($cardExpiry, 0, 2),
		"expiryYear" => substr($cardExpiry, -2, 2),
		"cvv" => $cardCvv,
		"owner" => $firstname . " " . $lastname,
		"billingFirstname" => $firstname,
		"billingLastname" => $lastname,
		"billingEmail" => $email,
		"billingPhone" => $phone,
		"billingCountrycode" => $country,
		"billingAddressline1" => $address1,
		"billingCity" => $city,
		"deliveryFirstname" => $firstname,
		"deliveryLastname" => $lastname,
		"deliveryPhone" => $phone,
		"deliveryAddressline1" => $address1,
		"deliveryCity" => $city,
		"clientIp" => $ip,
		"productName" => $description,
		"productSku" => $invoiceId,
		"productQuantity" => 1,
		"productPrice" => $amount,
		"currency" => $currencyCode,
	);

	$curl = curl_init();

	curl_setopt_array($curl, array(
		CURLOPT_URL => "https://web.paym.es/api/authorize",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_POST => "1",
		CURLOPT_POSTFIELDS => http_build_query($data),
		CURLOPT_HTTPHEADER => array(
			"Content-Type: application/x-www-form-urlencoded"
		),
	));

	$response = curl_exec($curl);
	curl_close($curl);

	$response = json_decode($response, true);

	unset($data['secret']);
	unset($data['cvv']);
	maskCC($data['number']);

	$logdata['request'] = $data;
	$logdata['response'] = $response;

	if ($response['status'] == "ERROR" || $response['code'] != "200") {
		logTransaction($moduleName, $logdata, 'declined');
		return 'declined';
	}
	else if ($response['status'] == "SUCCESS") {
		if ($response['message'] == '3DS Enrolled Card.') {
			$url = $response['paymentResult']['url'];
			if (!empty($url)) {
				logTransaction($moduleName, $logdata, 'Pending');

				$htmlOutput = '<form method="post" action="' . $url . '">';
				$htmlOutput .= '<input type="submit" value="' . $langPayNow . '" />';
				$htmlOutput .= '</form>';

				return $htmlOutput;
			} else {
				logTransaction($moduleName, $logdata, 'Failure');
				return 'declined';
			}
		} else if ($response['message'] == 'Authorized') {
			logTransaction($moduleName, $logdata, 'Success');
			return 'success';
		}
	}
	logTransaction($moduleName, $logdata, 'Failure');
	return 'declined';
}

function maskCC(&$cardNumber) {
    $length = strlen($cardNumber);
    $cardNumber = substr_replace($cardNumber, str_repeat('*', $length - 4), 0, $length - 4);
}

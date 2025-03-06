<?php
use WHMCS\Database\Capsule;
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../includes/invoicefunctions.php';
$gatewayParams = getGatewayVariables('tabapay');

if (!empty($_SERVER['HTTP_AUTHORIZE']) && md5($gatewayParams['MerchantID']) == $_SERVER['HTTP_AUTHORIZE']) {
	$responseData = $_POST;
	if ($responseData['status'] == "success" && $responseData['responseCode'] == 1) {
		$invoice = Capsule::table('tblinvoices')->where('notes', $responseData['token'])->where('status', 'Unpaid')->first();
		checkCbTransID($responseData['trackingCode']);
		logTransaction($gatewayParams['name'], $responseData, 'Success');
		addInvoicePayment(
			$invoice->id,
			$responseData['trackingCode'],
			$invoice->total,
			0,
			'tabapay'
		);
		echo (json_encode(['status' => 'success']));
	}
}
		
if ($_REQUEST['invoiceId'] || $_GET['token']) {
	if (!empty($_GET['status'])) {
		
		$invoice = Capsule::table('tblinvoices')->where('notes', $_REQUEST['token'])->where('status', 'Unpaid')->first();
		if (!$invoice) {
			die("Invoice not found");
		}
		
		elseif (!empty($_GET['status']) && $_GET['status'] == "success" && $_GET['responseCode'] == 1) {
	//            $amount = ceil($invoice->total * ($gatewayParams['currencyType'] == 'IRT' ? 10 : 1));
	
			$maxAttempts = 3;
			$attempt = 0;
			$responseData = null;
			
			while ($attempt < $maxAttempts && (empty($responseData['status']))) {
				$responseData = VerifyTransaction($_GET['token'], $_GET['amount'], $gatewayParams['MerchantID']);
				$attempt++;
			}

			if ($responseData['status'] == "success" && $responseData['responseCode'] == 1) {
				checkCbTransID($responseData['trackingCode']);
				logTransaction($gatewayParams['name'], $_REQUEST, 'Success');
				addInvoicePayment(
					$invoice->id,
					$responseData['trackingCode'],
					$invoice->total,
					0,
					'tabapay'
				);
			} else {
				logTransaction($gatewayParams['name'], array(
					'Code' => $responseData['responseCode'],
					'Message' => $responseData['message'],
					'Transaction' => $_GET['trackingCode'],
					'Invoice' => $invoice->id,
					'Amount' => $invoice->total,
				), 'Failure');
			}
		} else {
			logTransaction($gatewayParams['name'], array(
				'Code' => $_GET['responseCode'],
				'Message' => $_GET['message'],
				'Transaction' => $_GET['trackingCode'],
				'Invoice' => $invoice->id,
				'Amount' => $invoice->total,
			), 'Failure');
		}

		if (!isset($_SESSION["uid"]))
			$_SESSION["uid"] = $invoice->userid;

		header('Location: ' . $gatewayParams['systemurl'] . 'viewinvoice.php?id=' . $invoice->id);
	} else if (isset($_SESSION['uid'])) {
		$invoice = Capsule::table('tblinvoices')->where('id', $_REQUEST['invoiceId'])->where('status', 'Unpaid')->where('userid', $_SESSION['uid'])->first();
		if (!$invoice) {
			die("Invoice not found");
		}
		$client = Capsule::table('tblclients')->where('id', $_SESSION['uid'])->first();
		$amount = ceil($invoice->total * ($gatewayParams['currencyType'] == 'IRT' ? 10 : 1));

		$data = array(
			'amount' => $amount,
			'description' => sprintf('پرداخت فاکتور #%s', $invoice->id),
			'email' => $client->email,
	//            'mobile' => $client->phonenumber,
			'additionalData' => json_encode(['invoiceId' => $invoice->id]),
			'callbackURL' => $gatewayParams['systemurl'] . 'modules/gateways/tabapay.php',
		);
		$responseData = CreateTransaction($data, $gatewayParams['MerchantID']);
		if (!empty($responseData) && $responseData['status'] == "success" && !empty($responseData['url'])) {
			Capsule::table('tblinvoices')->where('id', $_REQUEST['invoiceId'])->where('status', 'Unpaid')->where('userid', $_SESSION['uid'])->update(['notes'=>$responseData['token']]);
			header('Location: ' . $responseData['url']);
		} else {
			echo 'اتصال به درگاه امکان پذیر نیست: ', $responseData['message'];
		}
	}
}


//if (!defined('WHMCS')) {
//    die('This file cannot be accessed directly');
//}


function tabapay_MetaData()
{
    return array(
        'DisplayName' => 'Tabapay Gateway',
        'APIVersion' => '1.0',
    );
}

function tabapay_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'تاباپی',
        ),
        'currencyType' => array(
            'FriendlyName' => 'نوع ارز',
            'Type' => 'dropdown',
            'Options' => array(
                'IRR' => 'ریال',
                'IRT' => 'تومان',
            ),
        ),
        'MerchantID' => array(
            'FriendlyName' => 'مرچنت کد',
            'Type' => 'text',
            'Size' => '255',
            'Default' => '',
            'Description' => 'مرچنت کد دریافتی از سایت تاباپی',
        ),
        'testMode' => array(
            'FriendlyName' => 'حالت تستی',
            'Type' => 'yesno',
            'Description' => 'برای فعال کردن حالت تستی تیک بزنید',
        ),
    );
}

function tabapay_link($params)
{
    $htmlOutput = '<form method="GET" action="modules/gateways/tabapay.php">';
    $htmlOutput .= '<input type="hidden" name="invoiceId" value="' . $params['invoiceid'] . '">';
    $htmlOutput .= '<input type="submit" id="tabapay" value="' . $params['langpaynow'] . '" />';
    $htmlOutput .= '</form>';
    return $htmlOutput;
}

function CreateTransaction($data, $merchant)
{
    $gatewayParams = getGatewayVariables('tabapay');
    if ($gatewayParams['testMode'] == 'on') {
        $url = "https://api.tabapay.ir/v1/sandbox/create";
    } else {
        $url = "https://api.tabapay.ir/v1/create";
    }

    // Convert data to JSON format
    $postData = json_encode($data);

    // Send request and get response
    return SendRequest("post", $url, $postData, $merchant);
}

function VerifyTransaction($token, $amount, $merchant)
{
    $gatewayParams = getGatewayVariables('tabapay');
    if ($gatewayParams['testMode'] == 'on') {
        $url = "https://api.tabapay.ir/v1/sandbox/verify";
    } else {
        $url = "https://api.tabapay.ir/v1/verify";
    }

    // Request body
    $data = array(
        'token' => $token,
        'amount' => $amount
    );

    // Convert data to JSON format
    $postData = json_encode($data);

    // Send request and get response
    return SendRequest("post", $url, $postData, $merchant);
}

function SendRequest($method, $url, $postData = null, $merchant = null)
{
    // Request headers
    $headers = array(
        'Authorization: Bearer ' . $merchant,
        'Content-Type: application/json',
    );

    // Initialize cURL session
    $ch = curl_init($url);

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method == "get") {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    }
    if ($method == "post") {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }

    // Execute cURL session and get the response
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    }

    // Close cURL session
    curl_close($ch);

    // Decode the API response
    return json_decode($response, 1);
}

?>

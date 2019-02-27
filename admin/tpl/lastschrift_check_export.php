<?php

require_once $ab_path . 'sys/lib.user.php';
require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/lib.payment.adapter.user.php';
require_once $ab_path . 'sys/payment/PaymentFactory.php';

if ( $_GET['download'] ) {
    if ( $_GET["download"] == "true" ) {

        $folder_path = dirname(__FILE__)."/../../filestorage";
        $lastschrift_check_exports = "/lastschrift_check_export";
        $file_path = $folder_path.$lastschrift_check_exports."/".$_GET["export"].".csv";

        if ( file_exists($file_path) ) {
            header('Content-Type: text/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename='.$_GET['export'].'.csv');
            echo file_get_contents($file_path);
            die();
        }
    }
}

if ( $_GET['export_id'] ) {

    require_once $ab_path . "sys/lib.payment.pincode.email.php";

    $export_id = intval( mysql_real_escape_string($_GET["export_id"]) );

    $payment_pincode_email = new PaymentPinCodeEmail();
    $payment_pincode_email->sendForceEmails( $export_id );

}

require_once $ab_path . 'sys/lib.payment.adapter.php';

$pincode_email_option = $db->fetch_atom("SELECT value
    FROM `option`
    WHERE `plugin` = 'MARKTPLATZ' AND `typ` = 'PINCODE_EMAIL'"
);

if ( $_GET['csv'] ) {
    $sql = 'SELECT a.ID_USER, a.PAYMENT_ADAPTER_CONFIG
    FROM user a
    WHERE a.PAYMENT_ADAPTER_DIRECTDB_REQ_FLAG = 1';

    $resultArr = $db->fetch_table( $sql );
    if ( count($resultArr) > 0 ) {
        $paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);
        $marketplacePaymentAdapter = $paymentAdapterManagement->fetchByAdapterName("DirectDebit");
        $paymentAdapterConfiguration = array(
            'CONFIG' => $paymentAdapterManagement->fetchConfigurationById(
                $marketplacePaymentAdapter['ID_PAYMENT_ADAPTER']
            )
        );
        $folder_path = dirname(__FILE__)."/../../filestorage";
        $lastschrift_check_exports = "/lastschrift_check_export";

        if ( !file_exists($folder_path . $lastschrift_check_exports) ) {
            mkdir($folder_path . $lastschrift_check_exports, 0777, true);
        }
        $time = time();
        $file_name = $folder_path.$lastschrift_check_exports."/".$time.".csv";
        $output = fopen($file_name, 'w');

        // output the column headings
        fputcsv($output, array(
                'Marktplatz IBAN',
                'Marktplatz BIC',
                'Customer IBAN',
                'Customer BIC',
                'Payment Details',
            )
        );

        $total_money = 0.00;

	    $payment_adapter = $paymentAdapterManagement->fetchByAdapterName("DirectDebit");

	    $paymentAdapterConfiguration = array(
	    	'CONFIG' => $paymentAdapterManagement->fetchConfigurationById($payment_adapter["ID_PAYMENT_ADAPTER"])
	    );

	    $paymentAdapter = Payment_PaymentFactory::factory(
	    	"DirectDebit",
		    $paymentAdapterConfiguration
	    );

        // loop over the rows, outputting them
        foreach ( $resultArr as $item ) {
	        $paymentAdapter->init(array(
		        'FK_USER'   =>  $item["ID_USER"]
	        ));

	        $generatedCode = mb_strtoupper(
		        substr(md5(
			        $customerPaymentAdapterConfig['Recipient'] .
			        $customerPaymentAdapterConfig['iban'] .
			        $customerPaymentAdapterConfig['bic'] .
			        $customerPaymentAdapterConfig['Bank'] .
			        time()
		        ),0,10)
	        );

	        $customerPaymentAdapterConfig = $paymentAdapter->getUserConfiguration("directdebit");
            $temp = array(
                $paymentAdapterConfiguration['CONFIG']['IBAN'],
                $paymentAdapterConfiguration['CONFIG']['BIC'],
                $customerPaymentAdapterConfig['iban'],
                $customerPaymentAdapterConfig['bic'],
                "Ihr Verifizierungscode für " .$GLOBALS["nar_systemsettings"]["SITE"]["SITENAME"]. " : " . $generatedCode
            );
            fputcsv($output, $temp);

            $hash = $hash = md5(
                $customerPaymentAdapterConfig['Recipient'].';'.
                $customerPaymentAdapterConfig['iban'].';'.
                $customerPaymentAdapterConfig['bic'].';'.
                $customerPaymentAdapterConfig['Bank'].';');

            $customerPaymentAdapterConfig['PinCodeRequestedValue'] = $generatedCode;
            $customerPaymentAdapterConfig['accounts'][$hash]['PinCodeRequestedValue'] = $generatedCode;

            $paymentAdapter->setUserConfiguration('directdebit',$customerPaymentAdapterConfig);

            $tab_data = array(
                'ID_USER'                           =>  $item['ID_USER'],

                'PAYMENT_ADAPTER_DIRECTDB_REQ_FLAG' =>  2
            );//'PAYMENT_ADAPTER_CONFIG'            =>  serialize($customerPaymentAdapterConfig),

            $db->update(
                'user',
                $tab_data
            );
            $total_money += 0.01;
        }
        fclose($output);

        $user_account_pincode_export = array(
            'EXPORT_NO'     =>  $time,
            'TOTAL_USERS'   =>  count($resultArr),
            'TOTAL_MONEY'   =>  $total_money,
            'PINCODE_EMAIL' =>  $pincode_email_option,
            'STAMP'         =>  date('Y-m-d H:i:s',$time)
        );
        $id_user_account_pincode_export = $db->update(
            'user_account_pincode_export',
            $user_account_pincode_export
        );
        if ( $id_user_account_pincode_export ) {
            foreach ( $resultArr as $item ) {
                $data = array(
                    'ID_USER'                           =>  $item['ID_USER'],
                    'FK_USER_ACCOUNT_PINCODE_EXPORT'    =>  $id_user_account_pincode_export,
                );
                $db->update('user',$data);
            }
        }
        $tpl_content->addvar("download_button_click",$time);
        $tpl_content->addvar("download_button_click_check",true);
    }
}

$sql = 'SELECT count(1) as count
FROM user a
WHERE a.PAYMENT_ADAPTER_DIRECTDB_REQ_FLAG = 1';

$count = $db->fetch_atom( $sql );
$total_money = intval($count) * 0.01;

$sql = 'SELECT *
        FROM user_account_pincode_export a
        ORDER BY a.STAMP DESC
        LIMIT 20';

$prevExports = $db->fetch_table( $sql );

$tpl_content->addvar("BANK_REQ_VERIFICATION_USERS_COUNT",$count);
$tpl_content->addvar("TOTAL_MONEY",$total_money);
$tpl_content->addvar("PINCODE_EMAIL",$pincode_email_option);
$tpl_content_links->addvar("PINCODE_EMAIL",$pincode_email_option);
$tpl_content->addlist("liste",$prevExports, 'tpl/'.$s_lang.'/lastschrift_check_export.row.htm');
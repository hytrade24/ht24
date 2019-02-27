<?php
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 6.5.1
 */

/**
 * Liest zufällig sortierte Anzeigen aus.
 * 
 * BEISPIEL:    {subtpl(tpl/{SYS_TPL_LANG}/ads_random.htm,ID_KAT=42,COUNT=8,COUNT_PER_ROW=4,HIDE_PARENT=1)}
 * PARAMETER:
 *  ID_USER         - (optional) Filtern der Einkäufe/Verkäufe nach dem Partner.
 *                      Standard: Es werden alle Einkäufe/Verkäufe ausgelesen.
 *  TYPE            - (optional) Legt fest ob die Einkäufe oder Verkäufe ausgelesen werden sollen.
 *                      Standard: Listet die Verkäufe des aktuellen Benutzers aus. (1: Verkäufe, 2: Käufe)
 *                      
 */

$subtplConfig = Template_Modules_SubTemplates::create($tpl_content, "sale_list", "Verkäufe/Käufe");
$userId = $subtplConfig->addOptionInt("ID_USER", "User-ID", false, "{ID_USER}");
$userId = ($userId > 0 ? $userId : null);
$type = $subtplConfig->addOptionCheckboxList("TYPE", "News-Typen", 1, array(
    1 => "Verkäufe",
    2 => "Käufe"
));
$template = $subtplConfig->addOptionSelectList("TEMPLATE", "Darstellung", 'row', array(
    'row'			=> "Listen-Darstellung"
));
$subtplConfig->finishOptions();

require_once $ab_path.'sys/lib.ad_order.php';
require_once $ab_path.'sys/lib.payment.adapter.php';
require_once $ab_path.'sys/payment/PaymentFactory.php';

$adOrderManagement = AdOrderManagement::getInstance($db);
$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);

$param = array(
	'LIMIT' => $perpage,
	'OFFSET' => $limit
);
switch ($type) {
    default:
    case 1:
        // Verkäufe
        $param["USER_BUYER"] = $userId;
        $param["USER_SELLER"] = $uid;
        break;
    case 2:
        // Käufe
        $param["USER_BUYER"] = $uid;
        $param["USER_SELLER"] = $userId;
        break;
}

$userOrders = $adOrderManagement->fetchAllByParam($param);
$countOrder = $adOrderManagement->countByParam($param);

// Change base template?
$tplNameBase = trim(str_replace("row", "", $template), "_.");
if (!empty($tplNameBase)) {
    $tplFileBase = CacheTemplate::getHeadFile("tpl/".$s_lang."/sale_list.".$tplNameBase.".htm");
    if (file_exists($tplFileBase)) {
        $tpl_content->LoadText($tplFileBase);
    }
}

$tpl_content->addlist("orders", $userOrders, "tpl/".$s_lang."/sale_list.row.htm", "callback_order_addOrderItems");
$tpl_content->addvar("CURRENCY_DEFAULT", $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
$tpl_content->addvar('COUNT_UNCONFIRMED_ORDERS', $countUnconfirmedOrders);
$tpl_content->addvars($_REQUEST);


?>

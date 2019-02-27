<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.pub_kategorien.php';
include_once "sys/lib.nestedsets.php";
include_once "sys/lib.shop_kategorien.php";
require_once $ab_path.'sys/lib.advertisement.categoryprice.php';
require_once $ab_path.'sys/lib.advertisement.php';
require_once $ab_path.'sys/lib.affiliate.php';

$kat_cache = new CategoriesCache();
$kat = new TreeCategories("kat", 1);

$advertisementManagement = AdvertisementManagement::getInstance($db);
$advertisementCategoryPriceManagement = AdvertisementCategoryPriceManagement::getInstance($db);

$affiliateManagement = AffiliateManagement::getInstance($db);


$idKat = (int) $_REQUEST['ID_KAT'];
if($idKat == 0) { die(); }

if(isset($_POST['DO']) && $_POST['DO'] == 'SAVE') {
    if($_POST['TYPE'] == 'ADVERTISEMENT') {
        $advertisementCategoryPriceManagement->setPriceByCategory($idKat, $_POST['ADVERTISEMENT_PRICE'], (bool)$_POST['PASS_TO_CHILDREN']);
        echo json_encode(array('success' => true));
        die();
    } else if($_POST['TYPE'] == 'AFFILIATE') {
		$affiliateManagement->updateCategoryAliases($idKat, $_POST['AFFILIATE_ALIAS'], (bool)$_POST['PASS_TO_CHILDREN']);
		echo json_encode(array('success' => true));
		die();
	}
}

// Tax value
$taxId = $nar_systemsettings["MARKTPLATZ"]["TAX_DEFAULT"];
$tax = $db->fetch1("SELECT * FROM `tax` WHERE ID_TAX=".(int)$taxId);

// Path
$path = array();
foreach($kat_cache->kats_read_path($idKat) as $key => $katPath) {
    $path[] = $katPath['V1'];
}
$tpl_content->addvar('KAT_PATH', implode(' > ', $path));

// depth
$categoryDepth = count($path);

// Siblings
$currentKat = $kat->element_read($idKat);
$tpl_content->addvars($currentKat, "KAT_");

$successorId = $db->fetch_atom("SELECT ID_KAT FROM kat WHERE ROOT = 1 AND LFT > '".$currentKat['LFT']."' ORDER BY LFT LIMIT 1");
$successor = $kat->element_read($successorId);

$predecessorId = $db->fetch_atom("SELECT ID_KAT FROM kat WHERE ROOT = 1 AND LFT < '".$currentKat['LFT']."' ORDER BY LFT DESC LIMIT 1");
$predecessor = $kat->element_read($predecessorId);

if($successor != null) {
    $tpl_content->addvars($successor, "SUCCESSOR_");
}
if($predecessor != null) {
    $tpl_content->addvars($predecessor, "PREDECESSOR_");
}


// Werbung

$advertisements = $advertisementManagement->fetchAllByParam(array());
$usergroups = $db->fetch_table("
    SELECT
  		g.*, s.V1, s.V2, s.T1
  	FROM `usergroup` g
  		LEFT JOIN `string_usergroup` s ON
        s.FK=g.ID_USERGROUP AND s.S_TABLE='usergroup' AND
        s.BF_LANG=if(g.BF_LANG_USERGROUP & ".$langval.", ".$langval.", 1 << floor(log(g.BF_LANG_USERGROUP+0.5)/log(2)))
    ORDER BY g.F_ORDER ASC ");

$tplAdvertisement = "";
foreach($advertisements as $key => $advertisement) {
    $tpl_tmp = new Template("tpl/de/m_kat_quickedit.advertisement.row.htm");

    $tplAdvertisementColNetto = "";
    $tplAdvertisementCol = "";
    foreach($usergroups as $uKey => $usergroup) {
        $price = $advertisementCategoryPriceManagement->getPriceByCategory($advertisement['ID_ADVERTISEMENT'], $idKat, $usergroup['ID_USERGROUP']);
        $priceBrutto = ($price > 0 ? ($price * (1 + $tax["TAX_VALUE"] / 100)) : $price);
        $defaultPrice = $advertisementManagement->getDefaultLevelPriceByAdvertisement($advertisement['ID_ADVERTISEMENT'], $categoryDepth);
        $defaultPriceBrutto = ($defaultPrice * (1 + $tax["TAX_VALUE"] / 100));
        $arColParams = array(
            'PRICE' => $price,
            'PRICE_BRUTTO' => $priceBrutto,
            'DEFAULT_PRICE' => $defaultPrice,
            'DEFAULT_PRICE_BRUTTO' => $defaultPriceBrutto,
            'ID_ADVERTISEMENT' => $advertisement['ID_ADVERTISEMENT'],
            'ID_KAT' => $idKat,
            'ID_USERGROUP' => $usergroup['ID_USERGROUP'],
            'TAX_PERCENT' => $tax["TAX_VALUE"],
            'DEFAULT_CURRENCY' => $nar_systemsettings['MARKTPLATZ']['CURRENCY']
        );

        $tpl_col_tmp_net = new Template("tpl/de/m_kat_quickedit.advertisement.col.htm");
        $tpl_col_tmp_net->addvars($arColParams);
        $tplAdvertisementColNetto .= $tpl_col_tmp_net->process();
        $tpl_col_tmp = new Template("tpl/de/m_kat_quickedit.advertisement.col.brutto.htm");
        $tpl_col_tmp->addvars($arColParams);
        $tplAdvertisementCol .= $tpl_col_tmp->process();
    }
    $tpl_tmp->addvars($advertisement);
    $tpl_tmp->addvar('even', ($key & 1) == 0);
    $tpl_tmp->addvar('COLS_NETTO', $tplAdvertisementColNetto);
    $tpl_tmp->addvar('COLS_BRUTTO', $tplAdvertisementCol);
    $tplAdvertisement .= $tpl_tmp->process();
}

$tpl_content->addvar("ADVERTISEMENT_TABLE", $tplAdvertisement);
$tpl_content->addlist("ADVERTISEMENT_TABLE_USERGROUP_TH", $usergroups, "tpl/de/m_kat_quickedit.advertisement.th.htm");


// Affiliate
$affiliateAliases = $db->fetch_nar("SELECT FK_AFFILIATE, ALIAS FROM affiliate_kat_alias WHERE FK_KAT = '".(int)$idKat."'");

$affiliates = $affiliateManagement->fetchAllByParam(array());
foreach($affiliates as $key => $affiliate) {
	$affiliates[$key]['ALIAS'] = $affiliateAliases[$affiliate['ID_AFFILIATE']];
}

$tpl_content->addlist("AFFILIATE_ROW", $affiliates, "tpl/de/m_kat_quickedit.affiliate_row.htm");


$tpl_content->addvar("SELECTED_TAB", (isset($_REQUEST['SELECTED_TAB'])?(int)$_REQUEST['SELECTED_TAB']:0));
$tpl_content->addvar("SETTINGS_AFFILIATE", $nar_systemsettings['PLUGIN']['AFFILIATE']);
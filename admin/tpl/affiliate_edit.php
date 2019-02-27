<?php

require_once $ab_path.'sys/lib.affiliate.php';
require_once $ab_path.'sys/lib.pub_kategorien.php';
require_once $ab_path.'sys/lib.shop_kategorien.php';

$affiliateManagement = AffiliateManagement::getInstance($db);
$categoriesBase = new CategoriesBase();
$treeCategories = new TreeCategories("kat", 1);

$affiliateId = (int)$_REQUEST['ID_AFFILIATE'];

$err = array();

if (count($_POST)) {
	date_implode($_POST, 'STAMP_START');
	date_implode($_POST, 'STAMP_END');


	if (!$_POST['DESCRIPTION']) {
		$err[] = 'Bezeichnung fehlt.';
	}
	if (!$_POST['ADAPTER']) {
		$err[] = 'Adapter fehlt.';
	}

	if (!count($err)) {
		if($_POST['STAMP_LAST'] == "") {
			$_POST['STAMP_LAST'] = NULL;
		}

		$affiliateId = $db->update('affiliate', $_POST);

		if (!$affiliateId) {
			$err[] = 'Fehler beim Speichern.';
		} else {
			forward('index.php?frame=content&page=affiliate');
		}
	}
}

if ($affiliateId) {
	$affiliate = $affiliateManagement->find($affiliateId);
} else {
	$affiliate = array();
}

$data = array_merge($affiliate, $_POST);
$tpl_content->addvars($data);

// Affiliate Adapter
$affiliateAdapters = $affiliateManagement->getAffiliateAdapters();
foreach($affiliateAdapters as $key => $affiliateAdapter) {
	$affiliateAdapters[$key]['SELECTED'] = ($affiliateAdapter['ADAPTER'] == $affiliate['ADAPTER']);
}
if(count($affiliateAdapters) > 0) {
	$tpl_content->addlist('affiliate_types', $affiliateAdapters, 'tpl/de/affiliate_edit.row_types.htm');
}

// Categories
//$categories = $categoriesBase->kats_read_path(1);
$rootKat = $treeCategories->element_read(1);

$categories = $db->fetch_table($query ="
	SELECT
		k.*,
		s.V1
	FROM `kat` k
	LEFT JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=k.ID_KAT
	  AND s.BF_LANG=if(k.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))
	WHERE
	  (LFT >= ".$rootKat["LFT"].") AND
	  (RGT <= ".$rootKat["RGT"].") AND
	  (ROOT = ".$rootKat["ROOT"].") AND
	  ID_KAT != 1
	ORDER BY LFT
");

foreach($categories as $key => $category) {
	$categories[$key]['PREFIX'] = str_repeat('-', ($category['LEVEL']-1)*2);
	$categories[$key]['SELECTED'] = ($category['ID_KAT'] == $affiliate['FK_FALLBACK_KAT']);
}
$tpl_content->addlist('affiliate_fallback_kat', $categories, 'tpl/de/affiliate_edit.row_kats.htm');

if (count($err)) {
	$tpl_content->addvar('err', implode('<br />', $err));
}

$tpl_content->addvar("npage", $_REQUEST['npage']);
$tpl_content->addvar("SETTINGS_AFFILIATE", $nar_systemsettings['PLUGIN']['AFFILIATE']);



?>
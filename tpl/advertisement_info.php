<?php
/* ###VERSIONSBLOCKINLCUDE### */


function read_state(&$row, $i) {
	global $db, $uid, $id_ad_user;
	$id_checked = $db->fetch_atom("
		SELECT
			ID_ADVERTISEMENT_KAT
		FROM
			`advertisement_kat`
		WHERE
			FK_ADVERTISEMENT_USER=".(int)$id_ad_user." AND
			FK_KAT=".(int)$row["ID_KAT"]."
	");
	if ($id_checked > 0) {
		$row["CHECKED"] = 1;
	} else {
		$row["CHECKED"] = 0;
	}
}

$id_ad_user = ($_REQUEST["id_ad_user"] ? $_REQUEST["id_ad_user"] : $ar_params[1]);

$advertisementUser = $db->fetch1("SELECT
			u.*,
			(SELECT count(*) FROM `advertisement_kat` k WHERE k.FK_ADVERTISEMENT_USER=u.ID_ADVERTISEMENT_USER)
				AS NUM_KATS,
			(DATEDIFF(STAMP_END,STAMP_START) * u.PRICE) as PRICE_SUM,
			s.V1 as AD_NAME 
		FROM
			`advertisement_user` u
		LEFT JOIN
			`advertisement` a ON
			a.ID_ADVERTISEMENT=u.FK_ADVERTISEMENT
		LEFT JOIN
			`string_advertisement` s ON
			s.S_TABLE='advertisement' AND s.FK=a.ID_ADVERTISEMENT AND
			s.BF_LANG=if(a.BF_LANG_ADVERTISEMENT & ".$langval.", ".$langval.", 1 << floor(log(a.BF_LANG_ADVERTISEMENT+0.5)/log(2)))
		WHERE FK_USER = '".mysql_escape_string($uid)."' AND ID_ADVERTISEMENT_USER = '".mysql_escape_string($id_ad_user)."'");

// Kategorie Baum
include_once "sys/lib.shop_kategorien.php";
$kat = new TreeCategories("kat", 1);
$id_kat = 1;
// Read child nodes of target categoy
if ($ar_kat_root = $kat->element_get_childs($id_kat)) {
	
	foreach ($ar_kat_root as $index => $ar_kat) {
		// Check for childs
		$ar_kat_root[$index]["HAS_CHILDS"] = $kat->element_has_childs($ar_kat["ID_KAT"]);
		$ar_kat_root[$index]["ID_AD_USER"] = $id_ad_user;
	}
	// Output category list
	$tpl_content->addlist("liste", $ar_kat_root, "tpl/de/advertisement.row_kat.htm", read_state);
}


$tpl_content->addvars($advertisementUser);

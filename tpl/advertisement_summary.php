<?php
/* ###VERSIONSBLOCKINLCUDE### */



$ar_ad_user = $db->fetch1("
	SELECT
		*,
		(DATEDIFF(STAMP_END, STAMP_START)+1) as DAYS,
		(SELECT count(*) FROM `advertisement_kat` WHERE FK_ADVERTISEMENT_USER=ID_ADVERTISEMENT_USER) AS CATEGORYS
	FROM
		`advertisement_user`
	WHERE
		".($_REQUEST['id'] > 0 ? "ID_ADVERTISEMENT_USER=".$_REQUEST['id'] :
			"ENABLED=0 AND FK_USER=".$uid." AND DONE=0"));
if (empty($ar_ad_user)) {
	$ar_ad_user = array(
		"FK_USER"	=> $uid,
		"ENABLED"	=> 0,
		"DONE"		=> 0
	);
	$ar_ad_user["ID_ADVERTISEMENT_USER"] = $db->update("advertisement_user", $ar_ad_user);
}
$id_ad_user = $ar_ad_user["ID_ADVERTISEMENT_USER"];

if ($ar_ad_user["FK_ADVERTISEMENT"] > 0) {
	$advertisement_name = $db->fetch_atom("
		SELECT
			s.V1
		FROM
			`advertisement` a
		LEFT JOIN
			`string_advertisement` s ON
			s.S_TABLE='advertisement' AND s.FK=a.ID_ADVERTISEMENT AND
			s.BF_LANG=if(a.BF_LANG_ADVERTISEMENT & ".$langval.", ".$langval.", 1 << floor(log(a.BF_LANG_ADVERTISEMENT+0.5)/log(2)))
		WHERE
			a.ID_ADVERTISEMENT=".$ar_ad_user["FK_ADVERTISEMENT"]);
	$tpl_content->addvar("NAME", $advertisement_name);
}
$tpl_content->addvar("DAYS", $ar_ad_user["DAYS"]);

$ar_levels = array();
$kat_levels = $db->fetch_atom("
	SELECT
		MAX(LEVEL)
	FROM
		`advertisement_kat`
	WHERE
		FK_ADVERTISEMENT_USER=".$id_ad_user);


$ar_kats = $db->fetch_table("
	SELECT
		ak.*,
		ak.PRICE as PRICE_DAY,
		s.*
	FROM
		`advertisement_kat` ak
    LEFT JOIN `kat` ON ak.FK_KAT = kat.ID_KAT
    LEFT JOIN `string_kat` s ON s.FK=kat.ID_KAT and s.S_TABLE='kat' and s.BF_LANG=if(kat.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(kat.BF_LANG_KAT+0.5)/log(2)))
  	WHERE
		FK_ADVERTISEMENT_USER=".$id_ad_user."
");

$ar_summary = array(
	"PRICE_DAY"	=> 0,
	"PRICE_ALL"	=> 0
);
$ar_kats_level = array();
foreach($ar_kats as $key => $ar_kat) {
    $ar_kats[$key]["PRICE_ALL"] = $ar_kat["PRICE_DAY"] * $ar_ad_user["DAYS"];
   	$ar_summary["PRICE_DAY"] += $ar_kat["PRICE_DAY"];
   	$ar_summary["PRICE_ALL"] += $ar_kats[$key]["PRICE_ALL"];
}


$tpl_content->addvars($ar_summary);
$tpl_content->addlist("liste", $ar_kats, "tpl/".$s_lang."/advertisement_summary.row.htm");

?>
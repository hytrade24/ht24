<?php
/* ###VERSIONSBLOCKINLCUDE### */


#$SILENCE=false;
include_once "sys/lib.nestedsets.php";
include_once "sys/lib.shop_kategorien.php";

$kat = new TreeCategories("kat", 1);
$id_kat_root = $kat->tree_get_parent();

$action = ($_POST["action"] ? $_POST["action"] : $ar_params[1]);

if ($action == "dialog") {
	$id_ad = (int)$ar_params[2];
	$id_kat = @$db->fetch_atom("SELECT FK_KAT FROM `ad_master` WHERE ID_AD_MASTER=".$id_ad);
	if (($id_ad > 0) && ($id_kat > 0)) {
		$tpl_content->addvar("show_extend", 1);
		$tpl_content->addvar("show_extend_ad", $id_ad);
		$tpl_content->addvar("show_extend_kat", $id_kat);
	}
}


if ($action == "deactivate") {
	$id_ad = $ar_params[2];
	$id_kat = $ar_params[3];
	$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".mysql_real_escape_string($id_kat));

	include_once "sys/lib.ads.php";
	AdManagment::Disable($id_ad, $kat_table);

	die(forward("/my-pages/my-marktplatz,,,,deakt.htm"));
}

#DATEDIFF(NOW(), p.STAMP_START) as RUNTIME, DATEDIFF(p.STAMP_END, NOW()) as TIMELEFT,
$perpage = 10;
$npage = ((int)$ar_params[1] ? $ar_params[1] : 1);
$limit = ($perpage*$npage)-$perpage;

$ads = $db->fetch_table("
	SELECT
			SQL_CALC_FOUND_ROWS
    		am.*,
    		am.ID_AD_MASTER AS ID_ARTIKEL,
    		LEFT(am.BESCHREIBUNG, 250) AS DSC,
    		DATEDIFF(NOW(), am.STAMP_START) as RUNTIME,
    		DATEDIFF(am.STAMP_END, NOW()) as TIMELEFT,
    		s.V1 as KAT,
    		sc.V1 as LAND,
    		i.SRC AS SRC_FULL,
    		i.SRC_THUMB,
			m.NAME as MANUFACTURER
    	FROM
    		ad_master am
    	LEFT JOIN
			string_kat s on s.S_TABLE='kat'
			and s.FK=am.FK_KAT
			and s.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
    	LEFT JOIN
			string sc on sc.S_TABLE='country'
			and sc.FK=am.FK_COUNTRY
			and sc.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
		LEFT JOIN
			ad_images i ON am.ID_AD_MASTER = i.FK_AD AND i.IS_DEFAULT=1
		LEFT JOIN `manufacturers` m ON m.ID_MAN=am.FK_MAN
		WHERE
    		am.FK_USER=".$uid."
    		AND am.STATUS&3 = 1 AND (am.DELETED=0) and (DATEDIFF(am.STAMP_END, NOW())<14)
    	ORDER BY
			am.STAMP_END ASC
    	LIMIT
    		".$limit.", ".$perpage);

$tpl_content->addlist("liste", $ads, "tpl/".$s_lang."/my-marktplatz.row.htm");
// Seitenzähler hinzufügen
$all = $db->fetch_atom("
  		SELECT
  			FOUND_ROWS()");
$tpl_content->addvar("pager", htm_browse($all, $ar_params[1], "/my-marktplatz-timeout,", $perpage));

$tpl_content->addvar("ALLOW_COMMENTS_AD", $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_AD']);

?>
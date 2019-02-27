<?php
/* ###VERSIONSBLOCKINLCUDE### */



function addbidinfo(&$row, $i) {
	$row['BID_STATUS_'.$row['BID_STATUS']] = 1;
}

$id_ad = (int)$_REQUEST["id_ad"];
$id_ad_variant = (int)$_REQUEST["id_ad_variant"];
$id_ad_request = (int)$_REQUEST["request"];
$ar_ad_check = $db->fetch1("SELECT FK_USER, VERKAUFSOPTIONEN FROM `ad_master` WHERE ID_AD_MASTER=".($id_ad_request > 0 ? $id_ad_request : $id_ad));
$id_user_owner = $ar_ad_check["FK_USER"];
//$id_user_owner = (int)$db->fetch_atom("SELECT FK_USER FROM `ad_master` WHERE ID_AD_MASTER=".$id_ad);
if ($uid != $id_user_owner) {
	die("Zugriff verweigert!");
}

if ($id_ad_request || ($ar_ad_check["VERKAUFSOPTIONEN"] != 5)) {
	// Regular article
	$res = $db->querynow("
		SELECT
			*,
			(SELECT NAME FROM `user` WHERE ID_USER=FK_USER_FROM) AS `NAME`,
			(SELECT NAME FROM `user` WHERE ID_USER=FK_USER_TO) AS `NAME_OFFER`,
			(AMOUNT * BID) AS BID_FULL,
			IF(FK_USER_AD_OWNER=FK_USER_FROM,FK_USER_TO,FK_USER_FROM) AS FK_USER_BID
		FROM
			trade
		WHERE FK_AD=".$id_ad." AND FK_AD_VARIANT=".$id_ad_variant.($id_ad_request > 0 ? " AND FK_AD_REQUEST=".$id_ad_request : "")."
		ORDER BY
			FK_NEGOTIATION DESC,
			STAMP_BID ASC,
			ID_TRADE ASC");
} else {
	// Request
	$res = $db->querynow("
		SELECT
			*,
			(SELECT NAME FROM `user` WHERE ID_USER=FK_USER_FROM) AS `NAME`,
			(SELECT NAME FROM `user` WHERE ID_USER=FK_USER_TO) AS `NAME_OFFER`,
			(AMOUNT * BID) AS BID_FULL,
			FK_USER_AD_OWNER AS FK_USER_BID
		FROM
			trade
		WHERE FK_AD_REQUEST=".$id_ad."
		ORDER BY
			FK_NEGOTIATION DESC,
			STAMP_BID ASC,
			ID_TRADE ASC");
}

$liste=array();
$remarks = false;
$active = false;
$first = true;
$i = 0;
while($row = mysql_fetch_assoc($res['rsrc'])) {
    $remarks = $row['REMARKS'];
	if ($first) {
		$row['new'] = 1;
		$cur_negotiation = $row['FK_NEGOTIATION'];
		$first = false;
	}
	if ($cur_negotiation != $row['FK_NEGOTIATION']) {
		$row['new'] = 1;
		$liste[$i-1]['last'] = 1;
		$cur_negotiation = $row['FK_NEGOTIATION'];
		$active = false;
	}
	if ($row["STATUS"] == "ACTIVE") {
		$active = true;
	}
	$row["active"] = $active;
	$row["BID_STATUS_".$row["STATUS"]] = 1;
	$liste[$i++] = $row;
}
if (count($liste) > 0) {
    $liste[$i-1]['last'] = 1;
	$tpl_content->addlist("liste", $liste, "tpl/".$s_lang."/my-marktplatz-handeln-gebote.row.htm", 'addbidinfo');
	$tpl_content->addvar("REMARKS", $remarks);
}

?>
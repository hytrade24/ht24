<?php
/* ###VERSIONSBLOCKINLCUDE### */


function addVariants(&$row, $i) {
	global $db, $langval;
	$ar_variant = (isset($row["SER_VARIANT"]) ? unserialize($row["SER_VARIANT"]) : array());
	$ar_variant_list = array();
	foreach ($ar_variant as $index => $ar_current) {
		$value = $db->fetch_atom("SELECT sl.V1 FROM `liste_values` t
				LEFT JOIN `string_liste_values` sl
					ON sl.S_TABLE='liste_values' AND sl.FK=t.ID_LISTE_VALUES
					AND sl.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
				WHERE t.ID_LISTE_VALUES=".$ar_current["ID_LISTE_VALUES"]);
		if ($value !== false) {
			$ar_variant_list[] = $value;
		} else {
			$ar_variant_list[] = $ar_current["VALUE"];
		}
	}
	$row["VARIANT"] = (empty($ar_variant_list) ? "" : implode(", ", $ar_variant_list));
	$row['BID_STATUS_'.$row['BID_STATUS']] = 1;
	#var_dump($row);
}

$npage = ((int)$ar_params[2] ? (int)$ar_params[2] : 1);
$perpage = 20;
$limit = ($perpage*$npage)-$perpage;

//$SILENCE = false;
$countReceived = $db->fetch_atom("
  SELECT count(*) 
  FROM `trade` 
  WHERE ID_TRADE=FK_NEGOTIATION 
    AND ((FK_USER_FROM=".$uid." AND BID_STATUS='REQUEST') OR (FK_USER_TO=".$uid." AND BID_STATUS!='REQUEST'))");

$countSent = $db->fetch_atom("
  SELECT count(*) 
  FROM `trade` 
  WHERE ID_TRADE=FK_NEGOTIATION 
    AND ((FK_USER_TO=".$uid." AND BID_STATUS='REQUEST') OR (FK_USER_FROM=".$uid." AND BID_STATUS!='REQUEST'))");


$mode = ( empty($ar_params[1]) ? (($countReceived == 0) && ($countSent > 0) ? "sent" : "received") : $ar_params[1] );
$tpl_content->addvar("mode_".$mode, 1);

switch ($mode) {
    default:
    case "received":
        $all = $countReceived;
        $liste = $db->fetch_table($q="
            SELECT
              ta.*,
              adr.ID_AD_MASTER AS FK_AD_REQUEST,
              adr.FK_KAT AS FK_KAT_REQUEST,
              adr.PRODUKTNAME AS REQUESTNAME,
              tr.SER_VARIANT,
              ad.PRODUKTNAME,
              ad.STATUS as AD_STATUS,
              us.NAME AS MAXBID_USERNAME
            FROM `trade` tr
            LEFT JOIN `trade_ad` ta 
              ON tr.FK_AD=ta.FK_AD AND tr.FK_AD_VARIANT=ta.FK_AD_VARIANT 
            LEFT JOIN `ad_master` ad
              ON ad.ID_AD_MASTER=ta.FK_AD
            LEFT JOIN `ad_master` adr
              ON adr.ID_AD_MASTER=tr.FK_AD_REQUEST
            LEFT JOIN `user` us
              ON us.ID_USER=IF(tr.FK_USER_FROM=".$uid.",tr.FK_USER_TO,tr.FK_USER_FROM)
            WHERE
              tr.ID_TRADE=tr.FK_NEGOTIATION 
                AND ((tr.FK_USER_FROM=".$uid." AND tr.BID_STATUS='REQUEST') OR (tr.FK_USER_TO=".$uid." AND tr.BID_STATUS!='REQUEST'))
            ORDER BY
              ta.LAST_BID_DATE DESC
            LIMIT
              ".$limit.", ".$perpage);
        break;
    case "sent":
        $all = $countSent;
        $liste = $db->fetch_table($q="
            SELECT
              tra.*,
              adr.ID_AD_MASTER AS FK_AD_REQUEST,
              adr.FK_KAT AS FK_KAT_REQUEST,
              adr.PRODUKTNAME AS REQUESTNAME,
              tr.SER_VARIANT,
              ad.PRODUKTNAME,
              ad.STATUS as AD_STATUS,
              us.NAME AS OWNER_USERNAME
            FROM `trade` tr
            LEFT JOIN `trade_ad` ta 
              ON tr.FK_AD=ta.FK_AD AND tr.FK_AD_VARIANT=ta.FK_AD_VARIANT
            LEFT JOIN `trade` tra
              ON tra.FK_NEGOTIATION=tr.FK_NEGOTIATION AND tra.FK_USER_FROM=".$uid." AND tra.BID_STATUS='ACTIVE'
            LEFT JOIN `ad_master` ad
              ON ad.ID_AD_MASTER=ta.FK_AD
            LEFT JOIN `ad_master` adr
              ON adr.ID_AD_MASTER=tr.FK_AD_REQUEST
            LEFT JOIN `user` us
              ON us.ID_USER=IF(tr.FK_USER_FROM=".$uid.",tr.FK_USER_TO,tr.FK_USER_FROM)
            WHERE
              tr.ID_TRADE=tr.FK_NEGOTIATION 
                AND ((tr.FK_USER_TO=".$uid." AND tr.BID_STATUS='REQUEST') OR (tr.FK_USER_FROM=".$uid." AND tr.BID_STATUS!='REQUEST'))
            ORDER BY
              ta.LAST_BID_DATE DESC
            LIMIT
              ".$limit.", ".$perpage);
        break;
}

$tpl_content->addlist("liste_handeln", $liste, "tpl/".$s_lang."/my-marktplatz-handeln.row_".$mode.".htm", 'addVariants');
$tpl_content->addvar("pager", htm_browse($all, $npage, "/my-marktplatz-handeln,".$mode.",",$perpage));

$tpl_content->addvar("countReceived", $countReceived);
$tpl_content->addvar("countSent", $countSent);

?>
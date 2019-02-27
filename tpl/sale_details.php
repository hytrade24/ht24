<?php
/* ###VERSIONSBLOCKINLCUDE### */



if (empty($_REQUEST['embed'])) {
	$tpl_content->addvar("dialog", 1);
}

/*
 * Details zur Transaktion
 * ------------------------------------
 */

$ar = $db->fetch1("
	SELECT
        ad_sold.*,
		manufacturers.`NAME` AS MANUFACTURER,
		ad_master.STAMP_START,
		ad_sold.PREIS as PREIS_NOSHIP,
		ad_sold.VERSANDKOSTEN,
		rating_get.RATING AS RATING_OWN,
		rating_get.`COMMENT` AS COMMENT_OWN,
		rating_send.RATING AS RATING_SEND,
		rating_send.`COMMENT` AS COMMENT_SEND,
        (ad_sold.STATUS & 1) AS ABGESCHLOSSEN
	FROM
		ad_sold
	LEFT JOIN
		ad_master ON ad_sold.FK_AD=ad_master.ID_AD_MASTER
	LEFT JOIN
		ad_sold_rating rating_get ON rating_get.FK_AD_SOLD=ad_sold.ID_AD_SOLD
		AND rating_get.FK_USER=".$uid."
	LEFT JOIN
		ad_sold_rating rating_send ON rating_send.FK_AD_SOLD=ad_sold.ID_AD_SOLD
		AND rating_send.FK_USER_FROM=".$uid."
	LEFT JOIN manufacturers on manufacturers.ID_MAN = ad_master.FK_MAN
	WHERE
		ad_sold.ID_Ad_SOLD=".(int)$_REQUEST['FK_SOLD']."
		AND ad_sold.FK_USER=".$uid);

if(!empty($ar))
{
	$ar['SUBJECT'] = urlencode("Transaktions Id ".$ar['ID_AD_SOLD'].": ".$ar['PRODUKTNAME']);
	$tpl_content->addvars($ar);

	// Varianten
	$liste_variants = array();
	$ar_variant = (isset($ar["SER_VARIANT"]) ? unserialize($ar["SER_VARIANT"]) : array());
	foreach ($ar_variant as $index => $ar_current) {
		$name = $db->fetch_atom("SELECT sf.V1 FROM `field_def` f
		 		LEFT JOIN `string_field_def` sf
		 		ON sf.S_TABLE='field_def' AND sf.FK=f.ID_FIELD_DEF
		 		AND sf.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
			WHERE f.ID_FIELD_DEF=".$ar_current["ID_FIELD_DEF"]);
		if ($name === false) {
			$name = $ar_current["FIELD"];
		}
		$value = $db->fetch_atom("SELECT sl.V1 FROM `liste_values` t
			LEFT JOIN `string_liste_values` sl
				ON sl.S_TABLE='liste_values' AND sl.FK=t.ID_LISTE_VALUES
				AND sl.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
			WHERE t.ID_LISTE_VALUES=".$ar_current["ID_LISTE_VALUES"]);
		if ($value === false) {
			$value = $ar_current["VALUE"];
		}
		$liste_variants[] = array("FIELD" => $name, "VALUE" => $value);
	}
	$tpl_content->addlist("VARIANTS", $liste_variants, "tpl/".$s_lang."/sale_details.variant.row.htm");

	$ar_data = $db->fetch1("
		SELECT
			`NAME` AS `USER`,
			FIRMA,
			VORNAME,
			NACHNAME,
			STRASSE,
			PLZ,
			ORT,
			s.V1 AS LAND,
			TEL,
			FAX,
			MOBIL,
			EMAIL,
			uc.ZAHLUNG as ZAHLUNG
		FROM
			`user`
		left join
			string s on s.S_TABLE='country'
			and s.FK=user.FK_COUNTRY
			and s.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
        left join usercontent uc on uc.FK_USER = user.ID_USER
		WHERE
			ID_USER=".(int)$ar['FK_USER_VK']);

	foreach($ar_data as $key => $value)
	{
		$tpl_content->addvar($key, $value);
	}
}

/*
 * E-Mail Verlauf zur Transaktion
 * ------------------------------------
 */
$ar_mails = $db->fetch_table("
	SELECT
		c.*
	FROM `chat` c
	WHERE
		FK_TRANS = '".(int)$_REQUEST['FK_SOLD']."'");
if (!empty($ar_mails)) {
	$tpl_content->addlist("liste_mails", $ar_mails, "tpl/".$s_lang."/sale_details.row_mail.htm");
}
/*
$ar_mails = $db->fetch_table("
	SELECT
		m.*,
		b.SENDED, b.SUBJECT, b.BODY
	FROM `my_msg` m
	LEFT JOIN `my_msg_body` b
		ON m.FK_MSG_BODY=b.ID_MSG_BODY
	WHERE
		(m.FK_USERID_OWNER=".$uid.") AND (m.FK_TRANS_ID=".$ar['ID_AD_SOLD'].") AND
		(m.FK_USERID_FROM=".$ar['FK_USER']." OR m.FK_USERID_TO=".$ar['FK_USER'].")");
$tpl_content->addlist("liste_mails", $ar_mails, "tpl/".$s_lang."/sale_details.row_mail.htm");
*/
?>
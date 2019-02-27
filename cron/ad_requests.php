<?php
/* ###VERSIONSBLOCKINLCUDE### */

global $db, $langval, $nar_systemsettings;

// Veraltete Gebote löschen
$db->querynow("DELETE FROM `ad_request` WHERE STAMP_END<NOW()");

// Neue Gebote auslesen
$ar_new = $db->fetch_table("
	SELECT
		*
	FROM `ad_request`
	WHERE DATE(STAMP_START) = (CURDATE() - INTERVAL 1 DAY) AND ((STATUS&1) = 1)");

if (!empty($ar_new)) {
	$ar_data = array(
		"links"		=> "",
		"SITENAME"	=> $nar_systemsettings['SITE']['SITENAME'],
		"SITEURL"	=> $nar_systemsettings['SITE']['SITEURL']
	);
	$ar_mails = array();
	foreach ($ar_new as $index => $ar_request) {
		$ar_data['links'] .=
			"- ".($ar_request['HERSTELLER'] ? $ar_request['HERSTELLER']." " : "").$ar_request['PRODUKTNAME']."\n".
			"  ".$ar_data['SITEURL']."/gesuche/gesuch_anzeigen,".$ar_request['ID_AD_REQUEST'].",".chtrans($ar_request['PRODUKTNAME']).".htm\n";
	}

	// Empfänger und deren Sprache auslesen
	$ar_users = $db->fetch_table("
		SELECT
			u.*
		FROM
			`user` u
		WHERE
			u.ABO_REQUEST=1");

	foreach ($ar_users as $index => $ar_user) {

		$mail_content = array_merge($ar_data, $ar_user);
		sendMailTemplateToUser(0, $ar_user["ID_USER"], 'ABO_REQUESTS', $mail_content);
	}
}
?>
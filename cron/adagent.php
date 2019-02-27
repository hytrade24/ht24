<?php
/* ###VERSIONSBLOCKINLCUDE### */


global $db, $langval, $nar_systemsettings;

require_once $ab_path.'sys/lib.ad_constraint.php';

/*
 * Code to check anzeigen agnet expiry
 * */
$query = 'SELECT u.VORNAME, u.NACHNAME, a.*
			FROM ad_agent a
			INNER JOIN user u 
			ON a.CREATED_AT > a.LIFE_CYCLE_ENDS
			AND u.ID_USER = a.FK_USER';

$result = $db->fetch_table( $query );

foreach ( $result as $row ) {

	$mail_content = array(
		'NAME'   =>  $row["VORNAME"]." ".$row["NACHNAME"]
	);

	sendMailTemplateToUser(
		0,
		$row["FK_USER"],
		'AD_AGENT_REMINDER',
		$mail_content
	);

	$table_array = array(
		"ID_AD_AGENT"   =>  $row["ID_AD_AGENT"],
		"STATUS"        =>  0
	);
	$db->update("ad_agent",$table_array);
}

### Ergebnisse auf Null setzen
$db->querynow("
	UPDATE
		ad_agent
	SET
		LAST_RUN=(SELECT count(*) FROM `ad_agent_temp` WHERE FK_AD_AGENT=ID_AD_AGENT)");

$reminders = $db->fetch_table("
  	SELECT
		at.*, a.SEARCH_NAME, a.ID_AD_AGENT, am.BF_CONSTRAINTS
  	FROM `ad_agent_temp` at
  		LEFT JOIN `ad_agent` a ON at.FK_AD_AGENT=a.ID_AD_AGENT
		LEFT JOIN `ad_master` am ON am.ID_AD_MASTER=at.FK_ARTICLE
  	WHERE at.MAIL_SENT=0");

$mails = array();

foreach ($reminders as $id => $data) {
	// Tabelle der Kategorie auslesen
	$data["TABLE"] = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$data['FK_KAT']);
	// User auslesen
	$ad_user = get_user($data["FK_USER"], true);
	if ($ad_user["ID_USER"] <= 0) {
		// Skip locked/invalid users
		continue;
	}
	$data["TEST"] = var_export($data, true);
	// Sprache auslesen
	$langval = $db->fetch_atom("SELECT BITVAL FROM `lang` WHERE ID_LANG=".$ad_user["FK_LANG"]);

	if (empty($mails[$data["FK_USER"]])) {
		$mails[$data["FK_USER"]] = array();
	}
	if (empty($mails[$data["FK_USER"]]["LIST"])) {
		$mails[$data["FK_USER"]]["LIST"] = array();
	}
	if (empty($mails[$data["FK_USER"]]["LIST"][$data["ID_AD_AGENT"]])) {
		$mails[$data["FK_USER"]]["LIST"][$data["ID_AD_AGENT"]] = array();
		$mails[$data["FK_USER"]]["LIST"][$data["ID_AD_AGENT"]]["ARTICLE_IDS"] = array();
	}

	// E-Mail verschicken
	$mail_content = array_merge($ad_user, $data);
	$mail_content['SITEURL'] = $nar_systemsettings['SITE']['SITEURL'];
	$mails[$data["FK_USER"]]["LIST"][$data["FK_AD_AGENT"]]["CONTENT"] = $mail_content;
	$mails[$data["FK_USER"]]["LIST"][$data["FK_AD_AGENT"]]["ARTICLE_IDS"][] = $data["FK_ARTICLE"];
}
//die(var_dump($mails));
$tmpLanguage = $s_lang;


foreach ($mails as $id_user => $mail_data) {
	foreach ($mail_data["LIST"] as $id_ad_agent => $mail_agent) {
		$mail_content = $mail_agent["CONTENT"];

		$langval = $db->fetch_atom("SELECT BITVAL FROM `lang` WHERE ID_LANG=".$mail_content["FK_LANG"]);
		$s_lang = $db->fetch_atom("SELECT ABBR FROM `lang` WHERE ID_LANG=".$mail_content["FK_LANG"]);

		set_language($ad_user['FK_LANG']);

		$mail_row_text = file_get_contents( CacheTemplate::getHeadFile("tpl/".$s_lang."/ad_agent.mail_row_text.htm") );
		$mail_row_html = file_get_contents( CacheTemplate::getHeadFile("tpl/".$s_lang."/ad_agent.mail_row.htm") );

		$tplBlank = new Template("tpl/de/empty.htm");
		//echo(getcwd()."\n");

		$ads = $db->fetch_table("
			SELECT
				a.*, i.SRC, i.SRC_THUMB, m.NAME as MANUFACTURER,
				DATEDIFF(a.STAMP_END, NOW()) as RUNTIME_LEFT,
				(SELECT NAME FROM `user` WHERE ID_USER=a.FK_USER) as vk_username
			FROM
				`ad_master` a
				LEFT JOIN
					`ad_images` i ON i.FK_AD = a.ID_AD_MASTER
				LEFT JOIN
					`manufacturers` m ON m.ID_MAN = a.FK_MAN
			WHERE
				a.ID_AD_MASTER in (".implode(",", $mail_agent["ARTICLE_IDS"]).")
			GROUP BY
				a.ID_AD_MASTER");

		$mail_content["liste"] = array();
		$mail_content["liste_html"] = array();

		foreach ($ads as $index => $ad_data) {
			$ad_data = array_merge($mail_content, $ad_data);
			// Constraints zuordnung hinzufügen
			Rest_MarketplaceAds::extendAdDetailsSingle($ad_data);
			// Kategorie hinzu
			$ad_data["KATEGORIE"] = $db->fetch_atom("SELECT s.V1 FROM `kat` t
	            									LEFT JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=t.ID_KAT
	              									AND s.BF_LANG=if(t.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
	            								WHERE ID_KAT=".$ad_data['FK_KAT']);
			$mail_content["liste"][] = parse_mail($mail_row_text, $ad_data);
			$mail_content["liste_html"][] = parse_mail($mail_row_html, $ad_data);
		}
		$mail_content["liste"] = implode("\n", $mail_content["liste"]);
		$mail_content["liste_html"] = implode("\n", $mail_content["liste_html"]);

		sendMailTemplateToUser(0, $id_user, 'AD_AGENT', $mail_content);
		$db->querynow("
			UPDATE
				ad_agent
			SET
				LAST_RUN = LAST_RUN+1
			WHERE
				ID_AD_AGENT=".$id_ad_agent);
		$db->querynow("UPDATE `ad_agent_temp` SET MAIL_SENT=1 WHERE FK_AD_AGENT=".$id_ad_agent);
		/*$ad = $db->fetch1("
	  		SELECT
	  			a.*, i.SRC_THUMB, a.ID_".strtoupper($data['TABLE'])." as ID_ARTICLE, m.NAME as MANUFACTURER,
	      		DATEDIFF(a.STAMP_END, NOW()) as RUNTIME_LEFT, a.ID_".strtoupper($data['TABLE'])." as ID_AD
	  		FROM
	  			".$data['TABLE']." a
	  			LEFT JOIN
	  			  `ad_images` i ON i.FK_AD = a.ID_".strtoupper($data['TABLE'])."
	  			LEFT JOIN
	  			  `manufacturers` m ON m.ID_MAN = a.FK_MAN
	  		WHERE
	  			a.ID_".strtoupper($data['TABLE'])."=".$data['FK_ARTICLE']);
		$ad["vk_username"] = $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".$ad["FK_USER"]);*/

	}
}

set_language($tmpLanguage);

?>
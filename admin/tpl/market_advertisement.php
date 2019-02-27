<?php
/* ###VERSIONSBLOCKINLCUDE### */



if (!empty($_REQUEST["done"])) {
	// Bestätigung für letzte Aktion anzeigen
	if ($_REQUEST["done"] == 1) {
		// Werbemittel hinzugefügt
		$tpl_content->addvar("done_add", 1);
	}
	if ($_REQUEST["done"] == 2) {
		// Werbemittel hinzugefügt
		$tpl_content->addvar("done_del", 1);
	}
}

if ((int)$_REQUEST["delete"] > 0) {
	// Eintrag löschen
	$db->querynow("
		DELETE FROM
			`advertisement`
		WHERE
			ID_ADVERTISEMENT=".(int)$_REQUEST["delete"]);
	$db->querynow("
		DELETE FROM
			`string_advertisement`
		WHERE
			FK=".(int)$_REQUEST["delete"]." AND S_TABLE='advertisement'");	
	die(forward("index.php?page=market_advertisement&done=2"));
}

// Vorhandene Werbemittel auslesen ...
$ar_advertisements = $db->fetch_table("
	SELECT
		a.*,
		s.*,(
			 select count(u.ID_ADVERTISEMENT_USER) 
			 	from advertisement_user u 
				 	where u.ENABLED=1 AND (CURDATE() BETWEEN u.STAMP_START AND u.STAMP_END) 
					 and  ID_ADVERTISEMENT =  FK_ADVERTISEMENT and PAID=1
					 ) as BANNER 
	FROM
		`advertisement` a
	LEFT JOIN
		`string_advertisement` s ON
		s.S_TABLE='advertisement' AND s.FK=a.ID_ADVERTISEMENT AND
		s.BF_LANG=if(a.BF_LANG_ADVERTISEMENT & ".$langval.", ".$langval.", 1 << floor(log(a.BF_LANG_ADVERTISEMENT+0.5)/log(2)))
	");
// ... und als Liste darstellen
$tpl_content->addlist("liste", $ar_advertisements, "tpl/de/market_advertisement.row.htm");

?>
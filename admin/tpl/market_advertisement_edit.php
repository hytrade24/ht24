<?php
/* ###VERSIONSBLOCKINLCUDE### */

$taxId = $nar_systemsettings["MARKTPLATZ"]["TAX_DEFAULT"];
$tax = $db->fetch1("SELECT * FROM `tax` WHERE ID_TAX=".(int)$taxId);
$tpl_content->addvar("TAX_PERCENT", $tax["TAX_VALUE"]);

$ar_price = array();

if (!empty($_REQUEST["ID_ADVERTISEMENT"])) {
	// Werbemittel auslesen
	$ar_advertisement = $db->fetch1("
		SELECT
			a.*,
			s.*
		FROM
			`advertisement` a
		LEFT JOIN
			`string_advertisement` s ON
			s.S_TABLE='advertisement' AND s.FK=a.ID_ADVERTISEMENT AND
			s.BF_LANG=if(a.BF_LANG_ADVERTISEMENT & ".$langval.", ".$langval.", 1 << floor(log(a.BF_LANG_ADVERTISEMENT+0.5)/log(2)))
		WHERE
			a.ID_ADVERTISEMENT=".(int)$_REQUEST["ID_ADVERTISEMENT"]);
	$tpl_content->addvars($ar_advertisement);
	// Preise auslesen
	$ar_price = explode("|", $ar_advertisement["COSTS"]);
}

$kat_levels = $db->fetch_atom("
	SELECT
		COUNT(*)-1 as level
	FROM
		kat AS n,
		kat AS p
	WHERE
		(n.lft BETWEEN p.lft AND p.rgt) AND
		(n.ROOT = 1) AND (p.ROOT = n.ROOT)
	GROUP BY n.lft
	ORDER BY level DESC, n.lft
	LIMIT 1");
$ar_price_list = array();
for ($kat_level = 0; $kat_level <= $kat_levels; $kat_level++) {
	$preis = (count($ar_price) >= $kat_level ? $ar_price[$kat_level] : $ar_price[0]);
	$preisBrutto = ($preis * (1 + $tax["TAX_VALUE"] / 100));
	$ar_price_list[] = array(
		"LEVEL"			=>	$kat_level,
		"PREIS"			=>	$preis,
		"PREIS_BRUTTO"	=>	$preisBrutto
	);
}
$tpl_content->addlist("liste", $ar_price_list, "tpl/de/market_advertisement_edit.row.htm");

if (!empty($_POST)) {
	// -- Werbemittel hinzufügen --

	/*
	 * Fehlerüberprüfung
	 */
	$errors = array();

	// Kurzbeschreibung (Pflichtfeld)
	if (empty($_POST["V1"])) {
		$errors[] = "Bitte geben Sie eine Kurzbeschreibung für das Werbemittel ein.";
	}
	if (empty($_POST["T1"])) {
		$errors[] = "Bitte geben Sie eine ausführliche Beschreibung ein.";
	}

	// Hinzufügen oder Fehler ausgeben
	if (empty($errors)) {
		// Keine Fehler, Werbemittel hinzufügen
		if (is_array($_POST["PRICE"])) {
			$_POST["COSTS"] = str_replace(",", ".", implode("|", $_POST["PRICE"]));
		} else {
			$_POST["COSTS"] = "";
		}
		$id_advertisement = $db->update("advertisement", $_POST);
		if ($id_advertisement > 0) {
			die(forward("index.php?page=market_advertisement&done=1"));
		}
	} else {
		// Fehler ausgeben
		$error_text = "- ".implode("\n- ", $errors)."\n";
		$tpl_content->addvar("errors", $error_text);
		$tpl_content->addvars($_POST);
	}
}

?>
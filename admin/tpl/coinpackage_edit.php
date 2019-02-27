<?php
/* ###VERSIONSBLOCKINLCUDE### */



if (!empty($_POST['send'])) {
	$err = '';
	if (empty($_POST['coinpackage_title'])) {
		$err .= 'Bitte Paketbezeichnung angeben!<br>';
	}
	if (empty($_POST['number_of_coins'])) {
		$err .= 'Bitte Anzahl Coins angeben!<br>';
	}
	if (!empty($_POST['number_of_coins']) && !preg_match("/^[0-9]{1,}$/", $_POST['number_of_coins'])) {
		$err .= 'Die Anzahl Coins muss eine Ganzzahl sein!<br>';
	}
	if (empty($_POST['cost'])) {
		$err .= 'Bitte Preis angeben!<br>';
	}
	if (!empty($_POST['cost']) &&  !preg_match("/(^[0-9]{1,})$|(^[0-9]{1,}(\.|\,)[0-9]{1,}$)/", $_POST['cost'])) {
		$err .= 'Bitte geben Sie einen g&uuml;tigen Preis an<br>';
	}
	$tpl_content->addvar('err', $err);
}

if (empty($_POST['ID_COINPACKAGE']) && !empty($_POST['coinpackage_title']) && !empty($_POST['number_of_coins']) &&
	!empty($_POST['cost']) && is_numeric($_POST['number_of_coins']) && is_numeric(str_replace(',', '.', $_POST['cost']))) {
		$db->querynow("INSERT INTO coinpackage (`ID_COINPACKAGE`, `COINPACKAGE_TITLE`, `NUMBER_OF_COINS`, `COST`)
					   VALUES ('', '".mysql_escape_string($_POST['coinpackage_title'])."',
					   '".mysql_escape_string($_POST['number_of_coins'])."',
					   '".mysql_escape_string(str_replace(',', '.', $_POST['cost']))."')");
		forward('index.php?page=coinpackages');
} elseif (!empty($_POST['ID_COINPACKAGE']) && !empty($_POST['coinpackage_title']) && !empty($_POST['number_of_coins']) &&
	!empty($_POST['cost']) && is_numeric($_POST['number_of_coins']) && is_numeric(str_replace(',', '.', $_POST['cost']))) {
	$db->querynow("UPDATE coinpackage SET `COINPACKAGE_TITLE`='".mysql_escape_string($_POST['coinpackage_title'])."',
	`NUMBER_OF_COINS`='".mysql_escape_string($_POST['number_of_coins'])."',
	`COST`='".mysql_escape_string(str_replace(',', '.', $_POST['cost']))."'
	WHERE `ID_COINPACKAGE`='".$_POST['ID_COINPACKAGE']."'");
		forward('index.php?page=coinpackages');
} elseif (!empty($_GET['ID_COINPACKAGE']) || !empty($_POST['ID_COINPACKAGE'])) {
	$result = $db->querynow("SELECT `ID_COINPACKAGE`, `COINPACKAGE_TITLE`, `NUMBER_OF_COINS`, `COST`
							 FROM `coinpackage` WHERE `ID_COINPACKAGE`='".mysql_escape_string($_GET['ID_COINPACKAGE'] ? $_GET['ID_COINPACKAGE'] : $_POST['ID_COINPACKAGE'])."'");
	$row = mysql_fetch_assoc($result['rsrc']);
	$tpl_content->addvar('ID_COINPACKAGE', 		$row['ID_COINPACKAGE']);
	$tpl_content->addvar('COINPACKAGE_TITLE', 	$row['COINPACKAGE_TITLE']);
	$tpl_content->addvar('NUMBER_OF_COINS', 	$row['NUMBER_OF_COINS']);
	$tpl_content->addvar('COST', 				$row['COST']);
}
else {
	if (!empty($_POST['coinpackage_title'])) {
		$tpl_content->addvar('COINPACKAGE_TITLE', $_POST['coinpackage_title']);
	}
	if (!empty($_POST['number_of_coins'])) {
		$tpl_content->addvar('NUMBER_OF_COINS', $_POST['number_of_coins']);
	}
	if (!empty($_POST['cost'])) {
		$tpl_content->addvar('COST', $_POST['cost']);
	}
}

?>
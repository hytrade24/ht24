<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.user_versand.php";

$userVersand = UserVersand::getInstance($db);
if ($_REQUEST["mode"] == "ajax") {
	if ($_REQUEST["what"] == "delete") {
		$id_user_versand = (int)$_REQUEST["id"];
		if ($id_user_versand > 0) {
			$userVersand->removeAddress($id_user_versand);
			header('Content-type: application/json');
			die(json_encode(array("success" => true)));
		} else {
			header('Content-type: application/json');
			die(json_encode(array("success" => false)));
		}
	}
	if ($_REQUEST["what"] == "edit") {
		$id_user_versand = (int)$_POST["ID_USER_VERSAND"];
		if ((empty($_POST['FIRSTNAME']) || empty($_POST["LASTNAME"])) ||
			empty($_POST["STREET"]) || empty($_POST["ZIP"]) || empty($_POST["CITY"]) || empty($_POST["FK_COUNTRY"])) {
			die(json_encode(array(
				"success" 	=> false
			)));
		}
		if ($id_user_versand > 0) {
			$userVersand->updateAddress($id_user_versand, $_POST["COMPANY"], $_POST["FIRSTNAME"], $_POST["LASTNAME"], $_POST["STREET"],
				$_POST["ZIP"], $_POST["CITY"], $_POST["FK_COUNTRY"], $_POST["PHONE"]);
			header('Content-type: application/json');
			die(json_encode(array(
				"success" 	=> true,
				"id"		=> $id_user_versand
			)));
		} else {
			$id_user_versand = $userVersand->addAddress($_POST["COMPANY"], $_POST["FIRSTNAME"], $_POST["LASTNAME"], $_POST["STREET"],
				$_POST["ZIP"], $_POST["CITY"], $_POST["FK_COUNTRY"], $_POST["PHONE"]);
			header('Content-type: application/json');
			die(json_encode(array(
				"success" 	=> true,
				"id"		=> $id_user_versand
			)));
		}
	}
	if ($_REQUEST["what"] == "getById") {
		$ar_address = $userVersand->getAddress($_REQUEST["id"]);
		if ($ar_address === false) {
			$ar_address = array("success" => false);
		} else {
			$ar_address["success"] = true;
		}
		header('Content-type: application/json');
		die(json_encode($ar_address));
	}
}

$ar_liste = $userVersand->getAddresses();

if ($user["FK_COUNTRY"] > 0) {
	$tpl_content->addvar("CURUSER_COUNTRY", $db->fetch_atom("SELECT s.V1 FROM country c
						LEFT JOIN string s ON S_TABLE='country' AND s.FK=c.ID_COUNTRY AND
							s.BF_LANG=if(c.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(c.BF_LANG+0.5)/log(2)))
						WHERE c.ID_COUNTRY=".(int)$user["FK_COUNTRY"]));
}

$id_active = (isset($_POST["ID_USER_VERSAND"]) ? $_POST["ID_USER_VERSAND"] : $user["FK_USER_VERSAND"]);
if (isset($tpl_content->vars['FK_USER_VERSAND'])) {
	$id_active = $tpl_content->vars['FK_USER_VERSAND'];
}
$ar_active = $userVersand->getAddress($id_active);
if ($ar_active != NULL) {
	$tpl_content->addvars($ar_active, 'VERSAND_');
} else {
	$tpl_content->addvar("VERSAND_COMPANY", $user["FIRMA"]);
	$tpl_content->addvar("VERSAND_FIRSTNAME", $user["VORNAME"]);
	$tpl_content->addvar("VERSAND_LASTNAME", $user["NACHNAME"]);
	$tpl_content->addvar("VERSAND_STREET", $user["STRASSE"]);
	$tpl_content->addvar("VERSAND_ZIP", $user["PLZ"]);
	$tpl_content->addvar("VERSAND_CITY", $user["ORT"]);
	$tpl_content->addvar("VERSAND_FK_COUNTRY", $user["FK_COUNTRY"]);
}

$tpl_content->addlist("liste", $ar_liste, "tpl/".$s_lang."/user_versand.row.htm");
$tpl_content->addvar("FK_USER_VERSAND", $id_active);
if (is_array($GLOBALS["user"])) {
	$tpl_content->addvars($GLOBALS["user"], 'CURUSER_');
}

?>
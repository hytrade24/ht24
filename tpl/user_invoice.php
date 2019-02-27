<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.user_invoice.php";

$userInvoice = UserInvoice::getInstance($db);
if ($_REQUEST["mode"] == "ajax") {
	if ($_REQUEST["what"] == "delete") {
		$id_user_invoice = (int)$_REQUEST["id"];
		if ($id_user_invoice > 0) {
			$userInvoice->removeAddress($id_user_invoice);
			header('Content-type: application/json');
			die(json_encode(array("success" => true)));
		} else {
			header('Content-type: application/json');
			die(json_encode(array("success" => false)));
		}
	}
	if ($_REQUEST["what"] == "edit") {
		$id_user_invoice = (int)$_POST["ID_USER_INVOICE"];
		if ((empty($_POST['FIRSTNAME']) || empty($_POST["LASTNAME"])) ||
			empty($_POST["STREET"]) || empty($_POST["ZIP"]) || empty($_POST["CITY"]) || empty($_POST["FK_COUNTRY"])) {
			die(json_encode(array(
				"success" 	=> false
			)));
		}
		if ($id_user_invoice > 0) {
			$userInvoice->updateAddress($id_user_invoice, $_POST["COMPANY"], $_POST["FIRSTNAME"], $_POST["LASTNAME"], $_POST["STREET"],
				$_POST["ZIP"], $_POST["CITY"], $_POST["FK_COUNTRY"], $_POST["PHONE"]);
			header('Content-type: application/json');
			die(json_encode(array(
				"success" 	=> true,
				"id"		=> $id_user_invoice
			)));
		} else {
			$id_user_invoice = $userInvoice->addAddress($_POST["COMPANY"], $_POST["FIRSTNAME"], $_POST["LASTNAME"], $_POST["STREET"],
				$_POST["ZIP"], $_POST["CITY"], $_POST["FK_COUNTRY"], $_POST["PHONE"]);
			header('Content-type: application/json');
			die(json_encode(array(
				"success" 	=> true,
				"id"		=> $id_user_invoice
			)));
		}
	}
	if ($_REQUEST["what"] == "getById") {
		$ar_address = $userInvoice->getAddress($_REQUEST["id"]);
		if ($ar_address === false) {
			$ar_address = array("success" => false);
		} else {
			$ar_address["success"] = true;
		}
		header('Content-type: application/json');
		die(json_encode($ar_address));
	}
}

$ar_liste = $userInvoice->getAddresses();

if ($user["FK_COUNTRY"] > 0) {
	$tpl_content->addvar("CURUSER_COUNTRY", $db->fetch_atom("SELECT s.V1 FROM country c
						LEFT JOIN string s ON S_TABLE='country' AND s.FK=c.ID_COUNTRY AND
							s.BF_LANG=if(c.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(c.BF_LANG+0.5)/log(2)))
						WHERE c.ID_COUNTRY=".(int)$user["FK_COUNTRY"]));
}

$id_active = (isset($_POST["ID_USER_INVOICE"]) ? $_POST["ID_USER_INVOICE"] : $user["FK_USER_INVOICE"]);
if (isset($tpl_content->vars['FK_USER_INVOICE'])) {
	$id_active = (int)$tpl_content->vars['FK_USER_INVOICE'];
}
$ar_active = $userInvoice->getAddress($id_active);
if ($ar_active != NULL) {
	$tpl_content->addvars($ar_active, 'INVOICE_');
} else {
	$tpl_content->addvar("INVOICE_COMPANY", $user["FIRMA"]);
	$tpl_content->addvar("INVOICE_FIRSTNAME", $user["VORNAME"]);
	$tpl_content->addvar("INVOICE_LASTNAME", $user["NACHNAME"]);
	$tpl_content->addvar("INVOICE_STREET", $user["STRASSE"]);
	$tpl_content->addvar("INVOICE_ZIP", $user["PLZ"]);
	$tpl_content->addvar("INVOICE_CITY", $user["ORT"]);
	$tpl_content->addvar("INVOICE_FK_COUNTRY", $user["FK_COUNTRY"]);
}

$tpl_content->addlist("liste", $ar_liste, "tpl/".$s_lang."/user_invoice.row.htm");
$tpl_content->addvar("FK_USER_INVOICE", $id_active);
if (is_array($GLOBALS["user"])) {
	$tpl_content->addvars($GLOBALS["user"], 'CURUSER_');
}

?>
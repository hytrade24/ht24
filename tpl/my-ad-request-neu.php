<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.ad_request.php';

$adRequestManagement = AdRequestManagement::getInstance($db);
$adRequestManagement->setLangval($langval);
        $preselectedTreeNodes = array();

if(isset($_POST) && $_POST['DO'] == "update") {
    $err = array();
    $data = $_POST;

    if($data['PRODUKTNAME'] == "") {
		$tpl_content->addvar("ERR_PRODUKTNAME", 1);

        $err[] = "Bitte geben Sie eine Bezeichnung an";
    }
    if($data['FK_KAT'] == "") {
		$tpl_content->addvar("ERR_FK_KAT", 1);
        $err[] = "Bitte wählen Sie eine Kategorie";
    }

    if($data['BESCHREIBUNG'] == "") {
		$tpl_content->addvar("ERR_BESCHREIBUNG", 1);
        $err[] = "Bitte geben Sie eine Beschreibung an";
    }

    if($data['ID_AD_REQUEST'] != "") {
        $oldAdRequest = $adRequestManagement->find($data['ID_AD_REQUEST']);

        if($oldAdRequest == false || ($oldAdRequest['FK_USER'] != $uid)) {
            $err[] = "Ungültige Anzeige";
        }
    }

    if(count($err) == 0) {
        $data['STAMP_START'] = date("Y-m-d H:i:s");

        $runtimeDays = (int)$nar_systemsettings['MARKTPLATZ']['REQUEST_RUNTIME_DAYS'];
        $data['STAMP_END'] = date("Y-m-d H:i:s", time() + (60*60*24*$runtimeDays));

        $data['STATUS'] = ($nar_systemsettings['MARKTPLATZ']['REQUEST_AUTO_APPROVE'] == 1)?1:0;

        $data['FK_USER'] = $uid;

        if ($data['ID_AD_REQUEST'] > 0) {
        	// Update
        	$db->update("ad_request", $data);
        	die(forward("/my-pages/gesuch_neu,".$data['ID_AD_REQUEST'].",ok.htm"));
        } else {
        	// Insert
        	$id_request = $db->update("ad_request", $data);
        	die(forward("/my-pages/gesuch_neu,".$id_request.",ok.htm"));
        }
    } else {
        $tpl_content->addvar("err", 1);
        $tpl_content->addvar("err_msg", implode("<br>", $err));
    }

    $preselectedTreeNodes = array($_POST['FK_KAT']);
    $tpl_content->addvars($_POST);
} else {
    $adRequestId = ($ar_params[1] ? (int)$ar_params[1] : null);
    if($adRequestId) {
		if ($ar_params[2] == "ok") {
			$tpl_content->addvar("OK", 1);
		}
        $oldAdRequest = $adRequestManagement->find($adRequestId);

        if($oldAdRequest && ($oldAdRequest['FK_USER'] == $uid)) {
            $tpl_content->addvars($oldAdRequest);
            $preselectedTreeNodes = array($oldAdRequest['FK_KAT']);
        }
    }
}

// Prüfung ob Profil ausgefüllt
if (empty($user["VORNAME"]) || empty($user["NACHNAME"]) || empty($user["STRASSE"]) || empty($user["PLZ"]) || empty($user["ORT"])) {
	$tpl_content->addvar("error_noaddress", 1);
	if (empty($user["VORNAME"])) $tpl_content->addvar("error_addr_first", 1);
	if (empty($user["NACHNAME"])) $tpl_content->addvar("error_addr_last", 1);
	if (empty($user["STRASSE"])) $tpl_content->addvar("error_addr_street", 1);
	if (empty($user["PLZ"])) $tpl_content->addvar("error_addr_zip", 1);
	if (empty($user["ORT"])) $tpl_content->addvar("error_addr_city", 1);
}


$categoryTree = $adRequestManagement->getAdRequestCategoryJSONTree($preselectedTreeNodes);

$tpl_content->addvar("CATEGORY_JSON_TREE", $categoryTree);
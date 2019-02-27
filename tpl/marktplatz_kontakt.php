<?php
/* ###VERSIONSBLOCKINLCUDE### */

if (array_key_exists("ajax", $_REQUEST)) {
    switch ($_REQUEST["ajax"]) {
        case "autocompleteArticle":
            if (!$uid) {
                // ERROR MESSAGE!
                die("Not logged in!");
            }
            $arResult = array();
            $phrase = (strlen($_REQUEST["phrase"]) >= 3 ? $_REQUEST["phrase"] : null);
            if ($phrase === null) {
                // Query latest articles 
            } else {
                // Query by title / id
                if (preg_match("/^[0-9]+$/", $phrase)) {
                    // Query by id
                    $searchQuery = Rest_MarketplaceAds::getQueryByParams(array(
                        "FK_USER" => $uid, "SALE_NO_REQUEST" => true,
                        "SEARCH_TEXT_ID" => $phrase
                    ));
                } else {
                    // Query by title
                    $searchQuery = Rest_MarketplaceAds::getQueryByParams(array(
                        "FK_USER" => $uid, "SALE_NO_REQUEST" => true,
                        "SEARCH_TEXT_SHORT" => $phrase
                    ));
                }
            }
            $searchQuery->addField("ID_AD_MASTER");
            $searchQuery->addField("PRODUKTNAME");
            $searchQuery->addField("MENGE");
            $searchQuery->addField("PREIS");
            $searchQuery->addField("IMG_DEFAULT_SRC");
            $searchQuery->setLimit(10);
            $arResult = $searchQuery->fetchTable();
            foreach ($arResult as $itemIndex => $itemDetails) {
                if (!empty($itemDetails["IMG_DEFAULT_SRC"])) {
                    $arResult[$itemIndex]["IMG_DEFAULT_SRC"] = $tpl_content->tpl_uri_baseurl($itemDetails["IMG_DEFAULT_SRC"]);
                }
            }

            header("Content-Type: application/json");
            die(json_encode($arResult));
    }
}

// TODO: Übersetzbare Fehlermeldungen

require_once 'sys/lib.chat.php';
require_once 'sys/lib.chat.user.php';
require_once 'sys/lib.chat.user.virtual.php';

$chatManagement = ChatManagement::getInstance($db);
$chatUserManagement = ChatUserManagement::getInstance($db);
$chatUserVirtualManagement = ChatUserVirtualManagement::getInstance($db);


$kat_table = $db->fetch_atom("
	select
		KAT_TABLE
	from
		kat
	WHERE
		ID_KAT=" . (int)$_REQUEST['ID_KAT']);
$ar_ad = $db->fetch1("
	SELECT
		a.ID_" . strtoupper($kat_table) . " AS ID_AD,
		a.FK_KAT AS FK_KAT,
		a.FK_USER,
		a.PRODUKTNAME,
		a.MENGE,
		a.PREIS,
		(SELECT NAME FROM manufacturers  WHERE ID_MAN=a.FK_MAN) as MANUFACTURER,
		u.`NAME` AS TO_USER,
		am.FK_AD_VARIANT,
		a.VERKAUFSOPTIONEN
	FROM
		" . $kat_table . " a
	LEFT JOIN
		ad_master am ON a.ID_" . strtoupper($kat_table) . "=am.ID_AD_MASTER
	LEFT JOIN
		user u ON a.FK_USER=u.ID_USER
	WHERE
		ID_" . strtoupper($kat_table) . "=" . (int)$_REQUEST['ID_AD']);
if($ar_ad != NULL) {
	$ar_variant = $db->fetch_table("SELECT * FROM `ad_variant2liste_values` WHERE FK_AD_VARIANT=".(int)$_REQUEST['ID_AD_VARIANT']);
	$ar_variant_list = array();
	foreach ($ar_variant as $index => $ar_current) {
		$value = $db->fetch_atom("SELECT sl.V1 FROM `liste_values` t
			LEFT JOIN `string_liste_values` sl
				ON sl.S_TABLE='liste_values' AND sl.FK=t.ID_LISTE_VALUES
				AND sl.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
			WHERE t.ID_LISTE_VALUES=".$ar_current["FK_LISTE_VALUES"]);
		if ($value !== false) {
			$ar_variant_list[] = $value;
		}
	}
	$ar_ad["PRODUKTNAME"] .= (empty($ar_variant_list) ? "" : " (".implode(", ", $ar_variant_list).")");
    if ($ar_ad["VERKAUFSOPTIONEN"] == 3) {
        $tpl_content->addvar("DATE_MIN", date("Y-m-d"));
    }
	$tpl_content->addvars($ar_ad);
} else {
	$tpl_content->addvar("ERR_AD_NOT_FOUND", 1);
}

$tpl_content->addvar("REQUEST_QTY", $ar_ad["MENGE"]);
$tpl_content->addvar("OFFER_ARTICLE_QUANTITY", $ar_ad["MENGE"]);
$tpl_content->addvar("REQUEST_PRICE", $ar_ad["PREIS"]);
$tpl_content->addvar("OFFER_ARTICLE_PRICE", $ar_ad["PREIS"]);

if (!empty($_POST)) {
    $err = array();

    if (empty($_POST['BODY'])) $err[] = 'Keine Nachricht eingegeben!';
    if (!$uid) {
        if (empty($_POST['SENDER'])) {
            $err[] = "Bitte geben Sie Ihren Namen an!";
        }
        if (empty($_POST['SENDER_MAIL'])) {
            $err[] = "Bitte geben Sie Ihre Emailadresse an";
        } else if (!preg_match("/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/i", $_POST["SENDER_MAIL"])) {
            $err[] = "Die angegebene Emailadresse ist ungültig!";
        }
        if (!secure_question($_REQUEST)) {
            $err[] = "Ihre Antwort auf die frage war nicht korrekt!";
        }
    }
    if ($ar_ad["VERKAUFSOPTIONEN"] == 3) {
        if (preg_match("/^([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{4})$/", $_POST["RENT_FROM"], $arDateMatch)) {
            $_POST["RENT_FROM"] = $arDateMatch[3]."-".$arDateMatch[2]."-".$arDateMatch[1];
        }
        if (preg_match("/^([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{4})$/", $_POST["RENT_TO"], $arDateMatch)) {
            $_POST["RENT_TO"] = $arDateMatch[3]."-".$arDateMatch[2]."-".$arDateMatch[1];
        }
        if (!preg_match("/^([0-9]{4})\-([0-9]{1,2})\-([0-9]{1,2})$/", $_POST["RENT_FROM"])) {
            $err[] = Translation::readTranslation("marketplace", "rent.missing.from", null, array(), "Bitte geben Sie das Start-Datum an!");
        }
        if (!preg_match("/^([0-9]{4})\-([0-9]{1,2})\-([0-9]{1,2})$/", $_POST["RENT_TO"])) {
            $err[] = Translation::readTranslation("marketplace", "rent.missing.to", null, array(), "Bitte geben Sie das Rückgabe-Datum an!");
        }
    }
    if (empty($err)) {
        $_POST['FK_USER'] = $db->fetch_atom("SELECT FK_USER FROM `ad_master` WHERE ID_AD_MASTER=".(int)$_POST['ID_AD']);
        if ($ar_ad["VERKAUFSOPTIONEN"] == 3) {
            $tplRent = new Template("tpl/de/empty.htm");
            $tplRent->tpl_text = Translation::readTranslation("marketplace", "rent.message.date", null, array(), "Gewünschter Zeitraum: {todate(FROM)} bis {todate(TO)}\n");
            $tplRent->addvars( array("FROM" => $_POST["RENT_FROM"], "TO" => $_POST["RENT_TO"]) );
            $_POST['BODY'] = $tplRent->process().$_POST['BODY'];
        }
        try {
            $chatId = $chatManagement->addChatForAd($_POST['ID_AD'], $_POST['SUBJECT']);
            $chatManagement->addUserToChat($chatId, $_POST['FK_USER']);

            if($uid) {
                // echter User
                $chatManagement->addUserToChat($chatId, $uid);
                $chatManagement->postMessageByUser($chatId, $uid, $_POST['BODY']);
            } else {
                // virtuellen User
                $virtualUser = $chatUserVirtualManagement->get($_POST['SENDER_MAIL'], $_POST['SENDER']);
                $chatManagement->addVirtualUserToChat($chatId, $virtualUser['ID_CHAT_USER_VIRTUAL']);
                $chatManagement->postMessageByVirtualUser($chatId, $virtualUser['ID_CHAT_USER_VIRTUAL'], $_POST['BODY']);
            }

            $tpl_content->addvar("SENDED", 1);
        } catch(Exception $e) {
            echo $e->getMessage(); die();
        }

        if (array_key_exists("RETURN", $_REQUEST) && ($_REQUEST["RETURN"] == "json")) {
            header("Content-Type: application/json");
            die(json_encode(array("success" => true)));
        }
    } // kein fehler
    else {
        if (array_key_exists("RETURN", $_REQUEST) && ($_REQUEST["RETURN"] == "json")) {
            header("Content-Type: application/json");
            die(json_encode(array("success" => false, "error" => implode("<br />", $err))));
        }
        $tpl_content->addvars($_REQUEST);
        $tpl_content->addvar("err", implode("<br />", $err));
    }
} else {
    $tpl_content->addvar("SUBJECT", trim($ar_ad["MANUFACTURER"]. ' '.$ar_ad["PRODUKTNAME"]));
}
?>
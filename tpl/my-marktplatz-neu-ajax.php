<?php
/* ###VERSIONSBLOCKINLCUDE### */

global $show_paid, $id_kat, $id_root_kat, $kat;

$ajax_page = $_REQUEST["do"];

if ($ajax_page == "kats") {
    $show_paid = ($_REQUEST["paid"] ? 1 : 0);
    $kat = new TreeCategories("kat", 1);
    $id_kat = ($_REQUEST["root"] ? $_REQUEST["root"] : $kat->tree_get_parent());
    $id_root_kat = $kat->tree_get_parent($id_kat);
    die($adCreate->renderStepContent('category', array(
        "ROOT"          => ($_REQUEST["root"] ? $_REQUEST["root"] : $kat->tree_get_parent()),
        "ID_ROOT_KAT"   => $id_root_kat,
        "ID_KAT"        => $id_kat
    )));
}
if ($ajax_page == "input") {
    $ar_kat = $db->fetch1("SELECT B_SALES, SER_OPTIONS FROM kat where ID_KAT=".$id_kat);
    $ar_kat_options = unserialize($ar_kat['SER_OPTIONS']);
    Rest_MarketplaceAds::extendAdDetailsSingle($ar_article);
    $ar_article["B_SALES"] = ($ar_kat['B_SALES'] && $nar_systemsettings['MARKTPLATZ']['BUYING_ENABLED']);
    foreach ($ar_kat_options as $opt_key => $opt_value) {
        $ar_article["OPTIONS_".$opt_key] = $opt_value;
    }
	$ar_article["AD_CONSTRAINTS"] = $nar_systemsettings['MARKTPLATZ']['AD_CONSTRAINTS'];
	$ar_article["USE_PRODUCT_DB"] = $nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB'];
	$ar_article["CURRENCY_DEFAULT"] = $nar_systemsettings['MARKTPLATZ']['CURRENCY'];
	$ar_article["AVAILABILITY"] = (!empty($ar_article["AVAILABILITY"]) ? json_encode(unserialize($ar_article["AVAILABILITY"])) : false);
	$html_input = CategoriesBase::getInputFieldsCache($id_kat, $ar_article, "tpl/".$s_lang."/my-marktplatz-neu.input.htm");
	$html_overview = CategoriesBase::getInputFieldsOverviewCache($id_kat);

	header('Content-type: application/json');
	die(json_encode(array(
			"id_kat"	=> $id_kat,
			"ad_table"	=> $kat_table,
			"input"		=> $html_input,
			"overview"	=> $html_overview
	)));
}
if ($ajax_page == "typeahead_manufacturer") {
	$list = $db->fetch_nar("
    	SELECT
    		ID_MAN, NAME
    	FROM
    		`manufacturers`
    	WHERE
    		NAME LIKE '".mysql_escape_string($_REQUEST["query"])."%' AND
    		CONFIRMED=1
		LIMIT 30");
	header('Content-type: application/json');
	die(json_encode(array_values($list)));
}
if ($ajax_page == "typeahead_product") {
	$id_man = $db->fetch_atom("SELECT ID_MAN FROM `manufacturers` WHERE NAME='".mysql_escape_string($_REQUEST["man"])."'");
	$list = $db->fetch_nar("
		SELECT
			p.ID_PRODUCT, s.V1 as NAME
		FROM `product` p
		LEFT JOIN `string_product` s
	      	ON s.S_TABLE='product' AND s.FK=p.ID_PRODUCT
	        	AND s.BF_LANG=if(p.BF_LANG_PRODUCT & ".$langval.", ".$langval.", 1 << floor(log(p.BF_LANG_PRODUCT+0.5)/log(2)))
		WHERE ".( $id_man > 0 ? "p.FK_MAN=".$id_man." AND " : "")."
			((s.V1 LIKE '%".mysql_escape_string($_REQUEST["query"])."%')
			OR (s.V2 LIKE '%".mysql_escape_string($_REQUEST["query"])."%')) AND
			p.CONFIRMED=1
		LIMIT 30");
	header('Content-type: application/json');
	die(json_encode(array_values($list)));
}
if ($ajax_page == "validate") {
    $name = $_REQUEST["name"];
    $value = $_REQUEST["value"];
    $needed = $_REQUEST["needed"];
    $type = $_REQUEST["valtype"];

    $json_result["valid"] = 1;
    $json_result["error"] = "";
    /**************************************
     * Standard-Felder kontrollieren
     **************************************/
    if (!is_numeric($value) && empty($value) && $needed) {
        $json_result["fname"] = $name;
        $json_result["valid"] = 0;
        $json_result["error"] = "FIELD_NEEDED";
        $json_result["error_msg"] = implode("", get_messages("AD_NEW", $json_result["error"]));
        die(json_encode($json_result));
    }
    switch ($type) {
        /*************************
         * Zahlen
         *************************/
        case 'int':
            if (!empty($value) || $needed) {
                $value = str_replace(",", ".", $value);
                if (round($value) != $value) {
                    $json_result["fname"] = $name;
                    $json_result["valid"] = 0;
                    $json_result["error"] = "NOT_INTEGER";
                }
                if (!is_numeric($value)) {
                    $json_result["fname"] = $name;
                    $json_result["valid"] = 0;
                    $json_result["error"] = "NOT_NUMERIC";
                }
            }
            break;
        case 'float':
            $value = str_replace(",", ".", $value);
            if (!empty($value) || $needed) {
                if (!is_numeric($value)) {
                    $json_result["fname"] = $name;
                    $json_result["valid"] = 0;
                    $json_result["error"] = "NOT_NUMERIC";
                }
            }
            break;
        /*************************
         * Auswahllisten
         *************************/
        case 'list':
        case 'liste':
            if ((!is_numeric($value) || ($value <= 0)) && ($needed == 1)) {
                $json_result["fname"] = $name;
                $json_result["valid"] = 0;
                $json_result["error"] = "INVALID_SELECTION";
            }
            break;
        case 'variant':
        case 'multicheckbox':
        case 'multicheckbox_and':
            if ((!is_numeric($value) || ($value <= 0)) && ($needed == 1)) {
                $json_result["fname"] = $name;
                $json_result["valid"] = 0;
                $json_result["error"] = "FIELD_NEEDED";
            }
            break;
    }
    switch ($name) {
        /*************************
         * Kurzer Text
         *************************/
        case 'PRODUKTNAME':
        case 'ZIP':
        case 'CITY':
            // - Artikelbezeichnung
            // - Postleitzahl
            // - Ort
            if (strlen(trim($value)) < 3) {
                $json_result["fname"] = $name;
                $json_result["valid"] = 0;
                $json_result["error"] = "TOO_SHORT";
            }
            break;
        /*************************
         * Langer Text
         *************************/
        case 'BESCHREIBUNG':
            $value = strip_tags($value);	// Remove html tags
            break;
        /*************************
         * Auswahllisten (Zahl>0)
         *************************/
        case 'LU_LAUFZEIT':
        case 'FK_COUNTRY':
        case 'ZUSTAND':
            // - Land
            // - Versandkosten
            // - Breite, Höhe, Tiefe
            // - Leistung
            if (!is_numeric($value) || ($value <= 0)) {
                $json_result["fname"] = $name;
                $json_result["valid"] = 0;
                $json_result["error"] = "INVALID_SELECTION";
            }
            break;
        /*************************
         * Positive Zahl (größer Null)
         *************************/
        case 'MENGE':
        case 'PREIS':
            $value = str_replace(',', '.', $value);
            if ($value < 0) {
                $json_result["fname"] = $name;
                $json_result["valid"] = 0;
                $json_result["error"] = "NEGATIVE_NUMBER";
            }
            if (!empty($value) || $needed) {
                // Nur prüfen wenn nicht leer oder Pflichtfeld
                if (!is_numeric($value)) {
                    $json_result["fname"] = $name;
                    $json_result["valid"] = 0;
                    $json_result["error"] = "NOT_NUMERIC";
                }
                if ($value < 0.01) {
                    $json_result["fname"] = $name;
                    $json_result["valid"] = 0;
                    $json_result["error"] = "NULL_NUMBER";
                }
            }
            break;
        case 'AUTOBUY':
            $value = str_replace(',', '.', $value);
            if (!empty($value) && ($value != 0)) {
                if (!is_numeric($value)) {
                    $json_result["fname"] = $name;
                    $json_result["valid"] = 0;
                    $json_result["error"] = "NOT_NUMERIC";
                }
                if ($value < 0) {
                    $json_result["fname"] = $name;
                    $json_result["valid"] = 0;
                    $json_result["error"] = "NEGATIVE_NUMBER";
                }
                if ($value < 0.01) {
                    $json_result["fname"] = $name;
                    $json_result["valid"] = 0;
                    $json_result["error"] = "NULL_NUMBER";
                }
            }
            break;
        /*************************
         * Positive Zahl
         *************************/
        case 'VERSANDKOSTEN':
        case 'BREITE': case 'HOEHE': case 'TIEFE':
        case 'LEISTUNG':
            // - Verkaufspreis
            // - Versandkosten
            // - Breite, Höhe, Tiefe
            // - Leistung
            $value = str_replace(',', '.', $value);
            if (!is_numeric($value)) {
                $json_result["fname"] = $name;
                $json_result["valid"] = 0;
                $json_result["error"] = "NOT_NUMERIC";
            }
            if ($value < 0) {
                $json_result["fname"] = $name;
                $json_result["valid"] = 0;
                $json_result["error"] = "NEGATIVE_NUMBER";
            }
            break;
    }
    if (!$json_result["valid"]) {
        $json_result["error_msg"] = implode("", get_messages("AD_NEW", $json_result["error"]));
    }
    header('Content-type: application/json');
    die(json_encode($json_result));
}
if ($ajax_page == "availability") {
	$json_result = array();
	$idAd = (int)$_REQUEST['ID_AD'];
	$timeStart = (int)$_REQUEST['start'];
	$timeEnd = (int)$_REQUEST['end'];
	$dateStart = date('Y-m-d H:i:s', $timeStart);
	$dateEnd = date('Y-m-d H:i:s', $timeEnd);
    // Get translated strings
    $query = "SELECT
            l.VALUE, s.V1
        FROM `lookup` l
        LEFT JOIN `string` s ON s.S_TABLE='lookup' AND s.FK=l.ID_LOOKUP
            AND s.BF_LANG=if(l.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(l.BF_LANG+0.5)/log(2)))
        WHERE l.art='AVAIL'";
    $arStrings = $db->fetch_nar($query);

	require_once $ab_path."sys/lib.ad_availability.php";
	$mAvail = AdAvailabilityManagement::getInstance($idAd, $db);
	$arBlocked = $mAvail->fetchByRange($dateStart, $dateEnd, true, false);
	$json_result = array();
	foreach ($arBlocked as $index => $arEvent) {
		$arEventJson = array(
            'start'		=> date('Y-m-d H:i:s', $arEvent['BEGIN']),
			'end'		=> date('Y-m-d H:i:s', $arEvent['END']),
			'title'		=> $arStrings['na'],
			'allDay'	=> false,
			'editable'	=> false,
			'className'	=> 'pause'
		);
        $json_result[] = $arEventJson;
	}
    $arEvents = $mAvail->fetchEventsByRange($dateStart, $dateEnd);
    foreach ($arEvents as $index => $arEvent) {
        $arEventJson = array(
            'id'        => $arEvent['ID_AD_AVAILABILITY_EVENT'],
            'start'     => $arEvent['BEGIN'],
            'end'       => $arEvent['END'],
            'amount'    => $arEvent['AMOUNT_BLOCKED'],
            'title'     => "(".$arEvent['AMOUNT_BLOCKED'].") ".$arEvent['TITLE'],
            'allDay'    => false,
            'editable'  => true,
            'className' => 'event'
        );
        $json_result[] = $arEventJson;
    }

    header('Content-type: application/json');
    die(json_encode($json_result));
}
if ($ajax_page == "availability_event_delete") {
    $json_result = array('success' => false);
    $id_event = (int)$_POST['id'];
    $id_ad = $db->fetch_atom("SELECT ID_AD_MASTER FROM `ad_master` WHERE ID_AD_MASTER=".(int)$_POST['FK_AD']." AND FK_USER=".(int)$uid);
    if (($id_ad > 0) && ($id_event > 0)) {
        require_once $ab_path."sys/lib.ad_availability.php";
        $mAvail = AdAvailabilityManagement::getInstance($id_ad, $db);
        $json_result['success'] = $mAvail->deleteEvent($id_event);
    }
    header('Content-type: application/json');
    die(json_encode($json_result));
}
if ($ajax_page == "availability_event_move") {
    $json_result = array('success' => false);
    $id_event = (int)$_POST['id'];
    $id_ad = $db->fetch_atom("SELECT ID_AD_MASTER FROM `ad_master` WHERE ID_AD_MASTER=".(int)$_POST['FK_AD']." AND FK_USER=".(int)$uid);
    if (($id_ad > 0) && ($id_event > 0)) {
        require_once $ab_path."sys/lib.ad_availability.php";
        $mAvail = AdAvailabilityManagement::getInstance($id_ad, $db);
        $mAvail->moveEvent($id_event, $_POST['deltas']['days'], $_POST['deltas']['minutes']);
        $json_result['success'] = true;
    }
    header('Content-type: application/json');
    die(json_encode($json_result));
}
if ($ajax_page == "availability_event_resize") {
    $json_result = array('success' => false);
    $id_event = (int)$_POST['id'];
    $id_ad = $db->fetch_atom("SELECT ID_AD_MASTER FROM `ad_master` WHERE ID_AD_MASTER=".(int)$_POST['FK_AD']." AND FK_USER=".(int)$uid);
    if (($id_ad > 0) && ($id_event > 0)) {
        require_once $ab_path."sys/lib.ad_availability.php";
        $mAvail = AdAvailabilityManagement::getInstance($id_ad, $db);
        $mAvail->resizeEvent($id_event, $_POST['deltas']['days'], $_POST['deltas']['minutes']);
        $json_result['success'] = true;
    }
    header('Content-type: application/json');
    die(json_encode($json_result));
}
if ($ajax_page == "availability_event_save") {
    $json_result = array('success' => false);
    $id_event = (int)$_POST['id'];
    $id_ad = $db->fetch_atom("SELECT ID_AD_MASTER FROM `ad_master` WHERE ID_AD_MASTER=".(int)$_POST['FK_AD']." AND FK_USER=".(int)$uid);
    if ($id_ad > 0) {
        require_once $ab_path."sys/lib.ad_availability.php";
        $mAvail = AdAvailabilityManagement::getInstance($id_ad, $db);
        $dateBegin = date('Y-m-d H:i:s', $_POST['from'] / 1000);
        $dateEnd = date('Y-m-d H:i:s', $_POST['to'] / 1000);
        if ($id_event > 0) {
            $mAvail->editEvent($id_event, $_POST['title'], $_POST['amount']);
        } else {
            $id_event = $mAvail->createEvent($dateBegin, $dateEnd, $_POST['title'], $_POST['amount']);
        }
        // Output result
        $json_result = array(
            'success'   => ($id_event > 0),
            'id'        => $id_event
        );
    }
    header('Content-type: application/json');
    die(json_encode($json_result));
}
if ($ajax_page == "variantstable") {
	$variantTable = $adVariantsManagement->getVariantTable($id_article);

	$colModel = array();
	$colNames = array();
	foreach($adVariantsManagement->getAdVariantFieldsById($id_article) as $key => $col) {
		$colNames[] = $col['V1'];
		$colModel[] = array(
			'name' => $col['F_NAME'],
			'index' => $col['F_NAME'],
			'sortable' => FALSE
		);
	}

	$variantTableData = array();
	$hasDefault = false;
	foreach($variantTable as $key => $variantTableRow) {
		$tmpRow = array();
		foreach($variantTableRow['FIELDS'] as $fkey => $field) {
			$tmpRow[$field['F_NAME']] = $field['LISTE_VALUE_V1'];
		}

		$tmpRow['id'] = $variantTableRow['ID_AD_VARIANT'];
		$tmpRow['IS_DEFAULT'] = $variantTableRow["IS_DEFAULT"];
		$tmpRow['MENGE'] = $variantTableRow['MENGE'];
		$tmpRow['PREIS'] = $variantTableRow['PREIS'];
		$tmpRow['STATUS'] = $variantTableRow['STATUS'];
		if ($tmpRow['IS_DEFAULT']) {
			$hasDefault = true;
		}

		$variantTableData[] = $tmpRow;
	}
	if (!empty($variantTableData) && ($hasDefault == false)) {
		$variantTableData[0]['IS_DEFAULT'] = true;
	}

	$tpl = new Template("tpl/".$s_lang."/my-marktplatz-neu.variants.htm");
	$tpl->addvar('COLMODEL', json_encode($colModel));
	$tpl->addvar('COLNAMES', json_encode($colNames));
	$tpl->addvar('VARIANTDATA', json_encode($variantTableData));
	$tpl->addvar('CURRENCY_DEFAULT', $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
	$tpl->addvars($ar_article);

	echo $tpl->process();
	die();
}
if($ajax_page == "savevariantstable") {
	if(isset($_POST['data']) && is_array($_POST['data'])) {
		foreach($_POST['data'] as $key => $value) {
			if(isset($value['id']) && $adVariantsManagement->existVariant($id_article, $value['id'])) {
				$adVariantsManagement->updateVariant($value['id'], $value);
				if ($value["IS_DEFAULT"]) {
					// Menge der Standard-Variante hat sich verändert
					$db->querynow("UPDATE `".$ar_article['AD_TABLE']."`
							SET MENGE=".(int)$value['MENGE'].", PREIS='".(float)str_replace(",", ".", $value['PREIS'])."'
							WHERE ID_AD_MASTER=".$id_article);
					$db->querynow("UPDATE `ad_master`
							SET FK_AD_VARIANT=".(int)$value["id"].",
							MENGE=".(int)$value['MENGE'].", PREIS='".(float)str_replace(",", ".", $value['PREIS'])."'
							WHERE ID_AD_MASTER=".$id_article);
				}
			}
		}
		echo json_encode(array('success' => TRUE)); die();
	} else {
		if(isset($_POST['id']) && $adVariantsManagement->existVariant($id_article, $_POST['id'])) {
			$adVariantsManagement->updateVariant($_POST['id'], $_POST);
			if ($_POST["IS_DEFAULT"]) {
				// Menge der Standard-Variante hat sich verändert
				$db->querynow("UPDATE `".$ar_article['AD_TABLE']."`
						SET MENGE=".(int)$_POST['MENGE'].", PREIS='".(float)str_replace(",", ".", $_POST['PREIS'])."'
						WHERE ID_AD_MASTER=".$id_article);
				$db->querynow("UPDATE `ad_master`
						SET FK_AD_VARIANT=".(int)$_POST["id"].",
							MENGE=".(int)$_POST['MENGE'].", PREIS='".(float)str_replace(",", ".", $_POST['PREIS'])."'
						WHERE ID_AD_MASTER=".$id_article);
			}

			echo json_encode(array('success' => TRUE)); die();
		}
	}

	echo json_encode(array('success' => FALSE)); die();
}
if ($ajax_page == "save") {
	// Datums-Felder
	if(is_array($_POST['dateimplodes']) && !empty($_POST['dateimplodes'])) {
		foreach($_POST['dateimplodes'] as $key => $value) {
			date_implode($_POST, $value);
		}
	}

	$fields_data = array();
	$fields_saved = array();
	// Fehler erkennen / auflisten
	$errors = array();

	if ($id_article > 0) {
		// Aktuellen Datensatz auslesen
		$fields_saved_master = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".$id_article);
		$fields_saved = $db->fetch1("SELECT * FROM `".$kat_table."` WHERE ID_".strtoupper($kat_table)."=".$id_article);
		$fields_saved = array_merge($fields_saved, $fields_saved_master);
		$fields_data["FK_AD_VARIANT"] = $fields_saved["FK_AD_VARIANT"];
	}
	// Listen auslesen
	$fields_saved["tmp_listen"] = implode(",", array_keys($db->fetch_nar(
		"SELECT f.F_NAME FROM field_def f
		LEFT JOIN kat2field kf ON f.ID_FIELD_DEF = kf.FK_FIELD
		        WHERE kf.FK_KAT=".$id_kat." AND f.F_TYP='LIST'")));
	// Erforderliche Felder auslesen
	$fields_needed = array_keys($db->fetch_nar($q =
		"SELECT f.F_NAME FROM field_def f
		LEFT JOIN kat2field kf ON f.ID_FIELD_DEF = kf.FK_FIELD
		        WHERE kf.FK_KAT=".$id_kat." AND kf.B_NEEDED=1"));
	$fields_needed[] = "LU_LAUFZEIT";

	if ((int)$fields_data['MOQ'] < 1) {
		$fields_data['MOQ'] = 1;	// Mindestbestellmenge von mindestens 1
	}
	$fields_data = array_merge($fields_data, $_POST);
	if (is_array($fields_data['availability'])) {
		require_once $ab_path."sys/lib.ad_availability.php";
		$ar_times = AdAvailabilityManagement::optimizeWorkTimes($fields_data['availability']);
		$fields_data['AVAILABILITY'] = serialize($ar_times);
	} else {
		$fields_data['AVAILABILITY'] = null;
	}

	foreach ($_POST["tmp_type"] as $field => $type) {
		if (in_array($field, $fields_needed)) {
			// PFLICHTFELD!


			// Nicht ausgefülltes Pflicht-Feld
			if (($fields_data[$field] != 0) && empty($fields_data[$field]))
			$errors[] = "ERR_MISSING_".$field;

			// Nicht ausgefülltes Pflicht-Feld (LISTE!!)
			if (($type == "list") && ($fields_data[$field] == 0)) {
				$errors[] = "ERR_MISSING_".$field;
			}
			if(($type == "variant") && (!isset($fields_data['variants'][$field]) || (count($fields_data['variants'][$field]) <= 0))) {
				$errors[] = "ERR_MISSING_".$field;
			}
            if(($type == "multicheckbox") && (!isset($fields_data['check'][$field]) || (count($fields_data['check'][$field]) <= 0))) {
                $errors[] = "ERR_MISSING_".$field;
            }
            if(($type == "multicheckbox_and") && (!isset($fields_data['check'][$field]) || (count($fields_data['check'][$field]) <= 0))) {
                $errors[] = "ERR_MISSING_".$field;
            }
		}

		if (($type == "multicheckbox") || ($type == "multicheckbox_and")) {
			$fields_data[$field] = '';
			if(isset($fields_data['check'][$field])) {
				$fields_data[$field] = "x".implode("x", $fields_data['check'][$field])."x";
			}
		}

		if ($type == "checkbox" && !isset($fields_data[$field])) {
			$fields_data[$field] = 0;
		}

		if (is_array($fields_data[$field]) && strpos($field, "BF_") === 0) {
			// Bitflag / array
		} else {
			// Feld ist gesetzt
			if (isset($fields_data[$field]) && ($fields_data[$field] != "")) {
				// Keine Zahl in Integer-Feld
				if (($type == "int") && (!is_numeric($fields_data[$field])))
					$errors[] = "ERR_WRONG_".$field;
				// Keine Zahl in Float-Feld
				if (($type == "float") && (!is_numeric(str_replace(",", ".", $fields_data[$field]))))
					$errors[] = "ERR_WRONG_".$field;
				else if ($type == "float")
					$fields_data[$field] = str_replace(",", ".", $fields_data[$field]);
			} else {
				//unset($fields_data[$field]);
			}
		}
	}
	if (!empty($errors)) {
		die(var_dump($errors));
		$errors = get_messages("MARKTPLATZ", implode(",", $errors));
		$tpl_content->addvar("errors", "- ".implode("<br />- ", $errors));
		$fields_data = array_merge($fields_saved, $fields_data);
	} else {
		// Eingaben ergänzen / verarbeiten
		$fields_data["AD_AGB"] = (isset($fields_data["AD_AGB"]) ? $fields_data["AD_AGB"] : null);
		$fields_data["AD_WIDERRUF"] = (isset($fields_data["AD_WIDERRUF"]) ? $fields_data["AD_WIDERRUF"] : null);
        $fields_data["FK_USER"] = $uid;
        $fields_data['AD_TABLE'] = $kat_table;
		$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$id_kat);
		if($nar_systemsettings['MARKTPLATZ']['ALLOW_HTML'] == 0) {
			$fields_data["BESCHREIBUNG"] = strip_tags($fields_data["BESCHREIBUNG"], "<a><br><span><p><u><em><strong><ol><ul><li>");
		} else {
			$fields_data["BESCHREIBUNG"] = $fields_data["BESCHREIBUNG"];
		}
		$fields_data["BF_CONSTRAINTS"] = 0;
		if ($nar_systemsettings['MARKTPLATZ']['AD_CONSTRAINTS']) {
			if (is_array($_POST["BF_CONSTRAINTS"])) {
				foreach ($_POST["BF_CONSTRAINTS"] as $key => $value) {
					$fields_data["BF_CONSTRAINTS"] += $value;
				}
			}
		}
        if (empty($fields_data["AUTOBUY"])) {
            $fields_data["AUTOBUY"] = 0;
        }
        if (empty($fields_data["AUTOCONFIRM"])) {
            $fields_data["AUTOCONFIRM"] = 0;
        }
		if (empty($fields_data["TRADE"])) {
			$fields_data["TRADE"] = 0;
		}
		// Herstellerdatenbank
		if ($nar_systemsettings["MARKTPLATZ"]["USE_PRODUCT_DB"] && !empty($fields_data["HERSTELLER"])) {
			$id_man = $db->fetch_atom("SELECT ID_MAN FROM `manufacturers` WHERE NAME='".mysql_escape_string($fields_data["HERSTELLER"])."'");
			if (!$id_man) {
				$res = $db->querynow("INSERT INTO `manufacturers` (NAME, CONFIRMED) VALUES ('".mysql_escape_string($fields_data["HERSTELLER"])."', 0)");
				$id_man = $res['int_result'];
			}
			if ($id_man > 0) {
				$fields_data["FK_MAN"] = $id_man;
			}
			// Produktdatenbank
			$id_product = $db->fetch_atom("
				SELECT
					p.ID_PRODUCT
				FROM `product` p
				LEFT JOIN `string_product` s
			      	ON s.S_TABLE='product' AND s.FK=p.ID_PRODUCT
			        	AND s.BF_LANG=if(p.BF_LANG_PRODUCT & ".$langval.", ".$langval.", 1 << floor(log(p.BF_LANG_PRODUCT+0.5)/log(2)))
				WHERE ".( $id_man > 0 ? "p.FK_MAN=".$id_man." AND " : "")."s.V1='".mysql_escape_string($fields_data["PRODUKTNAME"])."'");
			if (!$id_product) {
				$ar_product = array(
					"FK_MAN"	=> $id_man,
					"V1"		=> $fields_data["PRODUKTNAME"]
				);
				$id_product = $db->update("product", $ar_product);
			}
			if ($id_product > 0) {
				$fields_data["FK_PRODUCT"] = $id_product;
			}
		}
		if ($id_article > 0) {

			// remove old article table if category has changed
			$oldArticleMaster = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".$id_article);
			if($oldArticleMaster['AD_TABLE'] !== $kat_table) {
				$db->delete($oldArticleMaster['AD_TABLE'], $id_article);

				$id_name = 'ID_'.strtoupper($kat_table);
				$db->querynow("INSERT INTO `".mysql_real_escape_string($kat_table)."` (".$id_name.")
									VALUES (".$id_article.")");
			}

			/**
			 * BESTEHENDE ANZEIGE
			 */
			// Artikel-Tabelle updaten
			$id_name = 'ID_'.strtoupper($kat_table);
			$fields_data[$id_name] = $id_article;

			$db->update($kat_table, $fields_data);
			$db->querynow("UPDATE `verstoss`
							SET STAMP_AD_UPDATE=NOW()
							WHERE FK_AD=".$id_article);
			// Master-Tabelle updaten
			unset($fields_data[$id_name]);
			$fields_data['ID_AD_MASTER'] = $id_article;
			$db->update("ad_master", $fields_data);
		} else {
			/**
			 * NEUE ANZEIGE
			 */
			// Eintrag in der Master-Tabelle anlegen
	        $fields_data["STATUS"] = 0;
	        $fields_data['CRON_STAT'] = -1;
            if (!isset($fields_data["ALLOW_COMMENTS"]) && ($uid > 0)) {
                // Read default setting
                $userAllowComments = $db->fetch_atom("SELECT if(ALLOW_COMMENTS&1 > 0,1,0) FROM `usersettings` WHERE FK_USER=".$uid);
                $fields_data["ALLOW_COMMENTS"] = $userAllowComments;
            }
			$id_article = $db->update("ad_master", $fields_data);
			// Eintrag in der Artikel-Tabelle anlegen
			unset($fields_data["ID_AD_MASTER"]);
			$id_name = 'ID_'.strtoupper($kat_table);
			$db->querynow("INSERT INTO `".mysql_escape_string($kat_table)."` (".$id_name.")
					VALUES (".$id_article.")");
			$fields_data[$id_name] = $id_article;
			$db->update($kat_table, $fields_data);
			$db->querynow("UPDATE `verstoss`
							SET STAMP_AD_UPDATE=NOW()
							WHERE FK_AD=".$id_article);
		}

		if (is_array($_POST['availability'])) {
			$mAvail = AdAvailabilityManagement::getInstance($id_article, $db);
			$ar_avail = $_POST['availability'];
			foreach ($ar_avail as $dateBegin => $ar_avail_to) {
				foreach ($ar_avail_to as $dateEnd => $ar_avail_week) {
					if (preg_match("/^([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})$/", $dateBegin, $arStart)
						&& preg_match("/^([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})$/", $dateEnd, $arEnd)) {
						$dateFrom = $arStart[3]."-".$arStart[2]."-".$arStart[1];
						$dateTo = $arEnd[3]."-".$arEnd[2]."-".$arEnd[1];
						$ar_worktimes = array();
						foreach ($ar_avail_week as $weekday => $ar_times) {
							while (count($ar_times) >= 2) {
								$ar_worktimes[] = array(
									"WEEKDAY"	=> $weekday,
									"BEGIN"		=> array_shift($ar_times).":00",
									"END"		=> array_shift($ar_times).":00"
								);
							}
						}
						$mAvail->createAdAvailabilityRange($dateFrom, $dateTo, $ar_worktimes, $fields_data['MENGE']);
					}
				}
			}
		}

		// Varianten
		if((isset($fields_data['variants']) && count($fields_data['variants']) > 0)) {
			/** @var AdVariantsManagement $adVariantsManagement */
			$adVariantsManagement->generateAdVariantTable($id_article, $fields_data['variants'], $fields_data);
		}

		// Payment Adapter
		$adPaymentAdapterManagement->updatePaymentAdapterForAd($id_article, $fields_data['ad_payment_adapter']);

		header('Content-type: application/json');
		die(json_encode(array(
			"id_ad"		=> $id_article,
			"success"	=> true
		)));
	}

	header('Content-type: application/json');
	die(json_encode(array(
		"id_ad"		=> "",
		"errors"	=> $errors,
		"success"	=> false
	)));
}
if ($ajax_page == "upload") {
    $error = array();
    $success = true;
    $image_count = 0;
    $video_count = 0;
    $upload_count = 0;
    if ($id_article > 0) {
        $image_count = $db->fetch_atom("SELECT count(*) FROM `ad_images` WHERE FK_AD=".$id_article);
        $video_count = $db->fetch_atom("SELECT count(*) FROM `ad_video` WHERE FK_AD=".$id_article);
        $upload_count = $db->fetch_atom("SELECT count(*) FROM `ad_upload` WHERE FK_AD=".$id_article);
    } else {
        $image_count = count($ar_article['images']);
        $video_count = count($ar_article['videos']);
        $upload_count = count($ar_article['uploads']);
    }
    $images_max = 10;
    $images_left = (($ar_packet_usage["images_available"] + $image_count) > $images_max ? $images_max - $image_count : $ar_packet_usage["images_available"]);
    $images_limit = $image_count + $images_left;
    $videos_max = 10;
    $videos_left = (($ar_packet_usage["videos_available"] + $video_count) > $videos_max ? $videos_max - $video_count : $ar_packet_usage["videos_available"]);
    $videos_limit = $video_count + $videos_left;
    $upload_formats = $nar_systemsettings['MARKTPLATZ']['UPLOAD_TYPES'];
    $uploads_max = 10;
    $uploads_left = (($ar_packet_usage["downloads_available"] + $upload_count) > $uploads_max ? $uploads_max - $upload_count : $ar_packet_usage["downloads_available"]);
    $uploads_limit = $upload_count + $uploads_left;
    if (!empty($_FILES)) {
        $success = false;
        if (isset($_FILES["UPLOAD_IMAGE"]) && ($images_left > 0)) {
            /*
             * BILD UPLOAD
             */
            $image_default = ($image_count == 0);
            $uploads_dir = AdManagment::getAdCachePath($id_article, true);
            if ($_FILES["UPLOAD_IMAGE"]["error"] == UPLOAD_ERR_OK) {
                if ($id_article > 0) {
                    // Save to filesystem/database
                    require_once("sys/lib.image.php");
                    $tmp_name = $_FILES["UPLOAD_IMAGE"]["tmp_name"];
                    $name = $_FILES["UPLOAD_IMAGE"]["name"];
                    $img_thumb = new image(12, $uploads_dir, true);
                    $img_thumb->check_file(array("tmp_name"=>$tmp_name,"name"=>$name));
                    $src = "/".str_replace($ab_path, "", $img_thumb->img);
                    $src_thumb = "/".str_replace($ab_path, "", $img_thumb->thumb);
                    $image_data = array(
                        "FK_AD"       => $id_article,
                        "CUSTOM"      => 1,
                        "IS_DEFAULT"  => ($image_default > 0 ? 0 : 1),
                        "SRC"         => $src,
                        "SRC_THUMB"   => $src_thumb
                    );
                    $id_image = $db->update("ad_images", $image_data, true);
                    if ($id_image > 0) {
                        $success = true;
                        //$order->itemAddContent(PacketManagement::getType($ar_packet_usage["images_type"]), $id_image);
                    } else {
                        $error[] = "UPLOAD_IMAGE_FAILED_DATABASE";
                    }
                } else {
                    // Keep in temp and write to session
                    require_once("sys/lib.image.php");
                    $tmp_dir = sys_get_temp_dir();
                    $tmp_name = $_FILES["UPLOAD_IMAGE"]["tmp_name"];
                    $name = $_FILES["UPLOAD_IMAGE"]["name"];
                    //move_uploaded_file($_FILES['UPLOAD_IMAGE']['tmp_name'], $temp_file);
                    $img_thumb = new image(12, $tmp_dir, true);
                    $img_thumb->check_file(array("tmp_name"=>$tmp_name,"name"=>$name));
                    $ar_image = array(
                        'FK_AD'         => 0,
                        'CUSTOM'        => 1,
                        'IS_DEFAULT'    => (empty($_SESSION['article']['images']) ? true : false),
                        'TMP'           => $img_thumb->img,
                        'TMP_THUMB'     => $img_thumb->thumb,
                        'FILENAME'      => $_FILES["UPLOAD_IMAGE"]["name"],
                        'TYPE'          => $_FILES["UPLOAD_IMAGE"]["type"]
                    );
                    // Add to session
                    $_SESSION['article']['images'][] = $ar_image;
                    $success = true;
                }
            } else {
                $error[] = "UPLOAD_IMAGE_FAILED_SERVER";
            }
        }
        if (isset($_FILES["UPLOAD_FILE"]) && ($uploads_left > 0)) {
            /*
             * DATEI UPLOAD
             */
            $uploads_dir = AdManagment::getAdCachePath($id_article, true);
            if ($_FILES["UPLOAD_FILE"]["error"] == UPLOAD_ERR_OK) {
                $folder = AdManagment::getAdCachePath($id_article, true);
                $filename = $_FILES['UPLOAD_FILE']['name'];
                $hack = explode(".", $filename);
                $n = count($hack)-1;
                $ext = $hack[$n];
                $filename = preg_replace("/(^.*)(\.".$ext."$)/si", "$1", $filename);
                $src = $folder.'/'.$filename.'_x_'.time().'_x_.'.$ext;
                $allowed = explode(',', $upload_formats);
                if(!in_array($ext, $allowed)) {
                    $error[] = "NOT_ALLOWED";
                }
                if (empty($error)) {
                    if ($id_article > 0) {
                        // Save to filesystem/database
                        $ar_upload = array(
                            'FK_AD' 	=> $id_article,
                            'SRC'		=> $src,
                            'FILENAME'	=> $filename,
                            'EXT'		=> $ext
                        );
                        move_uploaded_file($_FILES['UPLOAD_FILE']['tmp_name'], $src);
                        $id_upload = $db->update("ad_upload", $ar_upload, true);
                        if ($id_upload > 0) {
                            $success = true;
                            //$order->itemAddContent(PacketManagement::getType($ar_packet_usage["downloads_type"]), $id_upload);
                        } else {
                            $error[] = "UPLOAD_FILE_FAILED_DATABASE";
                        }
                    } else {
                        // Keep in temp and write to session
                        $temp_file = tempnam(sys_get_temp_dir(), 'AdUpload');
                        move_uploaded_file($_FILES['UPLOAD_FILE']['tmp_name'], $temp_file);
                        $ar_upload = $_FILES['UPLOAD_FILE'];
                        $ar_upload['FK_AD'] = 0;
                        $ar_upload['EXT'] = $ext;
                        $ar_upload['TMP'] = $temp_file;
                        $ar_upload['FILENAME'] = $filename;
                        $_SESSION['article']['uploads'][] = $ar_upload;
                        $success = true;
                    }
                }
            } else {
                $error[] = "UPLOAD_FILE_FAILED_SERVER";
            }
        }
    }
    if (isset($_POST["youtube_url"]) && ($videos_left > 0)) {
        /*
         * Video-Upload
         */
        $url = $_POST["youtube_url"];
        $code = Youtube::ExtractCodeFromURL($_REQUEST["youtube_url"]);
        if ($code != false) {
            $video_data = array(
                "FK_AD"       => $id_article,
                "CODE"	      => $code
            );
            if ($id_article > 0) {
                // Save to filesystem/database
                $id_video = $db->update("ad_video", $video_data, true);
                if ($id_video > 0) {
                    // Erfolg!
                    $success = true;
                    //$order->itemAddContent(PacketManagement::getType($ar_packet_usage["videos_type"]), $id_video);
                } else {
                    $error[] = "UPLOAD_VIDEO_FAILED_DATABASE";
                }
            } else {
                // Write to session
                $_SESSION['article']['videos'][] = $video_data;
                $success = true;
            }
        } else {
            $error[] = "UPLOAD_VIDEO_FAILED_ANALYSE";
        }
        // Liste der Videos zurückgeben
        $_REQUEST["show"] = "videos";
    }
    if (isset($_REQUEST["action"])) {
        /*
         * Einge Upload-Bezogene ajax-funktionen
         */
        switch ($_REQUEST["action"]) {
            case "image_default":
                // Standard-Bild setzen
                $id_image = (int)$_REQUEST["id"];
                if ($id_article > 0) {
                    // Save to filesystem/database
                    $ar_image = $db->fetch1("
                        SELECT ad_images.*
                        FROM ad_images
                        JOIN ad_master ON ad_master.ID_AD_MASTER = ad_images.FK_AD AND ad_master.FK_USER=".$uid."
                        WHERE ad_images.ID_IMAGE=".$id_image);
                    if (!empty($ar_image)) {
                        $db->querynow("UPDATE `ad_images` SET IS_DEFAULT=0 WHERE FK_AD=".$ar_image["FK_AD"]);
                        $db->querynow("UPDATE `ad_images` SET IS_DEFAULT=1 WHERE FK_AD=".$ar_image["FK_AD"]." AND ID_IMAGE=".$id_image);
                        $_REQUEST["show"] = "images";
                    } else {
                        $success = false;
                    }
                } else {
                    // Write to session
                    foreach ($_SESSION['article']['images'] as $index => $ar_image) {
                        $_SESSION['article']['images'][$index]['IS_DEFAULT'] = 0;
                    }
                    $_SESSION['article']['images'][$id_image]['IS_DEFAULT'] = 1;
                    $_REQUEST["show"] = "images";
                    $success = true;
                }
                break;
            case "image_delete":
                // Bild löschen
                $id_image = (int)$_REQUEST["id"];
                if ($id_article > 0) {
                    // Save to filesystem/database
                    $ar_image = $db->fetch1("
                        SELECT ad_images.*
                        FROM ad_images
                        JOIN ad_master ON ad_master.ID_AD_MASTER = ad_images.FK_AD AND ad_master.FK_USER=".$uid."
                        WHERE ad_images.ID_IMAGE=".$id_image);
                    if (!empty($ar_image)) {
                        if ($image_delete["CUSTOM"] == 1) {
                            @unlink($ab_path.substr($ar_image["SRC"], 1));
                            @unlink($ab_path.substr($ar_image["SRC_THUMB"], 1));
                        }
                        $db->querynow("DELETE FROM `ad_images` WHERE ID_IMAGE=".$id_image);
                        if (($order != null) && $order->isRecurring()) {
                            $order->itemRemContent(PacketManagement::getType($ar_packet_usage["images_type"]), $id_image);
                        }
                        // Update default image
                        $image_default = $db->fetch_atom("SELECT count(*) FROM `ad_images` WHERE FK_AD=".$id_article." AND IS_DEFAULT=1");
                        if ($image_default == 0) {
                            $db->querynow("UPDATE `ad_images` SET IS_DEFAULT=1 WHERE FK_AD=".$id_article." LIMIT 1");
                        }
                        $_REQUEST["show"] = "images";
                    } else {
                        $success = false;
                    }
                } else {
                    // Write to session
                    array_splice($_SESSION['article']['images'], $id_image, 1);
                    if (count($_SESSION['article']['images']) > 0) {
                        $hasDefault = false;
                        // Ensure one default
                        foreach ($_SESSION['article']['images'] as $index => $ar_image) {
                            if ($_SESSION['article']['images'][$index]['IS_DEFAULT']) $hasDefault = true;
                        }
                        if (!$hasDefault) {
                            // No default! Set first one...
                            $_SESSION['article']['images'][0]['IS_DEFAULT'] = 1;
                        }
                    }
                    $_REQUEST["show"] = "images";
                    $success = true;
                }
                break;
            case "document_delete":
                // Dokument löschen
                $id_ad_upload = (int)$_REQUEST["id"];
                if ($id_article > 0) {
                    // Save to filesystem/database
                    $ar_ad_upload = $db->fetch1("
                        SELECT ad_upload.*
                        FROM ad_upload
                        JOIN ad_master ON ad_master.ID_AD_MASTER = ad_upload.FK_AD AND ad_master.FK_USER=".$uid."
                        WHERE ad_upload.ID_AD_UPLOAD=".$id_ad_upload);
                    if (!empty($ar_ad_upload)) {
                        @unlink($ar_ad_upload['SRC']);
                        $db->querynow("DELETE FROM ad_upload WHERE ID_AD_UPLOAD=".$id_ad_upload);
                        // Aktives Paket aktuallisieren
                        if (($order != null) && $order->isRecurring()) {
                            $order->itemRemContent(PacketManagement::getType($ar_packet_usage["downloads_type"]), $id_ad_upload);
                        }
                        $_REQUEST["show"] = "documents";
                    } else {
                        $success = false;
                    }
                } else {
                    // Write to session
                    array_splice($_SESSION['article']['uploads'], $id_ad_upload, 1);
                    $_REQUEST["show"] = "documents";
                    $success = true;
                }
                break;
            case "video_delete":
                // Video löschen
                $id_ad_video = (int)$_REQUEST["id"];
                if ($id_article > 0) {
                    // Save to filesystem/database
                    $ar_video = $db->fetch1("
                        SELECT ad_video.*
                        FROM ad_video
                        JOIN ad_master ON ad_master.ID_AD_MASTER = ad_video.FK_AD AND ad_master.FK_USER=".$uid."
                        WHERE ad_video.ID_AD_VIDEO=".$id_ad_video);
                    if (!empty($ar_video)) {
                        $db->querynow("DELETE FROM `ad_video` WHERE ID_AD_VIDEO=".$id_ad_video);
                        // Aktives Paket aktuallisieren
                        if (($order != null) && $order->isRecurring()) {
                            $order->itemRemContent(PacketManagement::getType($ar_packet_usage["videos_type"]), $id_ad_video);
                        }
                        $_REQUEST["show"] = "videos";
                    } else {
                        $success = false;
                    }
                } else {
                    // Write to session
                    array_splice($_SESSION['article']['videos'], $id_ad_video, 1);
                    $_REQUEST["show"] = "videos";
                    $success = true;
                }
                break;
        }
    }
    $ar_html = array();
    // Aktuelle Bilder
    $ar_liste = array();
    if ($id_article > 0) {
        $ar_liste = $db->fetch_table("SELECT * FROM `ad_images` WHERE FK_AD=".$id_article);
    } else {
        $ar_liste = $_SESSION['article']['images'];
    }
    $tpl_liste = new Template("tpl/".$s_lang."/my-marktplatz-neu-images.htm");
    $tpl_liste->addlist("liste", $ar_liste, "tpl/".$s_lang."/my-marktplatz-neu-images.row.htm", "addBase64");
    $ar_html["images"] = $tpl_liste->process(true);
    // Aktuelle Dokumente
    $ar_liste = array();
    if ($id_article > 0) {
        $ar_liste = $db->fetch_table("SELECT *, LEFT(FILENAME, 30) AS FILENAME_SHORT FROM `ad_upload` WHERE FK_AD=".$id_article);
    } else {
        $ar_liste = $_SESSION['article']['uploads'];
    }
    $tpl_liste = new Template("tpl/".$s_lang."/my-marktplatz-neu-documents.htm");
    $tpl_liste->addlist("liste", $ar_liste, "tpl/".$s_lang."/my-marktplatz-neu-documents.row.htm");
    $ar_html["documents"] = $tpl_liste->process(true);
    // Aktuelle Videos
    $ar_liste = array();
    if ($id_article > 0) {
        $ar_liste = $db->fetch_table("SELECT * FROM `ad_video` WHERE FK_AD=".$id_article);
    } else {
        $ar_liste = $_SESSION['article']['videos'];
    }
    $tpl_liste = new Template("tpl/".$s_lang."/my-marktplatz-neu-videos.htm");
    $tpl_liste->addlist("liste", $ar_liste, "tpl/".$s_lang."/my-marktplatz-neu-videos.row.htm");
    $ar_html["videos"] = $tpl_liste->process(true);
    // Spezifischen bereich ausgeben?
    if (isset($_REQUEST["show"])) {
        if (isset($ar_html[$_REQUEST["show"]])) {
            die($ar_html[$_REQUEST["show"]]);
        }
    }
    if (empty($error) && !$success) {
        $error[] = "UNKNOWN_ERROR";
    }
    header('Content-type: text/html');
    die(json_encode(array(
        "success"	=> $success,
        "errors"	=> $error,
        "html"		=> $ar_html,
        "packet"	=> array(
            "ads_new"			=> $ar_packet_usage["ads_required"],
            "images_used"		=> $image_count,
            "images_new"		=> $ar_packet_usage["images_required"],
            "images_free"		=> $nar_systemsettings["MARKTPLATZ"]["FREE_IMAGES"],
            "images_left"		=> $images_left,
            "images_max"		=> $images_limit,
            "videos_used"		=> $video_count,
            "videos_new"		=> $ar_packet_usage["videos_required"],
            "videos_free"		=> $nar_systemsettings["MARKTPLATZ"]["FREE_VIDEOS"],
            "videos_left"		=> $videos_left,
            "videos_max"		=> $videos_limit,
            "downloads_used"	=> $upload_count,
            "downloads_new"		=> $ar_packet_usage["downloads_required"],
            "downloads_free"	=> $nar_systemsettings["MARKTPLATZ"]["FREE_UPLOADS"],
            "downloads_left"	=> $uploads_left,
            "downloads_max"		=> $uploads_limit,
            "downloads_format"	=> $upload_formats
        )
    )));
}
if ($ajax_page == "finish") {
	require_once $ab_path."cron/debug.php";
	require_once $ab_path."cron/admaker_inc.php";
	require_once $ab_path."sys/lib.ads.php";
	### Logdatei öffnen
	global $debug_echo;
	$debug_echo = false;	// Bei true werden alle Debug ausgaben auch per echo ausgegeben
	Debug_Open($ab_path."cache/cronjob_ads.txt");
	### Klassen initialisieren
	$kat_cache = new CategoriesCache();

	$err = array();
	$article_temp = array(
		"ID_AD_MASTER"	=>	(int)$id_article,
		"AD_TABLE"		=>	$kat_table,
		"CRON_STAT"		=>	NULL,
		"CRON_DONE"		=>	0,
	);

	if (AdManagment::Enable($id_article, $kat_table)) {
		header('Content-type: application/json');
		die(json_encode(array(
            "url"		=> $tpl_content->tpl_uri_action("marktplatz_anzeige,".$id_article.",".$id_kat.",neu"),
			"success"	=> true
		)));
	} else {
		header('Content-type: application/json');
		die(json_encode(array(
			"errors"	=> " - ".implode("\n - ", $err)."\n",
			"success"	=> false
		)));
	}
}

?>

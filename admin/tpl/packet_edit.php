<?php
/* ###VERSIONSBLOCKINLCUDE### */
// var_dump($_POST);
function setPacketType(&$row, $i) {
	global $ar_packet_content;

	if (isset($_POST['COUNT'])) {
		if (isset($_POST['COUNT'][$row['ID_PACKET']])) {
			$row['COUNT'] = $_POST['COUNT'][$row['ID_PACKET']];
		}
		else {
			$row["COUNT"] = 0;
		}
	}
	else {
		if (isset($ar_packet_content[$row["ID_PACKET"]])) {
			$row["COUNT"] = $ar_packet_content[$row["ID_PACKET"]];
		} else {
			$row["COUNT"] = 0;
		}
	}

	$row["TYPE_".$row["TYPE"]] = 1;
}

function setUsergroup(&$row, $i)
{
	if (isset($_POST['USERGROUP'])) {
		if (isset($_POST['USERGROUP'][$row['ID_USERGROUP']])) {
			$row['ACTIVE'] = 1;
		}
	}
}

function setPacketRuntime(&$row, $i) {
	$row["CYCLE_".$row["BILLING_CYCLE"]] = 1;
}

$SILENCE = false;

require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);

$taxId = $nar_systemsettings["MARKTPLATZ"]["TAX_DEFAULT"];
$taxList = $db->fetch_nar("SELECT ID_TAX, TAX_VALUE FROM `tax`");

$tpl_content->addvar("jsonTax", json_encode($taxList));

if (isset($_REQUEST["saved"])) {
	$tpl_content->addvar("saved", 1);
}

global $ar_packet, $ar_packet_content;

$packetFeatures = new Api_Entities_PacketFeatures();

$id_packet = (int)$_REQUEST["ID_PACKET"];
$ar_packet = array();
$ar_packet_content = array();
if ($id_packet > 0) {
	$id_packet_runtime = $db->fetch_atom("SELECT ID_PACKET_RUNTIME FROM `packet_runtime` WHERE FK_PACKET=".(int)$id_packet);
	$ar_packet = $packets->getFull($id_packet_runtime);
	$ar_packet["B_ABO"] = ($ar_packet["BILLING_CYCLE"] != "ONCE");
	$ar_packet["BILLING_".$ar_packet["BILLING_CYCLE"]] = 1;		// Kleiner Hack als hilfe für die Template-Engine
	
	// Plugin features
	$packetFeatures->setUsergroupId($ar_packet["FK_USERGROUP"]);
	
	$tpl_content->addvars($ar_packet);
	$ar_packet_content = $db->fetch_nar("SELECT FK_PACKET, COUNT FROM `packet_collection` WHERE ID_PACKET=".$id_packet);
	$taxId = $ar_packet["FK_TAX"];
} else {
	$ar_packet["FK_TAX"] = $taxId;
}

// Serialized options
if (array_key_exists("OPTIONS", $ar_packet)) {
	$tpl_content->addvars( array_flatten($ar_packet["OPTIONS"], true, "_", "OPTIONS_") );
}
// Plugin features
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::PACKET_OTHER_FEATURES_ADMIN, $packetFeatures);
$arPluginOptions = array();
foreach ($packetFeatures->getFeaturesAdmin() as $featureIdent => $arFeature) {
	$tplPluginOption = new Template("tpl/de/packet_edit.plugin_opt.htm");
	$tplPluginOption->addvars($arFeature);
	$arPluginOptions[] = $tplPluginOption;
}
$tpl_content->addvar("PLUGIN_FEATURES", $arPluginOptions);

$tax = $db->fetch1("SELECT * FROM `tax` WHERE ID_TAX=".(int)$taxId);
$tpl_content->addvar("TAX_PERCENT", $tax["TAX_VALUE"]);
// Verfügbare Benutzergruppen ausgeben
$ar_usergroups = $db->fetch_table("
		SELECT
			g.*, s.V1, s.V2, s.T1,
			(SELECT count(*) FROM `packet_group` WHERE ID_PACKET=".$id_packet." AND FK_USERGROUP=g.ID_USERGROUP) as ACTIVE
		FROM `usergroup` g
			LEFT JOIN
				`string_usergroup` s ON
					s.FK=g.ID_USERGROUP AND s.S_TABLE='usergroup' AND
					s.BF_LANG=if(g.BF_LANG_USERGROUP & ".$langval.", ".$langval.", 1 << floor(log(g.BF_LANG_USERGROUP+0.5)/log(2)))
		GROUP BY g.ID_USERGROUP
		ORDER BY g.F_ORDER ASC ");
if (!empty($ar_usergroups)) {
	$tpl_content->addlist("liste_groups", $ar_usergroups, "tpl/de/packet_edit.row_group.htm", "setUsergroup");
}
// Vorhandene Laufzeiten ausgeben
if ($id_packet > 0) {
	$ar_runtimes = $db->fetch_table("
		SELECT
			*, (BILLING_PRICE * ".($tax["TAX_VALUE"] / 100 + 1).") as BILLING_PRICE_BRUTTO,
			(BILLING_FACTOR*RUNTIME_FACTOR) AS RUNTIME
		FROM `packet_runtime`
		WHERE FK_PACKET=".$id_packet);
	$tpl_content->addlist("liste_runtimes", $ar_runtimes, "tpl/de/packet_edit.row_runtime.htm", "setPacketRuntime");
}

if (!empty($_POST)) {
	$err = array();
	if (isset($_POST["BILLING_PRICE"])) {
		$_POST["BILLING_PRICE"] = str_replace(",", ".", $_POST["BILLING_PRICE"]);
	}
	// Ajax requests
	if ($_POST["action"] == "runtime_add") {
		if (!isset($_POST["FK_PACKET"])) {
			$err[] = "Ung&uuml;ltiges Paket!";
		}
		if (!$_POST["BILLING_CYCLE"]) {
			// Kein abo...
			$_POST["BILLING_FACTOR"] = 0;
			$_POST["BILLING_CYCLE"] = "ONCE";
			$_POST["BILLING_CANCEL_DAYS"] = 0;
		} else {
			if ((int)$_POST["BILLING_FACTOR"] < 1) {
				$err[] = "Ung&uuml;ltige Laufzeit!";
			}
			if (!isset($_POST["BILLING_CANCEL_DAYS"])) {
				$err[] = "Ung&uuml;ltige K&uuml;ndigungsfrist!";
			}
		}
		if (!isset($_POST["BILLING_PRICE"])) {
			$err[] = "Ung&uuml;ltiger Preis!";
		}
		if (empty($err)) {
			$id_packet_runtime = (int)$_POST["ID_PACKET_RUNTIME"];
			$query = "";
			if ($id_packet_runtime > 0) {
				$query = "UPDATE `packet_runtime` SET FK_PACKET=".(int)$_POST["FK_PACKET"].",
						RUNTIME_FACTOR=".(int)$_POST["RUNTIME_FACTOR"].", BILLING_FACTOR=".(int)$_POST["BILLING_FACTOR"].",
						BILLING_CYCLE='".mysql_escape_string($_POST["BILLING_CYCLE"])."', BILLING_CANCEL_DAYS=".(int)$_POST["BILLING_CANCEL_DAYS"].", BILLING_PRICE=".(double)$_POST["BILLING_PRICE"]."
					WHERE ID_PACKET_RUNTIME=".$id_packet_runtime;
			} else {
				$query = "INSERT INTO `packet_runtime` (FK_PACKET, RUNTIME_FACTOR, BILLING_FACTOR, BILLING_CYCLE, BILLING_CANCEL_DAYS, BILLING_PRICE)
					VALUES (".(int)$_POST["FK_PACKET"].", ".(int)$_POST["RUNTIME_FACTOR"].", ".(int)$_POST["BILLING_FACTOR"].", '".mysql_escape_string($_POST["BILLING_CYCLE"])."',
							".(int)$_POST["BILLING_CANCEL_DAYS"].", ".(double)$_POST["BILLING_PRICE"].")";
			}
			$db->querynow($query);
			header("Content-type: application/json");
			die(json_encode(array("success" => true, "query" => $query)));
		}
	} else if ($_POST["action"] == "runtime_rem") {
		$count_runtimes = $db->fetch_atom("SELECT count(*) FROM `packet_runtime` WHERE FK_PACKET=".(int)$_REQUEST["FK_PACKET"]);
		if ($count_runtimes > 1) {
			$db->querynow("DELETE FROM `packet_runtime` WHERE FK_PACKET=".(int)$_REQUEST["FK_PACKET"]." AND ID_PACKET_RUNTIME=".(int)$_REQUEST["ID_PACKET_RUNTIME"]);
			header("Content-type: application/json");
			die(json_encode(array(
				"success" => true
			)));
		} else {
			header("Content-type: application/json");
			die(json_encode(array("success" => false)));
		}
	} else if ($_POST["action"] == "runtime_get") {
		$ar_runtime = $db->fetch1("SELECT * FROM `packet_runtime` WHERE ID_PACKET_RUNTIME=".(int)$_REQUEST["ID_PACKET_RUNTIME"]);
		if (is_array($ar_runtime)) {
			header("Content-type: application/json");
			$ar_runtime["B_ABO"] = ($ar_runtime["BILLING_CYCLE"] != "ONCE");
			die(json_encode(array(
				"success" => true,
				"runtime" => $ar_runtime
			)));
		} else {
			header("Content-type: application/json");
			die(json_encode(array("success" => false)));
		}
	} else {
		$all_zeros = true;
		// Regular post
		$ar_content = array();
		// Anpassen der Eingaben
		foreach ($_POST["COUNT"] as $fk_packet => $count) {
			//if ($count != 0) {
				$ar_content[$fk_packet] = array(
					"count"		=> (int)$count,
					"params"	=> ""
				);
			//}
			if ( intval($count) != 0 ) {
				$all_zeros = false;
			}
		}
		$_POST["STATUS"] = 0;
		if ($_POST["B_AKTIV"]) {
			$_POST["STATUS"] += 1;
		}
		if (!$_POST["BILLING_CYCLE"]) {
			// Kein abo...
			$_POST["BILLING_CYCLE"] = "ONCE";
		} else {
			if ((int)$_POST["BILLING_FACTOR"] < 1) {
				$err[] = "Ung&uuml;ltige Laufzeit!";
			}
		}
		// Fehlerüberprüfung
		if (!isset($_POST["ID_PACKET"])) {
			if ($_POST["B_ABO"] == 1) {
				// Laufzeiten
				if (!isset($_POST["BILLING_FACTOR"])) {
					$err[] = "Ung&uuml;ltige Laufzeit!";
				}
				if (!isset($_POST["BILLING_CYCLE"])) {
					$err[] = "Ung&uuml;ltige Laufzeit!";
				}
				if (!isset($_POST["BILLING_CANCEL_DAYS"])) {
					$err[] = "Ung&uuml;ltige K&uuml;ndigungsfrist!";
				}
			} else {
				$_POST["BILLING_CYCLE"] = "ONCE";
				$_POST["BILLING_FACTOR"] = 0;
				$_POST["BILLING_CANCEL_DAYS"] = 0;
			}
			if (!isset($_POST["BILLING_PRICE"])) {
				$err[] = "Ung&uuml;ltiger Preis!";
			}
		}
		if ($all_zeros && empty($_POST["OPTIONS"])) {//if (empty($ar_content) && empty($_POST["OPTIONS"])) {
			$err[] = "Das Paket muss etwas beinhalten!";
			unset($_POST["COUNT"]);
		}

		if (empty($err) && ($id_packet = $packets->update($_POST))) {
			$price = str_replace(",", ".", $_POST["BILLING_PRICE"]);
			if ($_POST["ID_PACKET"] > 0) {
				$id_packet = (int)$_POST["ID_PACKET"];
				if (isset($_POST["BILLING_PRICE"])) {
					$db->querynow($query = "UPDATE `packet_runtime` SET BILLING_PRICE=".(double)$price." WHERE FK_PACKET=".$id_packet);
				}
			} else {
				$db->querynow("INSERT INTO `packet_runtime` (FK_PACKET, RUNTIME_FACTOR, BILLING_FACTOR, BILLING_CYCLE, BILLING_CANCEL_DAYS, BILLING_PRICE)
					VALUES (".(int)$id_packet.", ".(int)$_POST["RUNTIME_FACTOR"].", ".(int)$_POST["BILLING_FACTOR"].",
							'".$_POST["BILLING_CYCLE"]."', ".(int)$_POST["BILLING_CANCEL_DAYS"].", ".(float)$price.")");
			}
			// Gespeichert!?
			if ($id_packet > 0) {
				// Benutzergruppen
				$db->querynow("DELETE FROM `packet_group` WHERE ID_PACKET=".$id_packet." AND FK_USERGROUP NOT IN (".implode(", ", array_keys($_POST["USERGROUP"])).")");
				foreach ($_POST["USERGROUP"] as $fk_usergroup => $count) {
					$db->querynow("INSERT INTO `packet_group` (ID_PACKET, FK_USERGROUP) VALUES (".$id_packet.", ".(int)$fk_usergroup.")");
				}
				// Enthaltene Elemente
				$db->querynow("DELETE FROM `packet_collection` WHERE ID_PACKET=".$id_packet." AND FK_PACKET NOT IN (".implode(", ", array_keys($ar_content)).")");
				foreach ($ar_content as $fk_packet => $ar_current) {
					$db->querynow("INSERT INTO `packet_collection` (ID_PACKET, FK_PACKET, COUNT, PARAMS)
									VALUES (".$id_packet.", ".(int)$fk_packet.", ".$ar_current["count"].", '".mysql_escape_string($ar_current["params"])."') ON DUPLICATE KEY
									UPDATE COUNT=".$ar_current["count"].", PARAMS='".mysql_escape_string($ar_current["params"])."'");
				}
				// Fertig
				die(forward("index.php?page=packet_edit&ID_PACKET=".$id_packet."&saved=1"));
			} else {
				$err[] = "Datenbankfehler beim Speichern!";
			}
		}
	}
	// Fehler ausgeben
	$err = array_merge($err, $packets->getErrors());
	$tpl_content->addvar("errors", implode("<br />", $err));
	$tpl_content->addvars($_POST);
}
// Verfügbare bestandteile ausgeben
$all = 0;
$ar_packets = $packets->getList(1, 50, $all, array("(TYPE='BASE' OR TYPE='BASE_ABO')", "(STATUS&1)=1"), array("TYPE ASC", "V1 ASC"));
if (!empty($ar_packets)) {
	$tpl_content->addlist("liste", $ar_packets, "tpl/de/packet_edit.row.htm", "setPacketType");
}

?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);

$taxId = $nar_systemsettings["MARKTPLATZ"]["TAX_DEFAULT"];

$ar_type = array('BASE','BASE_ABO');	// Erlaubte typen
/*
$ar_recurring = array('ONCE','WEEK','MONTH','QUATER_YEAR','HALF_YEAR','YEAR');	// Erlaubte intervalle
	if (!in_array($ar_recurring, $_POST["RECURRING"])) {
		$err[] = "Keine oder ungültige Laufzeit angegeben!";
	}
*/

if (isset($_REQUEST["saved"])) {
	$tpl_content->addvar("saved", 1);
}

$id_packet = (int)$_REQUEST["ID_PACKET"];
$ar_packet = array();
$ar_packet_groups = array();
if ($id_packet > 0) {
	$ar_packet = $packets->get($id_packet);
	$ar_packet["B_AKTIV"] = (($ar_packet["STATUS"] & 1) == 1 ? 1 : 0);
	$ar_packet["TYPE_".$ar_packet["TYPE"]] = 1;		// Kleiner Hack als hilfe für die Template-Engine
	$taxId = $ar_packet["FK_TAX"];
	$tpl_content->addvars($ar_packet);

	$ar_packet_groups = $db->fetch_table("SELECT
	  		g.ID_USERGROUP, s.V1, p.PRICE
	  	FROM `usergroup` g
	  	LEFT JOIN `string_usergroup` s ON
	        s.FK=g.ID_USERGROUP AND s.S_TABLE='usergroup' AND
	        s.BF_LANG=if(g.BF_LANG_USERGROUP & ".$langval.", ".$langval.", 1 << floor(log(g.BF_LANG_USERGROUP+0.5)/log(2)))
		LEFT JOIN `packet_price` p ON
			p.FK_USERGROUP=g.ID_USERGROUP AND p.FK_PACKET=".$id_packet."
		ORDER BY g.F_ORDER ASC
		");
} else {
	$ar_packet["FK_TAX"] = $taxId;
	$ar_packet_groups = $db->fetch_table("SELECT
	  		g.ID_USERGROUP, s.V1, 0 AS PRICE
	  	FROM `usergroup` g
	  	LEFT JOIN `string_usergroup` s ON
	        s.FK=g.ID_USERGROUP AND s.S_TABLE='usergroup' AND
	        s.BF_LANG=if(g.BF_LANG_USERGROUP & ".$langval.", ".$langval.", 1 << floor(log(g.BF_LANG_USERGROUP+0.5)/log(2)))
	    ORDER BY g.F_ORDER ASC ");
}

if (in_array($id_packet, array(1,2,3,4,5,6,7,8,9,10,11,12))) {
    $tpl_content->addvar("SIMPLE", 1);
}

$tax = $db->fetch1("SELECT * FROM `tax` WHERE ID_TAX=".(int)$taxId);
$tpl_content->addvar("TAX_PERCENT", $tax["TAX_VALUE"]);
$tpl_content->addlist("liste", $ar_packet_groups, "tpl/de/packet_base_add.row.htm");

if (!empty($_POST)) {
	$err = array();
	if (empty($_POST["V1"])) {
		$err[] = "Keine Beschreibung angegeben!";
	}
    if (isset($_POST["SIMPLE"])) {
        if (empty($err)) {
            $ar_packet["V1"] = $_POST["V1"];
            $ar_packet["V2"] = $_POST["V2"];
            $db->update("packet", $ar_packet);
            die(forward("index.php?page=packet_base_add&ID_PACKET=".$id_packet."&saved=1"));
        } else {
            $tpl_content->addvar("errors", implode("<br />", $err));
        }
    } else {
        if (!in_array($_POST["TYPE"], $ar_type)) {
            $err[] = "Keinen oder ungültigen Typen angegeben! (Einmalig/Abo)";
        }
        if (!is_array($_POST["PRICE"])) {
            $err[] = "Keinen oder ungültigen Preis angegeben!";
        }
        if (empty($err)) {
            if (isset($_POST["B_AKTIV"])) {
                $_POST["STATUS"] = $ar_packet["STATUS"] | 1;
            } else {
                $_POST["STATUS"] -= $ar_packet["STATUS"] & 1;
            }
            if (!isset($_POST["ID_PACKET"])) {
                $id_packet = $db->update("packet", $_POST);
            } else {
                $db->update("packet", $_POST);
            }
            // Delete old groups
            $groups = mysql_escape_string(implode(", ", array_keys($_POST["PRICE"])));
            $db->querynow("DELETE FROM `packet_price` WHERE FK_PACKET=".$id_packet." AND FK_USERGROUP NOT IN (".$groups.")");
            // Update submitted groups
            foreach ($_POST["PRICE"] as $id_usergroup => $price) {
                $price = str_replace(",", ".", $price);
                $db->querynow("INSERT INTO `packet_price` (FK_PACKET, FK_USERGROUP, PRICE)
				VALUES (".$id_packet.", ".(int)$id_usergroup.", ".(float)$price.")
			ON DUPLICATE KEY UPDATE PRICE=".(float)$price);
            }
            die(forward("index.php?page=packet_base_add&ID_PACKET=".$id_packet."&saved=1"));
        } else {
            $tpl_content->addvar("errors", implode("<br />", $err));
        }
    }
	$tpl_content->addvars($_POST);
}

?>
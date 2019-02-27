<?php
/* ###VERSIONSBLOCKINLCUDE### */



if (isset($_POST['PSATZ']) && isset($_POST['PRICE'])) {
	// Provisionen
	$id = $_REQUEST["id"] = $_REQUEST["ID_USERGROUP"];
    $err = false;
	$id_prov = $db->fetch_atom("SELECT ID_PROVSATZ FROM provsatz WHERE
		(PSATZ=".mysql_real_escape_string($_POST['PSATZ'])." OR PRICE='".mysql_real_escape_string($_POST['PRICE'])."')
		AND FK_USERGROUP=".$id);
	if($id_prov > 0) {
		$err = true;
	}
	if(!$err) {
		$ar_provsatz = array(
			"PSATZ"			=> $_POST["PSATZ"],
			"PRICE"			=> (int)$_POST["PRICE"],
			"FK_USERGROUP"	=> $id
		);
		$db->update("provsatz", $ar_provsatz);
    	die(forward("index.php?page=usergroups_edit&id=".$id."&ok=1"));
	} else {
		$tpl_content->addvar('err', 1);
		$tpl_content->addvars($_POST);
	}
} else if (!empty($_POST)) {
    /* -------------------- */
    /* Formular abgeschickt */
    /* -------------------- */

	$err = array();

    // Checkboxen interpretieren
    $_POST['PREPAID']          		= ($_POST['PREPAID'] ? 1 : 0);
    $_POST['PROV_PREPAID']        = ($_POST['PROV_PREPAID'] ? 1 : 0);
    $_POST['UNLOCK_MANUAL']    		= ($_POST['UNLOCK_MANUAL'] ? 1 : 0);
    $_POST['AUTO_CREATE_VENDOR']    = ($_POST['AUTO_CREATE_VENDOR'] ? 1 : 0);
    $_POST['IS_AVAILABLE']          = ($_POST['IS_AVAILABLE'] > 0 ? (int)$_POST['IS_AVAILABLE'] : 0);

    if (empty($_POST['FK_PACKET_RUNTIME_DEFAULT'])) {
        $_POST['FK_PACKET_RUNTIME_DEFAULT'] = null;
    }

	$existing_defaults = $db->fetch_atom("SELECT count(*) FROM `usergroup` WHERE IS_DEFAULT=1");
	if ($_POST['IS_DEFAULT'] || ($existing_defaults == 0)) {
		if ($existing_defaults > 0) {
			$db->querynow("UPDATE `usergroup` SET IS_DEFAULT=0");
		}
		$_POST['IS_DEFAULT'] = 1;
	}

	// BF_CONSTRAINTS
	$bf_constraints = $_POST["BF_CONSTRAINTS"];
	$_POST["BF_CONSTRAINTS"] = 0;
	if (!empty($bf_constraints)) {
		foreach ($bf_constraints as $key => $value) {
			$_POST["BF_CONSTRAINTS"] = $_POST["BF_CONSTRAINTS"] + $value;
		}
	}
    // Sprache hinzufügen
    $_POST['BF_LANG']          = $langval;

    if (empty($_POST['V1'])) {
    	$err[] = "Sie müssen einen Namen für die Benutzergruppe angeben!";
    }

    if (!empty($_POST["OPTIONS"])) {
        $_POST["SER_OPTIONS"] = serialize($_POST["OPTIONS"]);
    }

    if (empty($err)) {
	    $id = $db->update('usergroup', $_POST);
	    // Erfolgreich eingetragen?
	    if ($id > 0) {
	      // Bild hochgeladen?
	      if (isset($_FILES["UPLOAD_IMAGE"])) {
	        // Bild upload
	        $uploads_dir = $ab_path.'cache/usergroups';
	        @mkdir($uploads_dir);
	          chmod($uploads_dir, 0777);
	        if ($_FILES["UPLOAD_IMAGE"]["error"] == UPLOAD_ERR_OK) {
	          $tmp_name = $_FILES["UPLOAD_IMAGE"]["tmp_name"];
	          $name = $id.".png";

	          if (file_exists($uploads_dir."/".$name))
	            unlink($uploads_dir."/".$name);

	         $d = move_uploaded_file($tmp_name, $uploads_dir."/".$name);

	          chmod($uploads_dir."/".$name, 0777);
	        }
	      }

	      // Rollen
	      $a = $db->querynow("DELETE FROM usergroup_role WHERE FK_USERGROUP = '".mysql_real_escape_string($_POST['ID_USERGROUP'])."' ");

	      if(is_array($_POST['ROLES'])) {
	          foreach($_POST['ROLES'] as $key => $role) {
	              $db->update("usergroup_role", array(
	                 'FK_USERGROUP' => $_POST['ID_USERGROUP'],
	                 'FK_ROLE' => $key
	              ));

	          }
	      }

            // Paketbestandteile
            require_once $ab_path."sys/packet_management.php";
            $ar_components = PacketManagement::getComponentTypes();
            $ar_insert = array();
            foreach ($ar_components as $packetId) {
                $ar_insert[] = "(".$packetId.", ".$id.", -1)";
            }
            $query = "INSERT IGNORE INTO `packet_price` (FK_PACKET, FK_USERGROUP, PRICE) VALUES".
                implode(",\n", $ar_insert);
            $db->querynow($query);

            // Zurück zur Übersicht
	      die(forward("index.php?page=usergroups_edit&id=".$id."&ok=1"));
	    } else {
	      // Fehler!
	      die(forward("index.php?page=usergroups_edit&id=".$id."&ok=1"));
	    }
    } else {
    	$tpl_content->addvar("errors", " - ".implode("<br />\n - ", $err));
    }
}

/* -------------------- */
/*   Normaler aufruf    */
/* -------------------- */

$tax = $db->fetch1("SELECT * FROM `tax` WHERE ID_TAX=".$nar_systemsettings["MARKTPLATZ"]["TAX_DEFAULT"]);
$tpl_content->addvar("TAX_PERCENT", $tax["TAX_VALUE"]);

if ($_REQUEST['del']) {
	$db->delete('provsatz', (int)$_REQUEST['del']);
}
if($_REQUEST['ok']) {
	$tpl_content->addvar("ok", 1);
}

if ($_REQUEST["id"] > 0) {
    $group = $db->fetch1("SELECT
      		g.*, s.V1, s.V2, s.T1
      	FROM `usergroup` g
      		LEFT JOIN `string_usergroup` s ON
            s.FK=g.ID_USERGROUP AND s.S_TABLE='usergroup' AND
            s.BF_LANG=if(g.BF_LANG_USERGROUP & ".$langval.", ".$langval.", 1 << floor(log(g.BF_LANG_USERGROUP+0.5)/log(2)))
          WHERE g.ID_USERGROUP=".(int)$_REQUEST["id"]);
    $groupOptions = @unserialize($group["SER_OPTIONS"]);
    if ($groupOptions !== false) {
        $groupOptions = array_merge(array_flatten($groupOptions), array_flatten($groupOptions, true));
        $tpl_content->addvars($groupOptions, "OPTIONS_");
    }
    $tpl_content->addvars($group);
    $tpl_content->addvar("editing", 1);

	$liste = $db->fetch_table("
		SELECT
			*
		FROM
			provsatz
		WHERE
			FK_USERGROUP=".(int)$_REQUEST["id"]."
		ORDER BY
			PRICE ASC
	");
	$tpl_content->addlist("provs", $liste, "tpl/de/usergroups_edit.row_prov.htm");

	$ar_no = array();
	for($i=0; $i<count($liste); $i++) {
		$ar_no[] = $liste[$i]['PSATZ'];
	}

	$tpl_content->addvar("use_prov", $nar_systemsettings['MARKTPLATZ']['USE_PROV']);
	$ar_satz = array(
		'<option value="0">0%</option>',
	);
	for($i=1; $i<101; $i++) {
		$ar_satz[] = '<option style="texta-align:right;" value="'.$i.'" '.($i==$_POST['PSATZ'] ? ' selected' : '').'>'.$i.' %</option>';
	}
	$tpl_content->addvar("saetze", implode("\n", $ar_satz));

    // Rollen

    $ar_roles = $db->fetch_table("
        SELECT
            r.*,
            (SELECT COUNT(*) FROM usergroup_role WHERE FK_ROLE = r.ID_ROLE AND FK_USERGROUP = '".mysql_real_escape_string($_REQUEST["id"])."') AS ROLE_SET
        FROM role r
        ORDER BY LABEL
    ");
    $tpl_content->addlist('ROLES', $ar_roles, 'tpl/de/usergroups_edit.roles.htm');

}

// Mitgliedschaften
require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);
$listMemberships = $packets->getList(1, 50, $all, array("(TYPE='MEMBERSHIP')", "(STATUS&1)=1"), array("F_ORDER ASC", "TYPE ASC", "V1 ASC"));
foreach ($listMemberships as $index => $arMembership) {
    $listMemberships[$index]["listRuntimes"] = array();
    foreach ($arMembership["RUNTIMES"] as $runtimeIndex => $arRuntime) {
        // Nur kostenlose darstellen?
        //if ($arRuntime["BILLING_PRICE"] > 0) continue;
        // Template für option
        $tplRuntime = new Template("tpl/".$s_lang."/usergroups_edit.option_row.htm");
        $tplRuntime->addvars($arMembership);
        $tplRuntime->addvars($arRuntime);
        $tplRuntime->addvar("CYCLE_".$arRuntime["BILLING_CYCLE"], 1);
        $listMemberships[$index]["listRuntimes"][] = $tplRuntime;
    }
}
$tpl_content->addlist("list_memberships", $listMemberships, "tpl/".$s_lang."/usergroups_edit.optgroup_row.htm");

?>

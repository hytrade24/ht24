<?php
/* ###VERSIONSBLOCKINLCUDE### */
require_once $ab_path.'sys/lib.advertisement.php';
require_once $ab_path.'sys/lib.user.php';

$advertisementManagement = AdvertisementManagement::getInstance($db);
$userManagement = UserManagement::getInstance($db);

global $id_ad_user;

if(isset($_POST['id_ad_user']) && $_POST['id_ad_user'] != "") { $id_ad_user = $_POST['id_ad_user']; }

/**
 * Read the current check state of a category
 * 
 * @param $row
 * @param $i
 */
function read_state(&$row, $i) {
	global $db, $uid, $id_ad_user;
	//die(var_dump($row));
	$id_checked = $db->fetch_atom("
		SELECT
			ID_ADVERTISEMENT_KAT
		FROM
			`advertisement_kat`
		WHERE
			FK_ADVERTISEMENT_USER=".(int)$id_ad_user." AND
			FK_KAT=".(int)$row["ID_KAT"]."
	");
	if ($id_checked > 0) {
		
		$row["CHECKED"] = 1;
	} else {
		$row["CHECKED"] = 0;
	}
}


$userdata = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=".$uid);
if (empty($userdata["VORNAME"]) || empty($userdata["NACHNAME"]) ||
      empty($userdata["STRASSE"]) || empty($userdata["PLZ"]) || empty($userdata["ORT"])) {
    $tpl_content->addvar("error_noaddress", 1);
    if (empty($userdata["VORNAME"])) $tpl_content->addvar("error_addr_first", 1);
    if (empty($userdata["NACHNAME"])) $tpl_content->addvar("error_addr_last", 1);
    if (empty($userdata["STRASSE"])) $tpl_content->addvar("error_addr_street", 1);
    if (empty($userdata["PLZ"])) $tpl_content->addvar("error_addr_zip", 1);
    if (empty($userdata["ORT"])) $tpl_content->addvar("error_addr_city", 1);
    return;
}

$id_kat_root = 1;
$ar_kat_root = array();

#$SILENCE = false;

if (empty($_POST) && ($_REQUEST["frame"] != "ajax") && empty($_REQUEST["action"])) {
	$id_ad_user = $db->fetch_atom("SELECT ID_ADVERTISEMENT_USER FROM `advertisement_user` WHERE FK_USER=".$uid." AND DONE=0");
	if ($id_ad_user > 0) {
		$db->querynow("DELETE FROM `advertisement_kat` WHERE FK_ADVERTISEMENT_USER=".$id_ad_user);
		$db->querynow("DELETE FROM `advertisement_user` WHERE ID_ADVERTISEMENT_USER=".$id_ad_user);
		die(forward("advertisement.htm"));
	} else {
		$id_ad_user = null;
	}
}

$action = ($_REQUEST['action'] ? $_REQUEST['action'] : "read");
$target = ($_REQUEST['target'] ? $_REQUEST['target'] : $id_kat_root);

include_once "sys/lib.shop_kategorien.php";
$kat = new TreeCategories("kat", $id_kat_root);

$num_ads = $db->fetch_atom("
	SELECT
		count(*)
	FROM
		`advertisement_user`
	WHERE
		FK_USER=".$uid." AND DONE=1 AND STAMP_END>CURDATE()");
$tpl_content->addvar("NUM_ADS", $num_ads);

if($id_ad_user == null) {
	$ar_ad_user = $db->fetch1("
	SELECT
		*,
		(SELECT count(*) FROM `advertisement_kat` WHERE FK_ADVERTISEMENT_USER=ID_ADVERTISEMENT_USER) AS CATEGORYS
	FROM
		`advertisement_user`
	WHERE
		ENABLED=0 AND FK_USER=".$uid." AND DONE=0");
} else {
	$ar_ad_user = $db->fetch1("
	SELECT
		*,
		(SELECT count(*) FROM `advertisement_kat` WHERE FK_ADVERTISEMENT_USER=ID_ADVERTISEMENT_USER) AS CATEGORYS
	FROM
		`advertisement_user`
	WHERE
		ID_ADVERTISEMENT_USER = ".mysql_escape_string($id_ad_user)." AND FK_USER=".$uid."");
}

$ar_ad_user['CATEGORYS'] = ($ar_ad_user['CATEGORYS'] > 0 ? $ar_ad_user['CATEGORYS'] : 0);
if (empty($ar_ad_user["ID_ADVERTISEMENT_USER"])) {
	$ar_ad_user = array(
		"FK_USER"	=> $uid,
		"ENABLED"	=> 0,
		"DONE"		=> 0
	);
	$ar_ad_user["ID_ADVERTISEMENT_USER"] = $db->update("advertisement_user", $ar_ad_user);
}
$id_ad_user = $ar_ad_user["ID_ADVERTISEMENT_USER"];
$tpl_content->addvars($ar_ad_user);

if ($_REQUEST['frame'] == "ajax") {
	$tpl_content->addvar("ajax", 1);
}

if (!empty($_POST["book"])) {
	$id_ad = (int)$ar_ad_user["FK_ADVERTISEMENT"];
	$num_kats = (int)$db->fetch_atom("SELECT count(*) FROM `advertisement_kat` WHERE FK_ADVERTISEMENT_USER=".$id_ad_user);
	$date_from = strtotime($ar_ad_user["STAMP_START"]);
	$date_to = strtotime($ar_ad_user["STAMP_END"]);

	if (($date_from < $date_to) && ($num_kats > 0) && ($id_ad > 0)) {

		// Update price
		$price = $db->fetch_atom("
			SELECT
				SUM(PRICE)
			FROM
				`advertisement_kat`
			WHERE
				FK_ADVERTISEMENT_USER=".$id_ad_user);

		// Einstellungen okay! 
		$db->querynow("
			UPDATE
				`advertisement_user`
			SET
				PRICE=".(float)$price.",
				DONE=1
			WHERE
				ID_ADVERTISEMENT_USER=".$id_ad_user);
        die(forward($tpl_content->tpl_uri_action("advertisement,done")));
	}
	$tpl_content->addvar("done", 1);
} else if ($ar_params[1] == "done") {
	$tpl_content->addvar("done", 1);
} else {
	if ($action == "save_banner_img") {
		include $ab_path."tpl/advertisement_upload.php";
	}
	if ($action == "read") {
		// Get target category id (or use root if none given)
		$id_kat = (int)$target;
		// Read child nodes of target categoy
		if ($ar_kat_root = $kat->element_get_childs($id_kat)) {
			foreach ($ar_kat_root as $index => $ar_kat) {
				// Check for childs
				$ar_kat_root[$index]["HAS_CHILDS"] = $kat->element_has_childs($ar_kat["ID_KAT"]);
				$ar_kat_root[$index]["ID_AD_USER"] = $id_ad_user;
			}
			// Output category list
			$tpl_content->addlist("liste_kat", $ar_kat_root, "tpl/de/advertisement.row_kat.htm", read_state);
		}
		if ($target == $id_kat_root) {
			// Regular call (no ajax)
			$ar_advertisements = $db->fetch_table("
				SELECT
					a.*,
					s.*
				FROM
					`advertisement` a
				LEFT JOIN
					`string_advertisement` s ON
					s.S_TABLE='advertisement' AND s.FK=a.ID_ADVERTISEMENT AND
					s.BF_LANG=if(a.BF_LANG_ADVERTISEMENT & ".$langval.", ".$langval.", 1 << floor(log(a.BF_LANG_ADVERTISEMENT+0.5)/log(2)))
				");
			$tpl_content->addlist("liste_pos", $ar_advertisements, "tpl/de/advertisement.row_pos.htm");
		}
	}
	
	/**
	 * Set banner format
	 */
	if ($action == "save") {
		$id_ad = (int)$_POST["FK_ADVERTISEMENT"];
		$code = $_POST["CODE"];
		$date_from = "";
		if (preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})/", $_POST["STAMP_START"], $matches)) {
			$date_from = $matches[3]."-".$matches[2]."-".$matches[1];
		}
		$date_to = "";
		if (preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})/", $_POST["STAMP_END"], $matches)) {
			$date_to = $matches[3]."-".$matches[2]."-".$matches[1];
		}

		$res = $db->querynow("
			UPDATE
				`advertisement_user`
			SET
				CODE='".mysql_escape_string($code)."',
				FK_ADVERTISEMENT=".(int)$id_ad.",
				STAMP_START='".mysql_escape_string($date_from)."',
				STAMP_END='".mysql_escape_string($date_to)."',
				DONE=1
			WHERE
				ID_ADVERTISEMENT_USER=".$id_ad_user);
		if ($res["rsrc"]) {
			// Get prices of the used advertisment


			// Update price
			$price = $db->fetch_atom("
				SELECT
					SUM(PRICE)
				FROM
					`advertisement_kat`
				WHERE
					FK_ADVERTISEMENT_USER=".$id_ad_user);
			// Einstellungen okay! 
			$db->querynow("
				UPDATE
					`advertisement_user`
				SET
					PRICE=".(float)$price.",
					DONE=1
				WHERE
					ID_ADVERTISEMENT_USER=".$id_ad_user);
			// Return result
            die(forward($tpl_content->tpl_uri_action("advertisement,done")));
		}	
	}
	
	/**
	 * Set banner code
	 */
	if ($action == "banner_set") {
		if (!empty($target)) {
			$res = $db->querynow("
				UPDATE
					`advertisement_user`
				SET
					FK_ADVERTISEMENT='".mysql_escape_string($target)."'
				WHERE
					ID_ADVERTISEMENT_USER=".$id_ad_user);
			// Return result as json object
			header('Content-type: application/json');
			if ($res["rsrc"]) {
				// Get prices of the used advertisment
				$costs = $db->fetch_atom("
					SELECT
						COSTS
					FROM
						`advertisement`
					WHERE
						ID_ADVERTISEMENT=".(int)$target);
				$ar_costs = explode("|", $costs);
				// Update prices
				$db->querynow("
					UPDATE 
						`advertisement_kat`
					SET
						PRICE=".(float)$ar_costs[0]."
					WHERE
						FK_ADVERTISEMENT_USER=".$id_ad_user);
				for ($level = 1; $level < count($ar_costs); $level++) {
					$db->querynow("
						UPDATE 
							`advertisement_kat`
						SET
							PRICE=".(float)$ar_costs[$level]."
						WHERE
							FK_ADVERTISEMENT_USER=".$id_ad_user." AND
							LEVEL=".(int)$level);
				}
				// Return result
				die(json_encode(array(
					"success"	=> 1
				)));	
			} else {
				// Return result
				die(json_encode(array(
					"success"	=> 0
				)));	
			}		
		}
	}
	
	/**
	 * Set banner code
	 */
	if ($action == "code_set") {
		if (!empty($target)) {
			$res = $db->querynow("
				UPDATE
					`advertisement_user`
				SET
					CODE='".mysql_escape_string($target)."'
				WHERE
					ID_ADVERTISEMENT_USER=".$id_ad_user);
			// Return result as json object
			header('Content-type: application/json');
			if ($res["rsrc"]) {
				// Return result
				die(json_encode(array(
					"success"	=> 1
				)));	
			} else {
				die(json_encode(array(
					"success"	=> 0
				)));	
			}		
		}
	}
	
	/**
	 * Set start date
	 */
	if ($action == "date_from_set") {
		if (preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})/", $target, $matches)) {
			$date_db = $matches[3]."-".$matches[2]."-".$matches[1];
			$res = $db->querynow("
				UPDATE
					`advertisement_user`
				SET
					STAMP_START='".mysql_escape_string($date_db)."'
				WHERE
					ID_ADVERTISEMENT_USER=".$id_ad_user);
			// Check for valid date range
			$query = "SELECT 
				if(STAMP_START IS NULL OR STAMP_END IS NULL,0,1) as FILLED,
				if(DATEDIFF(STAMP_END, STAMP_START) < 0 ,0,1) as POSITIVE
			FROM `advertisement_user`
			WHERE ID_ADVERTISEMENT_USER=".$id_ad_user;
			$ar_check = $db->fetch1($query);
			$date_check = (($ar_check["FILLED"] + $ar_check["POSITIVE"]) == 2 ? 1 : 0);
			// Return result as json object
			header('Content-type: application/json');
			if ($res["rsrc"]) {
				// Return result
				die(json_encode(array(
					"success"	=> 1,
					"date_okay"	=> $date_check
				)));	
			} else {
				die(json_encode(array(
					"success"	=> 0,
					"date_okay"	=> $date_check
				)));	
			}	
		}
	}
	
	/**
	 * Set end date
	 */
	if ($action == "date_to_set") {
		if (preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})/", $target, $matches)) {
			$date_db = $matches[3]."-".$matches[2]."-".$matches[1];
			$res = $db->querynow("
				UPDATE
					`advertisement_user`
				SET
					STAMP_END='".mysql_escape_string($date_db)."'
				WHERE
					ID_ADVERTISEMENT_USER=".$id_ad_user);
			// Check for valid date range
			$query = "SELECT 
				if(STAMP_START IS NULL OR STAMP_END IS NULL,0,1) as FILLED,
				if(DATEDIFF(STAMP_END, STAMP_START) < 0 ,0,1) as POSITIVE
			FROM `advertisement_user`
			WHERE ID_ADVERTISEMENT_USER=".$id_ad_user;
			$ar_check = $db->fetch1($query);
			$date_check = (($ar_check["FILLED"] + $ar_check["POSITIVE"]) == 2 ? 1 : 0);
			// Return result as json object
			header('Content-type: application/json');
			if ($res["rsrc"]) {
				// Return result
				die(json_encode(array(
					"success"	=> 1,
					"date_okay"	=> $date_check
				)));	
			} else {
				die(json_encode(array(
					"success"	=> 0,
					"date_okay"	=> $date_check
				)));	
			}	
		}
	}
	
	/**
	 * Add single category
	 */
	if ($action == "kat_add") {
		if ($target != $id_kat_root) {
			$id_kat = (int)$target;
			// Obtain category level
			$level = $db->fetch_atom("
				SELECT
					(SELECT count(*) FROM `kat` k2 WHERE k2.LFT<k1.LFT AND k2.RGT>k1.RGT AND k2.ROOT=k1.ROOT) as LEVEL
				FROM
					`kat` k1
				WHERE
					k1.ID_KAT=".$id_kat);
			// Get prices of the used advertisment

            $price = $advertisementManagement->getPriceByCategory($id_kat, $ar_ad_user["FK_ADVERTISEMENT"], $user['FK_USERGROUP']);

			// Add category to selection
			$res = $db->querynow("
				INSERT INTO `advertisement_kat`
					(`FK_ADVERTISEMENT_USER`, `FK_KAT`, `LEVEL`, `PRICE`)
				VALUES
					(".$id_ad_user.", ".$id_kat.", ".$level.", '".$price."')");
			// Return result as json object
			header('Content-type: application/json');
			die(json_encode(array(
				"success" => $res["rsrc"]
			)));
		} else {
			// Return result as json object
			header('Content-type: application/json');
			die(json_encode(array(
				"success" => 0
			)));
		}
	}
	
	/**
	 * Recursive add categorys
	 */
	if ($action == "kat_add_recursive") {
		if ($target != $id_kat_root) {
			$id_kat = (int)$target;
			// Get all related categorys
			$ar_kats = $db->fetch_nar("
				SELECT
					k1.ID_KAT,
					(SELECT count(*) FROM `kat` k2 WHERE k2.LFT<k1.LFT AND k2.RGT>k1.RGT AND k2.ROOT=k1.ROOT) as LEVEL
				FROM
					`kat` k1,
					`kat` k3
				WHERE
					k3.ID_KAT=".$id_kat." AND
					k1.LFT>=k3.LFT AND k1.RGT<=k3.RGT AND k1.ROOT=k3.ROOT");

			// Get prices of the used advertisment
			$ar_checked_kats = array();
			// Add categorys to selection
			foreach ($ar_kats as $id_kat => $level) {
                $price = $advertisementManagement->getPriceByCategory($id_kat, $ar_ad_user["FK_ADVERTISEMENT"], $user['FK_USERGROUP']);

				$res = $db->querynow("
					INSERT INTO `advertisement_kat`
						(`FK_ADVERTISEMENT_USER`, `FK_KAT`, `LEVEL`, `PRICE`)
					VALUES
						(".$id_ad_user.", ".$id_kat.", ".$level.", '".$price."')");

				if ($res["rsrc"] > 0) {
					$ar_checked_kats[] = $id_kat; 
				}
			}
			// Return result as json object
			header('Content-type: application/json');
			die(json_encode(array(
				"success" => 1,
				"checked" => $ar_checked_kats
			)));
		} else {
			// Return result as json object
			header('Content-type: application/json');
			die(json_encode(array(
				"success" => 0
			)));
		}
	}
	
	/**
	 * Remove single category
	 */
	if ($action == "kat_rem") {
		if ($target != $id_kat_root) {
			$id_kat = (int)$target;
			// Remove category from selection
			$res = $db->querynow("
				DELETE FROM
					`advertisement_kat`
				WHERE
					FK_ADVERTISEMENT_USER=".$id_ad_user." AND FK_KAT=".$id_kat);
			// Return result as json object
			header('Content-type: application/json');
			die(json_encode(array(
				"success" => $res["rsrc"]
			)));
		} else {
			// Return result as json object
			header('Content-type: application/json');
			die(json_encode(array(
				"success" => 0
			)));
		}
	}
	
	/**
	 * Recursive remove categorys
	 */
	if ($action == "kat_rem_recursive") {
		if ($target != $id_kat_root) {
			$id_kat = (int)$target;
			// Get all related categorys
			$ar_kats = $db->fetch_nar("
				SELECT
					k1.ID_KAT,
					(SELECT count(*) FROM `kat` k2 WHERE k2.LFT<k1.LFT AND k2.RGT>k1.RGT AND k2.ROOT=k1.ROOT) as LEVEL
				FROM
					`kat` k1,
					`kat` k3
				WHERE
					k3.ID_KAT=".$id_kat." AND
					k1.LFT>=k3.LFT AND k1.RGT<=k3.RGT AND k1.ROOT=k3.ROOT");
			$ar_checked_kats = array_keys($ar_kats);
			// Remove categorys from selection

			$res = $db->querynow("
				DELETE FROM
					`advertisement_kat`
				WHERE
					FK_ADVERTISEMENT_USER=".$id_ad_user." AND
					FK_KAT IN (".implode(",", $ar_checked_kats).")");
			// Return result as json object
			header('Content-type: application/json');
			die(json_encode(array(
				"success" => $res["rsrc"],
				"checked" => $ar_checked_kats
			)));
		} else {
			// Return result as json object
			header('Content-type: application/json');
			die(json_encode(array(
				"success" => 0
			)));
		}
	}
	
	if ($action == "update") {
		// Update price and count of selected categorys
		$price = 0;
		$ar_selected = $db->fetch_nar("
			SELECT
				FK_KAT,
				LEVEL
			FROM
				`advertisement_kat`
			WHERE
				FK_ADVERTISEMENT_USER=".$id_ad_user);
		$ar_ids = array_keys($ar_selected);
		if (!empty($ar_selected)) {
			// Get category levels for pricing
			$price = $db->fetch_atom("
				SELECT
					SUM(PRICE)
				FROM
					`advertisement_kat`
				WHERE
					FK_ADVERTISEMENT_USER=".$id_ad_user." AND
					FK_KAT IN (".mysql_escape_string(implode(",", $ar_ids)).")  
			");
		}
		// Return result as json object
		header('Content-type: application/json');
		die(json_encode(array(
			"count" => count($ar_ids),
			"price"	=> (float)$price
		)));
	}
}

?>
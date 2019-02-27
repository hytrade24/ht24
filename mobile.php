<?php
/* ###VERSIONSBLOCKINLCUDE### */



function validateUser($user, $pass) {
	if (empty($user) || empty($pass)) return false;
	$query = "SELECT * FROM `user` WHERE NAME LIKE '".mysql_escape_string($user)."' AND MD5(PASS)='".mysql_escape_string($pass)."'";
	$result = mysql_query($query);
	if ($result !== false) {
		$_SESSION["user"] = $user;
		$_SESSION["pass"] = $pass;
		return mysql_fetch_assoc($result);
	}
	return false;
}

session_start();

require_once "inc.server.php";
$mysql = mysql_connect($db_host, $db_user, $db_pass);
mysql_select_db($db_name);

$action = $_REQUEST["action"];
$ar_user = validateUser($_SESSION["user"], $_SESSION["pass"]);

switch ($action) {
	case "login":
		$user = $_REQUEST["user"];
		$pass = $_REQUEST["pass"];
		$ar_user = validateUser($user, $pass);
		die(json_encode(array( "success" => ($ar_user !== false) )));
		/*
		if ($ar_user !== false) {
			file_put_contents("mobile.log", "Login as user success: ".$user);
		} else {
			file_put_contents("mobile.log", "Login as user failed: ".$user);
		}
		*/
		break;
	case "status":
		if ($ar_user !== false) {
			$ar_result = array(
				"users_unlock"		=> 0,
				"complaints"		=> 0,
				"unconfirmed_ads"	=> 0
			);
			// Users to be unlocked
			$query = "SELECT COUNT(*) FROM `user` WHERE STAT=2";
			$result = mysql_query($query);
			if ($result !== false) {
				$row = @mysql_fetch_row($result);
				if ($row !== false) $ar_result["users_unlock"] = $row[0];
			}
			// Issued complaints
			$query = "SELECT COUNT(*) FROM `verstoss`";
			$result = mysql_query($query);
			if ($result !== false) {
				$row = @mysql_fetch_row($result);
				if ($row !== false) $ar_result["complaints"] = $row[0];
			}
			// Unconfirmed advertisements
			$query = "SELECT COUNT(*) FROM `advertisement_user` WHERE DONE=1 AND CONFIRMED=0";
			$result = mysql_query($query);
			if ($result !== false) {
				$row = @mysql_fetch_row($result);
				if ($row !== false) $ar_result["unconfirmed_ads"] = $row[0];
			}
			die(json_encode($ar_result));
		}
		break;
	case "list_complaints":
		if ($ar_user !== false) {
			// Complaints
			$query = "SELECT
					v.*,
					DATEDIFF(STAMP, STAMP_AD_UPDATE) as DIFF,
					am.PRODUKTNAME,
					am.FK_KAT,
					am.FK_USER AS FK_USER_OWNER,
					u.NAME AS MELDER,
					ua.NAME AS OWNER
				FROM verstoss v
					LEFT JOIN ad_master am ON v.FK_AD = am.ID_AD_MASTER
					LEFT JOIN user u ON v.FK_USER=u.ID_USER
					LEFT JOIN user ua ON am.FK_USER=ua.ID_USER
				ORDER BY STAMP DESC";
			$result = mysql_query($query);
			if ($result !== false) {
				while (($row = @mysql_fetch_assoc($result)) !== false) {
					$row["PRODUKTNAME"] = $row["PRODUKTNAME"];
					$row["OWNER"] = $row["OWNER"];
					$row["MELDER"] = $row["MELDER"];
					$row["GRUND"] = $row["GRUND"];
					$ar_result["list"][] = $row;
				}
			}
			die(json_encode($ar_result));
		}
		break;
	case "list_advertisements":
		if ($ar_user !== false) {
			$page = (int)$_REQUEST["page"];
			$ar_result = array(
				"list"		=> array()
			);
			// Users to be unlocked
			$query = "SELECT 
					au.ID_ADVERTISEMENT_USER, au.FK_USER, u.NAME as USER_NAME, au.STAMP_START, au.STAMP_END, au.PRICE,
					(au.CONFIRMED + au.PAID * 2) as STATUS, 
					sa.V1 as SLOT
				FROM `advertisement_user` au 
				LEFT JOIN `string_advertisement` sa ON sa.S_TABLE='advertisement' AND sa.BF_LANG=128 AND sa.FK=au.FK_ADVERTISEMENT
				LEFT JOIN `user` u ON u.ID_USER=au.FK_USER
				WHERE au.DONE=1
				LIMIT ".($page-1).",20";
			$result = mysql_query($query);
			if ($result !== false) {
				while (($row = @mysql_fetch_assoc($result)) !== false) {
					$row["STAMP_START"] = ($row["STAMP_START"] == null ? "----" : date("d.m.Y", strtotime($row["STAMP_START"])));
					$row["STAMP_END"] = ($row["STAMP_END"] == null ? "----" : date("d.m.Y", strtotime($row["STAMP_END"])));
					$row["PRICE"] = sprintf("%.2f", $row["PRICE"]);
					$ar_result["list"][] = $row;
				}
			}
			die(json_encode($ar_result));
		}
		break;
	case "list_users":
		if ($ar_user !== false) {
			$page = (int)$_REQUEST["page"];
			$ar_result = array(
				"list"		=> array()
			);
			// Users to be unlocked
			$query = "SELECT ID_USER, STAT, NAME, FK_USERGROUP AS `GROUP`, VORNAME, NACHNAME, FIRMA, TEL FROM `user` LIMIT ".($page-1).",20";
			$result = mysql_query($query);
			if ($result !== false) {
				while (($row = @mysql_fetch_assoc($result)) !== false) {
					$ar_result["list"][] = $row;
				}
			}
			die(json_encode($ar_result));
		}
		break;
	case "list_invoices":
		if ($ar_user !== false) {
			$page = (int)$_REQUEST["page"];
			$ar_result = array(
				"list"		=> array()
			);
			// Users to be unlocked
			$query = "SELECT 
					i.ID_INVOICE, i.STAMP_DELIVERY, i.PAY_STATUS, i.FK_USER, u.NAME as USER_NAME, i.BRUTTO, i.STAMP_PAY_UNTIL 
				FROM `invoice` i 
				LEFT JOIN `user` u ON u.ID_USER=i.FK_USER
				LIMIT ".($page-1).",20";
			$result = mysql_query($query);
			if ($result !== false) {
				while (($row = @mysql_fetch_assoc($result)) !== false) {
					$row["STAMP_DELIVERY"] = ($row["STAMP_DELIVERY"] == null ? "----" : date("d.m.Y", strtotime($row["STAMP_DELIVERY"])));
					$row["STAMP_PAY_UNTIL"] = ($row["STAMP_PAY_UNTIL"] == null ? "----" : date("d.m.Y", strtotime($row["STAMP_PAY_UNTIL"])));
					$row["BRUTTO"] = sprintf("%.2f", $row["BRUTTO"]);
					$ar_result["list"][] = $row;
				}
			}
			die(json_encode($ar_result));
		}
		break;
	case "get_user":
		if ($ar_user !== false) {
			$id_user = (int)$_REQUEST["id_user"];
			$ar_result = array();
			// Users to be unlocked
			$query = "SELECT ID_USER, STAT, NAME, FK_USERGROUP AS `GROUP`, VORNAME, NACHNAME, FIRMA, TEL FROM `user` WHERE ID_USER=".$id_user;
			$result = mysql_query($query);
			if ($result !== false) {
				if (($row = @mysql_fetch_assoc($result)) !== false) {
					$ar_result = $row;
				}
			}
			die(json_encode($ar_result));
		}
		break;
	case "update_user":
		if ($ar_user !== false) {
			$id_user = (int)$_REQUEST["id_user"];
			$stat = (int)$_REQUEST["stat"];
			$values = "STAT=".$stat.", VORNAME='".mysql_escape_string($_REQUEST["firstname"])."', NACHNAME='".mysql_escape_string($_REQUEST["lastname"])."', ".
				"FIRMA='".mysql_escape_string($_REQUEST["company"])."', TEL='".mysql_escape_string($_REQUEST["phone"])."'";
			$query = "UPDATE `user` SET ".$values." WHERE ID_USER=".$id_user;
			if (($result = @mysql_query($query)) !== false) {
				die(json_encode(array( "success" => true )));
			}
		}
		die(json_encode(array( "success" => false )));
		break;
	default:
		file_put_contents("mobile.log", var_export($_REQUEST, true));		
		break;
}

?>

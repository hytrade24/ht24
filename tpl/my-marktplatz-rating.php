<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.ad_rating.php';

$update_id_ad_sold_rating = $_POST['UPDATE_ID_AD_SOLD_RATING'];
if ( $update_id_ad_sold_rating == "1" ) {
	$rating = $_POST['RATING'];
	$id_ad_sold_rating = $_POST['ID_AD_SOLD_RATING'];
	$comment = $_POST['COMMENT'];

	$arr_ad_sold_rating = array(
		"ID_AD_SOLD_RATING" =>  $id_ad_sold_rating,
		"RATING"            =>  $rating,
		"COMMENT"           =>  $comment
	);
	$db->update(
		"ad_sold_rating",
		$arr_ad_sold_rating
	);

	$tpl_content->addvar("redirect_page",1);

	return;
}


$adSoldId = ($_POST['ID_AD_SOLD'] ? $_POST['ID_AD_SOLD'] : (int)$ar_params[1]);
$id_ad_sold_rating = null;

$query = "
  	SELECT a.*,
  	    uv.NAME as USER_NAME_VK,
  	    uv.IS_VIRTUAL as USER_VIRT_VK,
  	    ue.NAME as USER_NAME,
  	    ue.IS_VIRTUAL as USER_VIRT,
  		a.PRODUKTNAME as AD_PRODUCTNAME
  		FROM `ad_sold` a
  		JOIN `user` uv ON uv.ID_USER=a.FK_USER_VK
  		JOIN `user` ue ON ue.ID_USER=a.FK_USER
  		WHERE a.ID_AD_SOLD=".$adSoldId." AND (a.FK_USER_VK=".$uid." OR a.FK_USER=".$uid.")";

$update = false;
if ( isset($_GET["update"]) ) {
	if ( $_GET["update"] == "1" ) {
		$id_ad_sold_rating = $_GET['id_ad_sold_rating'];
		$update = true;
		$query = "
	    SELECT a.*, ar.*,
	        uv.NAME as USER_NAME_VK,
	        uv.IS_VIRTUAL as USER_VIRT_VK,
	        ue.NAME as USER_NAME,
	        ue.IS_VIRTUAL as USER_VIRT,
	        a.PRODUKTNAME as AD_PRODUCTNAME
	        FROM `ad_sold` a
	        JOIN `user` uv ON uv.ID_USER=a.FK_USER_VK
	        JOIN `user` ue ON ue.ID_USER=a.FK_USER
	        LEFT JOIN `ad_sold_rating` ar
	        ON ar.ID_AD_SOLD_RATING = ".$id_ad_sold_rating."
	        WHERE a.ID_AD_SOLD=".$adSoldId." AND (a.FK_USER_VK=".$uid." OR a.FK_USER=".$uid.")
	        AND ar.FK_AD_SOLD = " . $adSoldId;
	}
}

$adSold = $db->fetch1($query);

$existsRating = $db->fetch1("SELECT COUNT(*) as anz FROM ad_sold_rating WHERE FK_AD_SOLD=".mysql_escape_string($adSoldId)." AND FK_USER_FROM=".mysql_escape_string($uid)."");

if ((!empty($adSold) && ($existsRating['anz'] == 0) || ($update)) ) {
	/*
	 * @var int $role
	 */
	$role = ($adSold["FK_USER_VK"] == $uid ? 2 : 1);

	if (!empty($_POST)) {
		$errors = array();

		// Text in ISO-8859-1 konvertieren (ist wegen ajax utf8)
		$_POST["COMMENT"] = str_replace("â¬", "EUR", $_POST["COMMENT"]);
		$_POST["COMMENT"] = $_POST["COMMENT"];

		if ( $update != true ) {
			if (($adSold["RATED"] & $role) > 0) $errors["ERR_ALREADY_RATED"] = 1;
			if (($_POST["RATING"] <= 0) || ($_POST["RATING"] > 5)) $errors["ERR_NO_RATING"] = 1;
			if (strlen($_POST["COMMENT"]) < 1) {
				$errors["ERR_NO_COMMENT"] = 1;
			} elseif (strlen($_POST["COMMENT"]) < 10) {
				$errors["ERR_SHORT_COMMENT"] = 1;
			}
		}
		else {
			$tpl_content->addvar("ID_AD_SOLD_RATING",$id_ad_sold_rating);
		}

		if (strlen($_POST["COMMENT"]) > 255) {
			$errors["ERR_LONG_COMMENT"] = 1;
			$_POST["COMMENT"] = substr($_POST["COMMENT"], 0, 255);
		}

		if (!empty($errors)) {
			$tpl_content->addvars($_POST);
			$tpl_content->addvar("error", 1);
			$tpl_content->addvars($errors);
		} else {
			if($role == 2) {
				$userId = $adSold["FK_USER"];
				$userFromId = $adSold["FK_USER_VK"];
			} else {
				$userId = $adSold["FK_USER_VK"];
				$userFromId = $adSold["FK_USER"];
			}

			$adRatingManagement = AdRatingManagement::getInstance($db);
			$adRatingManagement->insertAdRating($adSoldId, $userId, $userFromId, $_POST["RATING"], $_POST["COMMENT"], $adSold['AD_PRODUCTNAME']);

			if ($adSold["FK_USER_VK"] == $uid) {
				$adSold["RATED"] = $adSold["RATED"] | 2;
				$db->update("ad_sold", $adSold);
				$tpl_content->addvar('READY', 1);
				//die(forward("/my-marktplatz-verkaeufe,show_done.htm"));
			} else {
				$adSold["RATED"] = $adSold["RATED"] | 1;
				$db->update("ad_sold", $adSold);
				$tpl_content->addvar('READY', 1);
				//die(forward("/my-marktplatz-einkaeufe,show_done.htm"));
			}
		}
	}

	if ($adSold["FK_USER_VK"] == $uid) $tpl_content->addvar("VK", 1);
	$ar_ad = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".$adSold["FK_AD"]);

	$ar = $adSold;
	if (!empty($ar_ad)) {
		$ar = array_merge($ar_ad, $ar);
	}
	foreach($ar as $key => $value) {
		if(is_string($value)) {
			$ar[$key] = $value;
		}
	}
	$tpl_content->addvars($ar);
} else {
	$tpl_content->addvar("not_found", 1);
}
?>
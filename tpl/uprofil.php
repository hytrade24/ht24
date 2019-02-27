<?php
/* ###VERSIONSBLOCKINLCUDE### */

if (!$uid) {
    list($accessUser, $accessHash) = explode("!", $_SESSION['TRADER_USER_ACCESS_HASH']);
    $accessCheck = $db->fetch_atom("SELECT MD5(CONCAT(NAME,SALT,EMAIL)) FROM `user` WHERE ID_USER=".(int)$accessUser);
    if ($accessCheck != $accessHash) {
        die(forward($tpl_content->tpl_uri_action("404")));
    } else {
        $uid = (int)$accessUser;
    }
}

require_once $ab_path.'sys/lib.user.php';
$userManagement = UserManagement::getInstance($db);

$get_uid = (int)$ar_params[1];
$get_uid = (int)$_GET['SELLER_ID'];


if ($get_uid > 0) {

	$anzahl_verkaufen = $db->fetch_atom("select count(FK_USER_VK) from ad_sold where CONFIRMED=1 AND FK_USER_VK = " . $get_uid . " and FK_USER  =" . $uid);
	$anzahl_kaufen = $db->fetch_atom("select count(FK_USER) from ad_sold where CONFIRMED=1 AND FK_USER_VK = " . $uid . " and FK_USER  =" . $get_uid);
	$anonymize = ($nar_systemsettings["MARKTPLATZ"]["HIDE_CONTACT_INFO"] ? (($anzahl_kaufen + $anzahl_verkaufen) == 0) : false);
	$tpl_content->addvar("anzahl_verkaufen", $anzahl_verkaufen);
	$tpl_content->addvar("anzahl_einkaufen", $anzahl_kaufen);

	$tpl_content->addvar("anonymisierung", $anonymize);

	$data = $db->fetch1("select VORNAME,NACHNAME,NAME,CACHE,STAMP_REG,LASTACTIV,URL,STRASSE,PLZ,ORT,LU_PROFESSION,ID_USER,UEBER, ROUND(RATING) as lastrate,TIMESTAMPDIFF(YEAR,GEBDAT,CURDATE()) as age,TEL,EMAIL from user where ID_USER=" . $get_uid); // Userdaten lesen
	// bewertungen
	$ratings = $db->fetch_table("
    	SELECT r.*, s.STAMP_BOUGHT,
    		(SELECT NAME FROM `user` WHERE ID_USER=r.FK_USER_FROM) as USERNAME_FROM
    		FROM `ad_sold_rating` r
    			LEFT JOIN `ad_sold` s ON r.FK_AD_SOLD=s.ID_AD_SOLD
    	WHERE r.FK_USER=" . $get_uid . "
    		ORDER BY s.STAMP_BOUGHT DESC limit 10");
	$tpl_content->addlist("USER_RATINGS", $ratings, $ab_path . 'tpl/' . $s_lang . '/uprofil.rating.row.htm');
	// end bewertungen

	$userData = $userManagement->fetchFullDatasetById($get_uid);

	$tpl_content->addvars($userData, "USER_");
	$tpl_content->addvars($data);

}
?>
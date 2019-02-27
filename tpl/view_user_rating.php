<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.ad_rating.php';

$paginatorPage = ((int)$ar_params[4] ? (int)$ar_params[4] : 1);
$paginatorItemsPerPage = 30;
$paginatorOffset = ($paginatorItemsPerPage*$paginatorPage)-$paginatorItemsPerPage;

$userId = ((int)$ar_params[2] ? (int)$ar_params[2] : null);
$user_ = $db->fetch1("select VORNAME,NACHNAME,NAME,CACHE,STAMP_REG,LASTACTIV,URL,STRASSE,PLZ,ORT,LU_PROFESSION,ID_USER,UEBER, ROUND(RATING) as lastrate,TIMESTAMPDIFF(YEAR,GEBDAT,CURDATE()) as age,TEL from user where ID_USER='". $userId."'");

$useView = ((isset($ar_params[3]) && (in_array((string) $ar_params[3], array('kaeufer', 'verkaeufer'))))? ((string)$ar_params[3]):null);

$tpl_content->addvar("active_rating", 1);

if(($userId != null) && ($user_ != null)) {

	$adRatingManagement = AdRatingManagement::getInstance($db);
	/**
	 * Berechne Gesamtbewertung
	 */

	$adRatingCount = $adRatingManagement->countAdRatingsByUserId($userId);
	$adRatingAverageValue = $adRatingManagement->getRatingByUserId($userId);

	/**
	 * Liste aller Bewertungen
	 */
	$conditionParameter = array();
	if($useView == "kaeufer") {$conditionParameter['restrictSoldTypeAsBuyer'] = true; }
	if($useView == "verkaeufer") {$conditionParameter['restrictSoldTypeAsSeller'] = true; }

	$adRatings = $adRatingManagement->fetchAllAdRatingsByUserId($userId, $conditionParameter, $paginatorOffset .', ' . $paginatorItemsPerPage);
	$countAdRatings = $adRatingManagement->countAdRatingsByUserId($userId, $conditionParameter);

	foreach ($adRatings as $key => $adRating) {
		// Rating anreichern mit Informationen
		$adRatings[$key]['USER_FROM_ID'] = null;

		// User
		if($adRating['FK_USER_FROM'] != null) {
			$userFrom = $db->fetch1("SELECT ID_USER, NAME, IS_VIRTUAL FROM user WHERE ID_USER = '".mysql_escape_string($adRating['FK_USER_FROM'])."'");
			if($userFrom) {
				$adRatings[$key]['USER_FROM_ID'] = $userFrom['ID_USER'];
				$adRatings[$key]['USER_FROM_NAME'] = $userFrom['NAME'];
				$adRatings[$key]['USER_FROM_VIRTUAL'] = $userFrom['IS_VIRTUAL'];
			}
		}

		// Anzeige
		$ad = $db->fetch1("SELECT ad_sold.FK_AD, ad_sold.PRODUKTNAME, ad_sold.STAMP_BOUGHT, adm.FK_KAT, (adm.ID_AD_MASTER IS NOT NULL) as AD_EXISTS  FROM ad_sold LEFT JOIN ad_master adm ON adm.ID_AD_MASTER = ad_sold.FK_AD WHERE ID_AD_SOLD = '".mysql_escape_string($adRating['FK_AD_SOLD'])."'");
		if ($ad) {
			$adRatings[$key]['AD_ID'] = $ad['FK_AD'];
			$adRatings[$key]['AD_NAME'] = $ad['PRODUKTNAME'];
			$adRatings[$key]['AD_FK_KAT'] = $ad['FK_KAT'];
			$adRatings[$key]['AD_EXISTS'] = $ad['AD_EXISTS'];
			// Verkaufdatum sofern Bewertungsdatum nicht existiert
			if($adRating['STAMP_RATED'] == "0000-00-00 00:00:00") {
				if($ad['STAMP_BOUGHT']) {
					$adRatings[$key]['STAMP_RATED'] = $ad['STAMP_BOUGHT'];
				}
			}
		}
		//var_dump($adRatings[$key]['userFrom']);
	}

	//$tpl_content->addvar("adRatingTest", array("x" => "test", "a" => array("x" => "t", "y" => a)));

	$tpl_content->addvar("adRatingCount", $adRatingCount);
	$tpl_content->addvar("adRatingAverageValue", $adRatingAverageValue);
	$tpl_content->addlist("adRatings", $adRatings, $ab_path.'tpl/'.$s_lang.'/view_user_rating.row.htm');

	$pager = htm_browse_extended($countAdRatings, $npage, "view_user_rating,".addnoparse(chtrans($user_['NAME'])).",".$userId.",".$useView.",{PAGE}", $paginatorItemsPerPage);
	$tpl_content->addvar("pager", $pager);

	$tpl_content->addvar("t_".$view, 1);
	$tpl_content->addvar("UID", $uid);

	$tpl_content->addvar("useViewSeller", ($useView == "verkaeufer"));
	$tpl_content->addvar("useViewBuyer", ($useView == "kaeufer"));

	$tpl_content->addvars($user_, 'USER_');
} else {
	$nullUser = $db->fetch_blank('user');
	$tpl_content->addvars($nullUser);
}
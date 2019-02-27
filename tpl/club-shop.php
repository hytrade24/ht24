<?php
/* ###VERSIONSBLOCKINLCUDE### */

function killbb(&$row,$i)
{
	$row['BESCHREIBUNG'] = substr(strip_tags($row['BESCHREIBUNG']), 0, 250);
	$row['BESCHREIBUNG'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['BESCHREIBUNG']);
}

require_once $ab_path."sys/lib.club.php";
require_once $ab_path.'sys/lib.ad_constraint.php';
$clubManagement = ClubManagement::getInstance($db);

$id_club = (int)$ar_params[2];
$fk_kat = ((int)$ar_params[3] ? (int)$ar_params[3] : 0);
$npage = ((int)$ar_params[4] ? (int)$ar_params[4] : 1);
$perpage = 10;

/** Club **/
$ar_club = $clubManagement->getClubById($id_club);
if (!empty($ar_club) && (($ar_club['STATUS'] == 1) || ($ar_club['FK_USER'] == $uid))) {
	$ar_club['LOGO'] = ($ar_club['LOGO'] != "" ? 'cache/club/logo/'.$ar_club['LOGO'] : null);
	$ar_ads = $clubManagement->getAdsByClubId($id_club, $fk_kat, $langval, $npage, $perpage);
	Rest_MarketplaceAds::extendAdDetailsList($ar_ads);
	$all = $db->fetch_atom("SELECT FOUND_ROWS()");
	$club_kats = $clubManagement->getKatsByClubId($id_club, $fk_kat, $langval);
	$pager = htm_browse_extended($all, $npage, "club-shop,".chtrans($ar_club['NAME']).",".$id_club.",".$fk_kat.",{PAGE}", $perpage);

    $tpl_content->addvar("active_club_shop", 1);
	$tpl_content->isTemplateRecursiveParsable = TRUE;
	$tpl_content->isTemplateCached = TRUE;
	$tpl_content->addvars($ar_club, "CLUB_");
	$tpl_content->addvar("own_kats", $club_kats);
	$tpl_content->addlist("liste", $ar_ads, "tpl/".$s_lang."/marktplatz.row.htm", "killbb");
	$tpl_content->addvar("pager", $pager);
} else {
	$url = $tpl_content->tpl_uri_baseurl("/404.htm");
	die(forward( $url ));
}

?>
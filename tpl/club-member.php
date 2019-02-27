<?php
/* ###VERSIONSBLOCKINLCUDE### */

/**
 * @author ebiz-consult
 * @copyright 2013
 */

require_once $ab_path."sys/lib.club.php";
$clubManagement = ClubManagement::getInstance($db);

#Seitenzähler
$id_club = (int)$ar_params[2];
$curpage = ($ar_params[3] ? $ar_params[3] : $ar_params[3]=1);
$perpage = $nar_systemsettings['MARKTPLATZ']['CLUB_MEMBERVIEWS']; // Elemente pro Seite
$limit = (($curpage-1)*$perpage);

/** Club **/
$ar_club = $clubManagement->getClubById($id_club);
if (!empty($ar_club) && (($ar_club['STATUS'] == 1) || ($ar_club['FK_USER'] == $uid))) {
	$ar_club['LOGO'] = ($ar_club['LOGO'] != "" ? 'cache/club/logo/'.$ar_club['LOGO'] : null);
	$ar_members = $clubManagement->getMembersByClubId($id_club, false, $curpage, $perpage);
	//die(var_dump($ar_club));
	$tpl_content->addvars($ar_club, "CLUB_");
	$tpl_content->addlist("liste", $ar_members, "tpl/".$s_lang."/club-member.row.htm");
	#Seitenzähler
	$all = $db->fetch_atom("
  		SELECT
  			FOUND_ROWS()");
	$tpl_content->addvar("ALL_USERS", $all);
	$tpl_content->addvar("pager", htm_browse_extended($all, $ar_params[1], "club-member,".$clubId.",{PAGE}", $perpage));
} else {
	$url = $tpl_content->tpl_uri_baseurl("/404.htm");
	die(forward( $url ));
}


$tpl_content->addvar("active_club_member", 1);
?>
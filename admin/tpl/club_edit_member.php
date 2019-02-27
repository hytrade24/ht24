<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . "sys/lib.club.php";

$clubManagement = ClubManagement::getInstance($db);

$ar_club = array();
$ar_members = array();
$err = array();
$clubId = $_GET['id'];


if ($clubId > 0) {
    $ar_club = $clubManagement->getClubById($clubId);
    $ar_members = $clubManagement->getMembersByClubId($clubId, true);
}

$ar_club['LOGO'] = ($ar_club['LOGO'] != "" ? 'cache/club/logo/' . $ar_club['LOGO'] : null);

$tpl_content->addlist("liste", $ar_members, "tpl/" . $s_lang . "/my-club-members.row.htm");
$tpl_content->addvar('id', $clubId);
$tpl_content->addvars($ar_club, "CLUB_");
?>
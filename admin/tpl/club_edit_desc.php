<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . "sys/lib.club.php";

$clubManagement = ClubManagement::getInstance($db);
$clubId = $_REQUEST['id'];
$clubAction = (isset($_REQUEST['ajax']) ? $_REQUEST['ajax'] :
    (isset($_REQUEST['do']) ? $_REQUEST['do'] : 'view'));
$ar_club = array();

if (isset($_REQUEST['saved'])) {
    $tpl_content->addvar("saved", 1);
}

switch ($clubAction) {
    case 'saved':
        $tpl_content->addvar("saved", 1);
    case 'view':
        if ($clubId > 0) {
            $ar_club = $clubManagement->getClubById($clubId, $langval);
        }
        break;
    case 'save':
        $err = array();
        $ar_club = array_merge($clubManagement->getClubById($clubId), $_POST);
        if ($clubManagement->updateCheckFields($ar_club, $err)) {
            $clubManagement->update($ar_club, $langval);
        }
        if (empty($err)) {
            // Success
            die(forward("index.php?lang=de&page=club_edit_desc&id=" . $clubId . "&saved=1"));
        } else {
            // Failed!
            $ar_club["NEW"] = ($clubId > 0 ? 0 : 1);
            $ar_club["EDITABLE"] = 1;
            $htm_errors = "<li>" . implode("</li>\n<li>", get_messages("CLUB", implode(",", $err))) . "</li>";
            $tpl_content->addvar("errors", $htm_errors);
        }
        break;
}

$ar_club['LOGO'] = ($ar_club['LOGO'] != "" ? 'cache/club/logo/' . $ar_club['LOGO'] : null);

$tpl_content->addvars($ar_club, "CLUB_");

?>
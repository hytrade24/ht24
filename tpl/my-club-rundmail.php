<?php
/* ###VERSIONSBLOCKINLCUDE### */
// if(!empty($_POST))
// die(var_dump($_POST));
require_once $ab_path."sys/lib.club.php";

$clubManagement = ClubManagement::getInstance($db);
$clubId = (isset($ar_params[1]) ? $ar_params[1] : 0);
$clubAction = (isset($ar_params[2]) ? $ar_params[2] : "view");
$ar_club = array();

// return to main page
if ($clubId == 0) {
    die(forward("/my-pages/my-club.htm"));
}

$ar_club = $clubManagement->getClubById($clubId);

$ar_club["IS_MODERATOR"] = $clubManagement->isClubModerator($clubId, $uid);
$ar_club["IS_ADMIN"] = ($ar_club["FK_USER"] == $uid);

// return to main page
if(!$ar_club["IS_MODERATOR"] && !$ar_club["IS_ADMIN"]) {
    die(forward("/my-pages/my-club.htm"));
}

// post
if (!empty($_POST)) {
    // Form abgeschickt
    $clubId = (isset($_POST["ID_CLUB"]) ? $_POST["ID_CLUB"] : $clubId);
    $clubAction = $_POST["do"];
    // Template laden
    $arTemplatesAllowed = array("EVENT_INVITE");
    if (in_array($_POST["TEMPLATE"], $arTemplatesAllowed)) {
        $templateTitle = new Template("tpl/".$s_lang."/my-club-rundmail.tpl_title.".$_POST["TEMPLATE"].".htm");
        $templateBody = new Template("tpl/".$s_lang."/my-club-rundmail.tpl_body.".$_POST["TEMPLATE"].".htm");
        unset($_POST["TEMPLATE"]);
        $templateTitle->addvars($_POST);
        $templateBody->addvars($_POST);
        $ar_club["SUBJECT"] = $templateTitle->process();
        $ar_club["MESSAGE"] = $templateBody->process();
    }
}

if ($clubId > 0) {
    $ar_members = $clubManagement->getMembersByClubId($clubId, $includePending = false, $page = 1, $perpage = 100);

    if (isset($_POST['MEMBERS'])) {
        $count = count($ar_members);

        for ($i=0; $i < $count; $i++) {
            for ($k = count($_POST['MEMBERS']); $k >= 0; $k--) {
                if ($_POST['MEMBERS'][$k] === $ar_members[$i]['FK_USER']) {
                    $ar_members[$i]['CHECKED'] = 1;
                }
            }
        }
    }

    $tpl_content->addlist("liste", $ar_members, "tpl/".$s_lang."/my-club-rundmail.members_row.htm");
}


// action
switch ($clubAction) {
    case 'success':
        $tpl_content->addvar("success", 1);
        break;
    case 'send':
        $err = array();
        $ar_members = $_POST['MEMBERS'];
        $ar_club['MESSAGE'] = trim($_POST['MESSAGE']);
        $ar_club['SUBJECT'] = trim($_POST['SUBJECT']);

        if (empty($ar_members)) {
            $err[] = "NO_USER";
        }

        if (empty($ar_club['MESSAGE'])) {
            $err[] = "NO_MESSAGE_TEXT";
        }

        if (empty($ar_club['SUBJECT'])) {
            $err[] = "NO_SUBJECT_TEXT";
        }

        if ($_POST['ACCEPT_CONDITION'] != 1) {
            $err[] = "CONDITION_NOT_ACCEPTED";
        }

        if (empty($err)) {
            // Success
            $ar_club['USERNAME'] = $user['NAME'];
            $ar_club['CLUB'] = $ar_club['NAME'];

            $c = count($ar_members);
            for ($i = 0; $i < $c; $i++) {
                sendMailTemplateToUser(0, $ar_members[$i], 'RUNDMAIL', $ar_club);
            }

            $url = $tpl_content->tpl_uri_action("my-club-rundmail,".$clubId.",success");
            die(forward($url));
        } else {
            // Failed!

            $htm_errors = "<li>".implode("</li>\n<li>", get_messages("CLUB", implode(",", $err)))."</li>";
            $tpl_content->addvar("errors", $htm_errors);
        }
        break;
}

$ar_club['MODERATOR'] = ($ar_club['IS_ADMIN'] || $ar_club['IS_MODERATOR']);
$tpl_content->addvars($ar_club, "CLUB_");

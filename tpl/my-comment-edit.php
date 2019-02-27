<?php

require_once $ab_path."sys/lib.comment.php";

$commentId = (array_key_exists("ID_COMMENT", $_REQUEST) ? (int)$_REQUEST["ID_COMMENT"] : (int)$ar_params[1]);
$commentTable = CommentManagement::getCommentTable($db, $commentId);

$successUrl = $tpl_content->tpl_uri_action("my-comments,1,sent");

if ($commentTable === NULL) {
    die(forward($tpl_content->tpl_uri_action("my-comments")));
}

$commentManagement = CommentManagement::getInstance($db, $commentTable);
$arComment = $commentManagement->fetchOneByParams(array("ID_COMMENT" => $commentId, "FK_USER" => $uid));
if (!is_array($arComment)) {
    $tpl_content->addvar("not_found", 1);
    return;
}

$articleRated = 0;
if ($arComment["FK"] > 0) {
    $articleRated = $db->fetch_atom("SELECT count(*) FROM `comment` WHERE `TABLE`='".mysql_real_escape_string($arComment["TABLE"])."' AND FK='".mysql_real_escape_string($arComment["FK"])."'
              			AND RATING IS NOT NULL AND FK_USER=".(int)$uid." AND ID_COMMENT<>".$commentId);
} else {
    $articleRated = $db->fetch_atom("SELECT count(*) FROM `comment` WHERE `TABLE`='".mysql_real_escape_string($arComment["TABLE"])."' AND FK_STR='".mysql_real_escape_string($arComment["FK_STR"])."'
              			AND RATING IS NOT NULL AND FK_USER=".(int)$uid." AND ID_COMMENT<>".$commentId);
}
if ($articleRated > 0) {
    $err[] = 'ALREADY_RATED';
}

if (!empty($_POST)) {
    $err = array();
    if (strlen(trim($_POST['COMMENT'])) < 10) {
    	$err[] = "COMMENT_TOO_SHORT";
    }
    if (($_POST["RATING"] <= 0) || ($_POST["RATING"] > 5)) {
        $arComment["RATING"] = NULL;
    } else {
        $arComment["RATING"] = (int)$_POST["RATING"];
    }

    if (empty($err)) {
        $arComment["COMMENT"] = trim($_POST["COMMENT"]);
        // Kommentar inkl. möglicher Antwort löschen und neu erstellen
        $commentManagement->deleteComment($commentId, (int)$uid, true);
        if((int)$arComment["FK"] > 0) {
            $result = $commentManagement->addComment((int)$arComment["FK"], $arComment["TITLE"], $arComment["COMMENT"], (int)$uid, $arComment["RATING"]);
        } else {
            $result = $commentManagement->addCommentStr($arComment["FK_STR"], $arComment["TITLE"], $arComment["COMMENT"], (int)$uid, $arComment["RATING"]);
        }
        // Kommentar update
        #$result = $commentManagement->updateComment($commentId, $arComment);
        if ($result > 0) {
            die(forward($successUrl));
        }
    } else {
        $tpl_content->addvar("err", get_messages("COMMENT", implode(",", $err)));
        $tpl_content->addvars($_POST);
    }
}

$tpl_content->addvars($arComment);
if ($articleRated < 1) {
    $tpl_content->addvar("SHOW_RATING", $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_RATED']);
}
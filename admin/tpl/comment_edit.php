<?php

require_once $ab_path."sys/lib.comment.php";
$commentManagement = CommentManagement::getInstance($db);

$ar_comment = $commentManagement->fetchOneByParams(array("ID_COMMENT" => $_REQUEST['ID_COMMENT']));
$commentManagement->setTable($ar_comment['TABLE']);
if ($ar_comment["FK"] > 0) {
	$ar_comment["TARGET_LINK"] = $commentManagement->getTargetLink($ar_comment["FK"]);
} else {
	$ar_comment["TARGET_LINK"] = $commentManagement->getTargetLinkStr($ar_comment["FK_STR"]);
}

if (!empty($_POST)) {
	$id_comment = (int)$_POST["ID_COMMENT"];
	$ar_comment["RATING"] = ($_POST["RATING"] > 0 ? (int)$_POST["RATING"] : null);
	$ar_comment["COMMENT"] = $_POST["COMMENT"];
    $ar_comment['ANSWER_COMMENT'] = $_POST['ANSWER_COMMENT'];
	$commentManagement->updateComment($id_comment, $ar_comment);
	die(forward("index.php?frame=popup&page=comment_edit&frompopup=1&ID_COMMENT=".$id_comment."&success=1"));
}

$query = "SELECT `value`
			FROM `option`
			WHERE `plugin` = 'MARKTPLATZ' AND `typ` = 'ALLOW_COMMENTS_RATED'";

$rating_allow_for_comment = $db->fetch_atom($query);
$showRating = $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_RATED'] 
	&& (($ar_comment["TABLE_DESC"] == "Anzeige") || $nar_systemsettings['MARKTPLATZ']['GLOBAL_RATINGS']);

$tpl_content->addvars($ar_comment);
$tpl_content->addvar("ALLOW_RATING", $showRating);
$tpl_content->addvar("success", (isset($_REQUEST["success"]) ? 1 : 0));

?>

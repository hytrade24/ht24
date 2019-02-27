<?php

require_once $ab_path."sys/lib.comment.php";
$commentManagement = CommentManagement::getInstance($db);

$ar_comment = $commentManagement->fetchOneByParams(array("ID_COMMENT" => $_REQUEST['ID_COMMENT']));
$ar_comment["TARGET_LINK"] = CommentManagement::getCommentTargetLink($db, $ar_comment["FK"]);

$tpl_content->addvars($ar_comment);

?>
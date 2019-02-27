<?php

if(!function_exists("addCommentLink")) {
	function addCommentLink(&$row, $i) {
		global $db;
		$cm = CommentManagement::getInstance($db, $row['TABLE']);
		if ($row["FK"] > 0) {
			$row["TARGET_LINK"] = $cm->getTargetLink($row["FK"]);
		} else {
			$row["TARGET_LINK"] = $cm->getTargetLinkStr($row["FK_STR"]);
		}
	}
}

$table = ($ar_params[0] == 'comment_list' ? $ar_params[1] : $tpl_content->vars['TABLE']);
$fk = ($ar_params[0] == 'comment_list' ? (int)$ar_params[2] : (int)$tpl_content->vars['FK']);
if ((int)$ar_params[3] > 0) {
    $pageCur = (int)$ar_params[3];
} elseif ((int)$ar_params[7] > 0) {
    $pageCur =  (int)$ar_params[7];
} else {
    $pageCur = 1;
}

$pageItems = 5;

require_once $ab_path."sys/lib.comment.php";
$commentManagement = CommentManagement::getInstance($db, $table);

$all = 0;
$ar_comments = $commentManagement->fetchPublicByFk($fk, ($pageCur-1)*$pageItems, $pageItems, $all );

$pager = htm_browse_extended($all, $pageCur, 'comment_list,'.$table.','.$fk.',{PAGE}', $pageItems);

if (!empty($ar_comments)) {
	$tpl_file_list = "tpl/".$s_lang."/comment_list.".$table.".row.htm";
	if (!file_exists(CacheTemplate::getHeadFile($tpl_file_list))) {
		$tpl_file_list = "tpl/".$s_lang."/comment_list.row.htm";
	}
	$tpl_content->addvar("npage", $npage);
	$tpl_content->addvar("pager", $pager);
	$tpl_content->addlist("liste", $ar_comments, $tpl_file_list, "addCommentLink");

}

$tpl_content->addvar("TABLE", $table);
$tpl_content->addvar("FK", $fk);

$tpl_content->addvar("LOGIN_ID_USER", $uid);
$tpl_content->addvar("ALLOW_COMMENTS_RATED", $nar_systemsettings["MARKTPLATZ"]["ALLOW_COMMENTS_RATED"]);

?>

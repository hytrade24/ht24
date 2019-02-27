<?php

function commentShorten(&$row, $i) {
	global $db;
	$cm = CommentManagement::getInstance($db, $row['TABLE']);
	$row["COMMENT_SHORT"] = substr(strip_tags(html_entity_decode($row['COMMENT'])), 0, 250);
	if ($row["FK"] !== null) {
		$row["TARGET_LINK"] = $cm->getTargetLink($row["FK"]);
	} else {
		$row["TARGET_LINK"] = $cm->getTargetLinkStr($row["FK_STR"]);
	}
}

require_once $ab_path."sys/lib.comment.php";
$commentManagement = CommentManagement::getInstance($db);
$isSearch = false;

if ($_REQUEST["delete"] > 0) {
	header('Content-type: application/json');
	die(json_encode(array(
		"success" => $commentManagement->deleteComment((int)$_REQUEST["delete"], 1)
	)));
}

if ($_REQUEST["unlock"] > 0) {
	header('Content-type: application/json');
	die(json_encode(array(
		"success" => $commentManagement->unlockComment((int)$_REQUEST["unlock"], 1)
	)));
}

if ($_REQUEST["lock"] > 0) {
	header('Content-type: application/json');
	die(json_encode(array(
		"success" => $commentManagement->lockComment((int)$_REQUEST["lock"], 1)
	)));
}

if ($_REQUEST["toggle"] > 0) {
	$ar_comment = $commentManagement->fetchOneByParams(array("ID_COMMENT" => (int)$_REQUEST["toggle"]));

	header('Content-type: application/json');
	die(json_encode(array(
		"success" => $commentManagement->setCommentVisible((int)$_REQUEST["toggle"], abs($ar_comment["IS_PUBLIC"]-1))
	)));
}

$ar_tables = array();
$ar_tables_nar = get_messages('COMMENT_TABLES');
foreach ($ar_tables_nar as $table => $desc) {
	$ar_tables[] = array("TABLE" => $table, "DESC" => $desc, "SELECTED" => ($_REQUEST["TABLE"] == $table));
}

$pageUrl = "index.php?page=".$tpl_content->vars['curpage'];
$limitOffset = ($_REQUEST["npage"] > 0 ? $_REQUEST["npage"] - 1 : 0);
$limitCount = 25;
$all = 0;
$ar_params = array();

if ($_REQUEST["FK_AUTOR"] > 0) {
	$ar_params["FK_USER"] = (int)$_REQUEST["FK_AUTOR"];
	$pageUrl .= "&FK_AUTOR=".(int)$_REQUEST["FK_AUTOR"]."&NAME_=".urlencode($_REQUEST["NAME_"]);
	$isSearch = true;
} else if (!empty($_REQUEST["NAME_"])) {
	$ar_params["NAME_"] = $_REQUEST["NAME_"];
	$pageUrl .= "&NAME_=".urlencode($_REQUEST["NAME_"]);
	$isSearch = true;
}
if (!empty($_REQUEST["SEARCH"])) {
	$ar_params["SEARCH"] = $_REQUEST["SEARCH"];
	$pageUrl .= "&SEARCH=".urlencode($_REQUEST["SEARCH"]);
	$isSearch = true;
}
if (!empty($_REQUEST["TABLE"])) {
	if (substr($_REQUEST["TABLE"], -4) == "_str") {
		$ar_params["TABLE"] = substr($_REQUEST["TABLE"], 0, -4);
		$ar_params["FK_STR"] = $_REQUEST["FK_STR"] = $_REQUEST["FK"];
	} else {
		$ar_params["TABLE"] = $_REQUEST["TABLE"];
	}
	$pageUrl .= "&TABLE=".urlencode($_REQUEST["TABLE"]);
	$isSearch = true;
}
if (empty($_REQUEST["FK_STR"]) && ($_REQUEST["FK"] > 0)) {
	$ar_params["FK"] = (int)$_REQUEST["FK"];
	$pageUrl .= "&FK=".(int)$_REQUEST["FK"];
	$isSearch = true;
}
if ($_REQUEST["RATING_MIN"] > 0) {
	$ar_params["RATING_MIN"] = (int)$_REQUEST["RATING_MIN"];
	$pageUrl .= "&RATING_MIN=".(int)$_REQUEST["RATING_MIN"];
	$isSearch = true;
}
if ($_REQUEST["RATING_MAX"] > 0) {
	$ar_params["RATING_MAX"] = (int)$_REQUEST["RATING_MAX"];
	$pageUrl .= "&RATING_MAX=".(int)$_REQUEST["RATING_MAX"];
	$isSearch = true;
}
if ($_REQUEST["IS_REVIEWED"] > 0) {
	$ar_params["IS_REVIEWED"] = ($_REQUEST["IS_REVIEWED"] == 1 ? 1 : 0);
	$pageUrl .= "&IS_REVIEWED=".(int)$_REQUEST["IS_REVIEWED"];
	$isSearch = true;
}
if (($_REQUEST["IS_PUBLIC"] !== null) && ($_REQUEST["IS_PUBLIC"] != "")) {
	$ar_params["IS_PUBLIC"] = (int)$_REQUEST["IS_PUBLIC"];
	$pageUrl .= "&IS_PUBLIC=".(int)$_REQUEST["IS_PUBLIC"];
	$isSearch = true;
} else {
	$_REQUEST["IS_PUBLIC"] = 2;
}

if ($isSearch) {
	$tpl_content->addvar("SEARCH_RESULT", 1);
}

$tpl_content->addvars($_REQUEST);

$ar_comments = $commentManagement->fetchAllByParams($ar_params, $limitOffset * $limitCount, $limitCount, $all);
$pager = htm_browse($all, $limitOffset+1, $pageUrl."&npage=", $limitCount);
// Add template variables
$tpl_content->addvar("npage", $npage);
$tpl_content->addvar("pager", $pager);

$tpl_content->addlist("liste_tables", $ar_tables, "tpl/".$s_lang."/comments.option_table.htm");
$tpl_content->addlist("liste", $ar_comments, "tpl/".$s_lang."/comments.row.htm", "commentShorten");

?>
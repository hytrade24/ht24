<?php

if (!function_exists("addCommentLink")) {
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

if (!function_exists("addPageCounter")) {
	function addPageCounter(&$row, $i)
	{
		global $db;
		$cm = CommentManagement::getInstance($db, $row['TABLE']);
		if ($row["FK"] > 0) {
			$row['PAGE_COUNTER'] = $cm->getPageForComment($row['ID_COMMENT'], $row['FK'], 5);
		} else {
			$row['PAGE_COUNTER'] = $cm->getPageForCommentStr($row['ID_COMMENT'], $row['FK_STR'], 5);
		}
		$row['TARGET_LINK_WITH_PAGER'] = $cm->getCommentTargetLink($db, $row["ID_COMMENT"], "," . $row['PAGE_COUNTER']);
	}
}

if (!function_exists("shortMessage")) {
	function shortMessage(&$row, $i)
	{
		$row['SHORT_COMMENT'] = substr($row['COMMENT'], 0, 300) . '...';
	}
}

$action = (!empty($tpl_content->vars["ACTION"]) ? $tpl_content->vars["ACTION"] : "list");
$pageCur = (!empty($tpl_content->vars["PAGE"]) ? (int)$tpl_content->vars["PAGE"] : 1);
$pageItems = (!empty($tpl_content->vars["PERPAGE"]) ? (int)$tpl_content->vars["PERPAGE"] : 10);
$ar_param = array();
$ar_param_search = array("FK", "FK_USER", "FK_USER_OWNER", "SEARCH", "TYPE", "IS_REVIEWED", "IS_PUBLIC", "SORT_BY", "SORT_DIR");

foreach ($ar_param_search as $index => $field) {
	if (isset($tpl_content->vars[$field])) {
		$ar_param[$field] = $tpl_content->vars[$field];
	}
	if (isset($_POST[$field])) {
		$ar_param[$field] = $_POST[$field];
	}
}
if (isset($_POST["ACTION"])) {
	$action = $_POST["ACTION"];
}
if (isset($_POST["PAGE"])) {
	$pageCur = $_POST["PAGE"];
}
if (isset($_POST["PERPAGE"])) {
	$pageItems = $_POST["PERPAGE"];
}
// Default values
if (!isset($ar_param["IS_REVIEWED"])) {
	$ar_param["IS_REVIEWED"] = "all";
}
if (!isset($ar_param["IS_PUBLIC"])) {
	$ar_param["IS_PUBLIC"] = "all";
}
$ar_param["IS_CONFIRMED"] = 1;
if (!isset($ar_param["SORT_BY"])) {
	$ar_param["SORT_BY"] = "STAMP";
}
if (!isset($ar_param["SORT_DIR"])) {
	$ar_param["SORT_DIR"] = "ASC";
}
// Additional parameters
if ($ar_param["SEARCH_FOR_ID"] == 1) {
	$tpl_content->addvar("SEARCH_FOR_ID", 1);
}
if ($ar_param["TYPE_LOCKED"] == 1) {
	$tpl_content->addvar("TYPE_LOCKED", 1);
}
$view = "DEFAULT";
if (!empty($_POST['VIEW'])) $view = $_POST['VIEW'];
if (!empty($tpl_content->vars['VIEW'])) $view = $tpl_content->vars['VIEW'];
switch ($view) {
	case 'MINIMAL':
		$tpl_content->addvar("HIDE_SORT", 1);
		$tpl_content->addvar("HIDE_SEARCH", 1);
		$tpl_content->addvar("HIDE_LEGEND", 1);
		break;
	default:
		break;
}
$tpl_content->addvar("VIEW", $view);

require_once $ab_path."sys/lib.comment.php";
require_once $ab_path."sys/lib.club.php";
$commentManagement = CommentManagement::getInstance($db);
$clubManagement = ClubManagement::getInstance($db);

$tpl_content->addvar("action_".$action, 1);
if (preg_match("/GROUP_([0-9]+)/", $ar_param["TYPE"], $matches)) {
	$tpl_content->addvar("TYPE_GROUP", $matches[1]);
} else {
	$tpl_content->addvar("TYPE_".$ar_param["TYPE"], 1);
}

if ($action == "list") {
	// List of users clubs
	$userClubs = $clubManagement->getClubsByUser($uid, $langval, 1, 128, $all, array('IS_ADMIN' => 'DESC', 'MODERATOR' => 'DESC'));
	for ($i=count($userClubs)-1; $i >= 0; $i--) {
		if (!$userClubs[$i]["IS_ADMIN"] && !$userClubs[$i]["IS_MODERATOR"]) {
			unset($userClubs[$i]);
		}
	}
	// List users comments
	$ar_comments = array();
	$all = 0;
	$ar_comments = $commentManagement->fetchAllByParams( $ar_param, ($pageCur-1)*$pageItems, $pageItems, $all );
	$pager = htm_browse_extended($all, $pageCur, 'my-comments,{PAGE},'.$tpl_content->vars["SHOW"], $pageItems);
	// Add template variables
	$tpl_content->addvars($ar_param);
	$tpl_content->addvar("npage", $pageCur);
	$tpl_content->addvar("perpage", $pageItems);
	$tpl_content->addvar("pager", $pager);
	$tpl_content->addvar("ALL_COMMENTS", $all);
	$tpl_content->addlist("liste", $ar_comments, "tpl/".$s_lang."/my-comments-list.row.htm", "addCommentLink;addPageCounter;shortMessage");
	// Available groups
	$tpl_content->addlist('options_groups', $userClubs, 'tpl/de/my-comments-list.option.htm');
} else {
	$id_comment = ($_POST["ID_COMMENT"] > 0 ? (int)$_POST["ID_COMMENT"] : (int)$ar_params[4]);
    $err = array();
	$success = false;
	if ($id_comment > 0 && $commentManagement->isUserAdmin($id_comment, $uid)) {
		if ($action == "show") {
			if ($commentManagement->setCommentVisible($id_comment, true)) {
				$success = true;
			}
		} else if ($action == "hide") {
			if ($commentManagement->setCommentVisible($id_comment, false)) {
				$success = true;
			}
		} else if ($action == "delete") {
			if ($commentManagement->deleteComment($id_comment)) {
				$success = true;
			}
		} else if ($action == "reply") {
			if(isset($_POST['COMMENT'])) {
				$comment = trim($_POST['COMMENT']);
           	}

			if(isset($_POST['ID_COMMENT'])) {
				$id_comment = $_POST['ID_COMMENT'];
			}

            if (strlen($comment) < 10) {
                $err[] = "COMMENT_TOO_SHORT";
                $success = false;
            }
            else {
                if ($commentManagement->replyComment($id_comment, $comment)) {
                    $success = true;
                }
            }
		} else if ($action == 'unlock') {
			if ($commentManagement->unlockComment($id_comment)) {
				$success = true;
			}
		} else if ($action == 'lock') {
			if ($commentManagement->lockComment($id_comment)) {
				$success = true;
			}
		}
	}

	header("Content-Type: application/json");

    if (!empty($err)) {
        die(json_encode(array('success' => $success, 'error' => implode("\n", get_messages("COMMENT", implode(",", $err))))));
    }

	die(json_encode(array('success' => $success)));
}

?>

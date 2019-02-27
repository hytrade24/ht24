<?php

$action = $ar_params[1];
if (!empty($action)) {
	require_once $ab_path."sys/lib.comment.php";
	$commentManagement = CommentManagement::getInstance($db);

	switch ($action) {
		case 'confirm':
			$id_comment = (int)$ar_params[2];
			$code = $ar_params[3];
			if ($commentManagement->confirmComment($id_comment, $code)) {
				die(forward( CommentManagement::getCommentTargetLink($db, $id_comment)."?success=1" ));
			}
			break;
	}
}

require_once $ab_path."sys/lib.comment.php";
if (!empty($_POST)) {
	$commentManagement = CommentManagement::getInstance($db, $_POST['TABLE']);

	$err = array();
	// Build title of commented item
	$title = CommentManagement::getTargetTitleByFkStr($db, $_POST['TABLE'], $_POST['FK_STR']);
	// Build comment assoc array
	if ($uid > 0) {
		// Registered user
		if (strlen(trim($_POST['comment'])) < 10) {
			$err[] = "COMMENT_TOO_SHORT";
		}
        if (($_POST["RATING"] <= 0) || ($_POST["RATING"] > 5)) {
            $rating = NULL;
            $err[] = 'NOT_RATED';
        } else {
            $rating = (int)$_POST["RATING"];
            $articleRated = $db->fetch_atom("SELECT count(*) FROM `comment` WHERE `TABLE`='".mysql_real_escape_string($_POST["TABLE"])."' AND FK_STR='".mysql_real_escape_string($_POST["FK_STR"])."'
                AND RATING IS NOT NULL AND FK_USER=".(int)$uid);
            if ($articleRated > 0) {
                $err[] = 'ALREADY_RATED';
            }
        }
		if (empty($err)) {
			$id_comment = $commentManagement->addCommentStr($_POST["FK_STR"], $title, trim($_POST['comment']), $uid, $rating);
			if ($id_comment > 0) {
				$ar_comment = $commentManagement->fetchOneByParams(array("ID_COMMENT" => $id_comment));
				if ($ar_comment["IS_CONFIRMED"]) {
					$tpl_content->addvar("confirmed", 1);
				}
				if ($ar_comment["IS_PUBLIC"]) {
					$tpl_content->addvar("online", 1);
				}
			}
		}
	} else {
		// Guest user
		if (strlen($_POST['name']) < 2) {
			$err[] = "NAME_TOO_SHORT";
		}
		if (!validate_email($_POST['email'])) {
			$err[] = "EMAIL_INVALID";
		}
		if (strlen(trim($_POST['comment'])) < 10) {
			$err[] = "COMMENT_TOO_SHORT";
		}
        if (($_POST["RATING"] <= 0) || ($_POST["RATING"] > 5)) {
            $rating = NULL;
            $err[] = 'NOT_RATED';
        } else {
            $rating = ($nar_systemsettings['MARKTPLATZ']['ANONYMOUS_RATINGS'] ? (int)$_POST["RATING"] : null);
            $articleRated = $db->fetch_atom("SELECT count(*) FROM `comment` WHERE `TABLE`='".mysql_real_escape_string($_POST["TABLE"])."' AND FK_STR='".mysql_real_escape_string($_POST["FK_STR"])."'
                AND RATING IS NOT NULL AND USER_MAIL='".mysql_real_escape_string($_POST['email'])."'");
            if ($articleRated > 0) {
                $err[] = 'ALREADY_RATED';
            }
        }
        if (!secure_question($_POST)) {
            $err[] = 'secQuestion';
        }
		if (empty($err)) {
			$id_comment = $commentManagement->addCommentAnonymousStr($_POST["FK_STR"], $title, trim($_POST['comment']), $_POST['name'], $_POST['email'], $rating);
			if ($id_comment > 0) {
				$ar_comment = $commentManagement->fetchOneByParams(array("ID_COMMENT" => $id_comment));
				if ($ar_comment["IS_CONFIRMED"]) {
					$tpl_content->addvar("confirmed", 1);
				}
				if ($ar_comment["IS_PUBLIC"]) {
					$tpl_content->addvar("online", 1);
				}
			}
		}
	}

	if (!empty($err)) {
		$tpl_content->addvar("err", get_messages("COMMENT", implode(",", $err)));
		$tpl_content->addvars($_POST);
	} else {
		$tpl_content->addvar("TABLE", $_POST["TABLE"]);
		$tpl_content->addvar("FK_STR", $_POST["FK_STR"]);
		$tpl_content->addvar("success", 1);
	}
} else {
    $commentManagement = CommentManagement::getInstance($db, $tpl_content->vars['TABLE']);
	if ($_REQUEST['success']) {
		$tpl_content->addvar("confirmed", 1);
        $tpl_content->addvar("online", 1);
		$tpl_content->addvar("success", 1);
	}
}

$tpl_content->addvar("SHOW_RATING_ANONYMOUS", $nar_systemsettings['MARKTPLATZ']['ANONYMOUS_RATINGS']);
$tpl_content->addvar("SHOW_RATING", $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_RATED'] && $tpl_content->vars['SHOW_RATING']);
$already_rated = false;
if ($uid > 0) {
    $result = $db->fetch1("SELECT MIN(ID_COMMENT) AS ID_COMMENT, count(*) AS CNT FROM `comment` WHERE `TABLE`='".mysql_real_escape_string($tpl_content->vars["TABLE"])."' AND FK_STR='".mysql_real_escape_string($tpl_content->vars["FK_STR"])."'
					AND RATING IS NOT NULL AND FK_USER=".(int)$uid);
    if ($result["CNT"] > 0) {
        $already_rated = true;
        $tpl_content->addvar("ALREADY_RATED", $already_rated);
        $tpl_content->addvar("ID_COMMENT", $result["ID_COMMENT"]);
    }
}
$tpl_content->addvar("ALREADY_RATED", $already_rated);

?>
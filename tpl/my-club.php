<?php
/* ###VERSIONSBLOCKINLCUDE### */

function comments(&$row, $i)
{
	global $db;
	require_once $ab_path."sys/lib.comment.php";

	$cm = CommentManagement::getInstance($db, 'club');
	$row['COMMENTS'] = $cm->getCommentCount($row['ID_CLUB']);
	$tmpComment = $cm->fetchOneByParams(array('FK' => $row['ID_CLUB']));
	$row['COMMENT_STAMP'] = $tmpComment['STAMP'];
}

require_once $ab_path."sys/lib.club.php";
require_once $ab_path."sys/lib.club.category.php";

$clubManagement = ClubManagement::getInstance($db);
$clubCategoryManagement = ClubCategoryManagement::getInstance($db);
$clubId = (isset($ar_params[1]) ? $ar_params[1] : 0);
$clubAction = (isset($ar_params[2]) ? $ar_params[2] : "list");
$action = (isset($_POST['action']) ? $_POST['action'] : null);
$sort_by = ($ar_params[4] ? $ar_params[4] : "MODERATOR");
$sort_dir = ($ar_params[5] ? $ar_params[5] : "DESC");
$search = (($_REQUEST['search'] && $ar_params[2] == "list") ? $_REQUEST['search'] : null);
$ar_club = array();
$err = array();

if ($action == "toggleComments") {

	$ar_result = array('success' => false);
	$id_club = (int)$_POST["idClub"];
	$ar_club = $db->fetch1("SELECT FK_USER, ALLOW_COMMENTS FROM `club` WHERE ID_CLUB=".$id_club);
	if ($uid == $ar_club["FK_USER"]) {
		// Get new state (toggle)
		$state = ($ar_club["ALLOW_COMMENTS"] > 0 ? 0 : 1);
		$db->querynow("UPDATE `club` SET ALLOW_COMMENTS=".$state." WHERE ID_CLUB=".$id_club);
		$ar_result["success"] = true;
		$ar_result["enabled"] = $state;
	}
	header('Content-type: application/json');
	die(json_encode($ar_result));
}

$perpage = 20;
$npage = ((int)$ar_params[3] ? $ar_params[3] : 1);
$limit = ($perpage*$npage)-$perpage;

if (!empty($_POST)) {
	// Form abgeschickt
	$clubId = $_POST["ID_CLUB"];
	$clubAction = $_POST["do"];
	if (isset($_POST["delete"])) {
		// Club auflösen!!
		if ($clubManagement->deleteClub($clubId)) {
			$url = $tpl_content->tpl_uri_action("my-club");
			die(forward($url));
		} else {
			$err[] = "RIGHTS_DELETE";
		}
	}
	if (isset($_POST["leave"])) {
		// Club verlassen!!
		if ($clubManagement->leaveClub($clubId)) {
			$url = $tpl_content->tpl_uri_action("my-club");
			die(forward($url));
		} else {
			$err[] = "RIGHTS_DELETE";
		}
	}
}

switch ($clubAction) {
	case 'accept':
		if ($clubManagement->acceptInvite($clubId, $uid)) {
			$url = $tpl_content->tpl_uri_action("my-club,".$clubId.",view");
			die(forward($url));
		} else {
			$userClubs = $clubManagement->getClubsByUser($uid);
			if (empty($userClubs)) {
				$err[] = "NOT_FOUND";
				break;
			} else {
				$err[] = "LIMIT_ONE";
				break;
			}
		}
	case 'saved':
		$tpl_content->addvar("saved", 1);
	case 'view':
		if ($clubId > 0 && $clubManagement->isMember($clubId, $uid)) {

			$ar_club = $clubManagement->getClubById($clubId);
		}
		$ar_club["ADMIN"] = ($ar_club["FK_USER"] == $uid);
		$ar_club["MODERATOR"] = ($ar_club["ADMIN"] || $clubManagement->isClubModerator($clubId, $uid));

        // Kategorien --- ACHTUNG, AUCH UNTER "new" !!
        $preSelectedNodes = array();
        if (empty($_POST)) {
            $selectedCategories = $clubCategoryManagement->fetchAllClubCategoriesByClubId($clubId);
            foreach($selectedCategories as $key => $selectedCategory) {
                $preSelectedNodes[] = $selectedCategory['FK_KAT'];
            }
        } else if (!empty($_POST["CATEGORIES"])) {
            $preSelectedNodes = explode(",", $_POST["CATEGORIES"]);
        }

        $categoryJSONTree = $clubCategoryManagement->getClubCategoryJSONTree($preSelectedNodes);
        $tpl_content->addvar("CATEGORY_JSON_TREE", $categoryJSONTree);
        $tpl_content->addvar("CATEGORY_TREE_MAX_SELECTS", ClubCategoryManagement::MAX_CATEGORY_PER_USER);
        // Kategorien --- ACHTUNG, AUCH UNTER "new" !!
        break;
	case 'decline':
		if ($clubManagement->declineInvite($clubId, $uid)) {
			$url = $tpl_content->tpl_uri_action("my-pages");
			die(forward($url));
		} else {
			$err[] = "NOT_FOUND";
			break;
		}
		break;
	case 'new':
		$usersettings = $db->fetch1("SELECT ALLOW_COMMENTS FROM `usersettings` WHERE FK_USER=".(int)$uid);
		unset($ar_club["ID_CLUB"]);
		$ar_club["NEW"] = 1;
		$ar_club["ADMIN"] = 1;
		$ar_club["MODERATOR"] = 1;
		// Set default options
		$ar_club['ALLOW_COMMENTS'] = (($usersettings["ALLOW_COMMENTS"] & 4) == 4 ? 1 : 0);

        // Kategorien --- ACHTUNG, AUCH UNTER "view" !!
        $preSelectedNodes = array();
        if (empty($_POST)) {
            $selectedCategories = $clubCategoryManagement->fetchAllClubCategoriesByClubId($clubId);
            foreach($selectedCategories as $key => $selectedCategory) {
                $preSelectedNodes[] = $selectedCategory['FK_KAT'];
            }
        } else if (!empty($_POST["CATEGORIES"])) {
            $preSelectedNodes = explode(",", $_POST["CATEGORIES"]);
        }

        $categoryJSONTree = $clubCategoryManagement->getClubCategoryJSONTree($preSelectedNodes);
        $tpl_content->addvar("CATEGORY_JSON_TREE", $categoryJSONTree);
        $tpl_content->addvar("CATEGORY_TREE_MAX_SELECTS", ClubCategoryManagement::MAX_CATEGORY_PER_USER);
        // Kategorien --- ACHTUNG, AUCH UNTER "view" !!
		break;
	case 'list':
		$all = 0;

        $possibleSort = array(
            'MODERATOR' => array(
                'IS_ADMIN' => $sort_dir,
                'IS_MODERATOR' => $sort_dir,
            ),
            'STAMP_JOIN' => array(
                'c2u.STAMP_JOIN' => $sort_dir,
            ),
            'NAME' => array(
                'c.NAME' => $sort_dir,
            ),
            'MEMBERS' => array(
                'MEMBERS' => $sort_dir,
            ),
            'COMMENTS' => array(
                'COUNT_COMMENTS' => $sort_dir,
            ),
            'EVENTS' => array(
                'COUNT_EVENTS' => $sort_dir,
            ),
            'GALLERY' => array(
                'COUNT_GALLERY' => $sort_dir,
            ),
            'REQUESTS' => array(
                'MEMBER_REQUEST' => $sort_dir,
            ),
            'ALLOW_COMMENTS' => array(
                'c.ALLOW_COMMENTS' => $sort_dir,
            ),
        );
        if(array_key_exists($sort_by, $possibleSort) && in_array($sort_dir, array('ASC', 'DESC'))) {
            $tpl_content->addvar(strtoupper("SORT_BY_".$sort_by."_".$sort_dir), true);
        }

        if ($search != null) {
            $clubIds = $clubManagement->getUserClubIds($userId, $all);

            if (!empty($clubIds)) {
                $arr = array(
                    'ID_CLUB' => $clubIds,
                    'FK_USER_PERMISSION' => $userId,
                    'SEARCHCLUB' => $search,
                );
            }
            else {
                $arr = array('FK_USER' => $userId, 'FK_USER_PERMISSION' => $userId);
            }

            $liste = $clubManagement->getClubsByParameters($arr, 128, $npage, $perpage, $all, $possibleSort[$sort_by]);
        }else {
            $liste = $clubManagement->getClubsByUser($uid, 128, $npage, $perpage, $all, $possibleSort[$sort_by]);
        }

		if (!empty($liste)) {
			$tpl_content->addlist("liste", $liste, "tpl/".$s_lang."/my-club.row.htm", "comments");
            $tpl_content->addvar("search", $search);
            $tpl_content->addvar("npage", $npage);
		}
        else if (empty($liste) && $search) {
            $tpl_content->addvar("search", $search);
        }

        $tpl_content->addvar('ALL', $all);
		$tpl_content->addvar("pager", htm_browse_extended($all, $npage, "my-club," . $clubId . "," . $clubAction . ",{PAGE},".$sort_by.",".$sort_dir."", $perpage, 5,($search != null ? "?search=".$search : "")));
		break;
	case 'save':
	    if(isset($_FILES) && $_FILES['LOGO']['tmp_name'] != "") {
	    	// Logo uploaded
	        $galleryFilename = md5_file($_FILES['LOGO']['tmp_name']).'_'.$_FILES['LOGO']['name'];
	        $galleryFile = $ab_path.'cache/club/logo/'.$galleryFilename;

	        move_uploaded_file($_FILES['LOGO']['tmp_name'], $galleryFile);
	        chmod($galleryFile, 0777);

	        $_POST['LOGO'] = $galleryFilename;
	    }
	    if(isset($_POST['DELETE_LOGO']) && $_POST['DELETE_LOGO'] == 1) {
	        $_POST['LOGO'] = "";
	    }
        if(!isset($_POST['ALLOW_COMMENTS'])) {
            $_POST['ALLOW_COMMENTS'] = 0;
        }
        if(!isset($_POST['FORUM_ENABLED'])) {
            $_POST['FORUM_ENABLED'] = 0;
        } else {
            // Only allow changes if forum is enabled
            if(!isset($_POST['FORUM_PUBLIC'])) {
                $_POST['FORUM_PUBLIC'] = 0;
            }
            if(!isset($_POST['FORUM_MODERATED'])) {
                $_POST['FORUM_MODERATED'] = 0;
            }
        }
        if(!isset($_POST['STATUS'])) {
            $_POST['STATUS'] = 0;
        }
		if ($clubId > 0) {
			if($clubManagement->isClubModerator($clubId, $uid) || $clubManagement->isClubOwner($clubId, $uid)) {
				$ar_club = array_merge($clubManagement->getClubById($clubId), $_POST);
				if ($clubManagement->updateCheckFields($ar_club, $err)) {
					$clubManagement->update($ar_club, $langval);
                    $clubCategoryManagement->addClubCategories(explode(',', $_POST['CATEGORIES']), $clubId);
				}
			}
		} else {
			$ar_club = $_POST;
			if ($clubManagement->updateCheckFields($ar_club, $err)) {
				$clubId = $clubManagement->update($ar_club, $langval);
                $clubCategoryManagement->addClubCategories(explode(',', $_POST['CATEGORIES']), $clubId);
			}
		}
		if (empty($err)) {
			// Success
			$url = $tpl_content->tpl_uri_action("my-club,".$clubId.",saved");
			die(forward($url));
		}
		// Error!
		$ar_club['NEW'] = ($clubId > 0 ? 0 : 1);
		if ($clubId > 0) {
			$ar_club["ADMIN"] = ($ar_club["FK_USER"] == $uid);
			$ar_club["MODERATOR"] = ($ar_club["ADMIN"] || $clubManagement->isClubModerator($clubId, $uid));
		} else {
			$ar_club["ADMIN"] = 1;
			$ar_club["MODERATOR"] = 1;
		}
		break;
}

$ar_club['LOGO'] = ($ar_club['LOGO'] != "" ? 'cache/club/logo/'.$ar_club['LOGO'] : null);

$tpl_content->addvars($ar_club, "CLUB_");
if (!empty($err)) {
	// Error!
	$htm_errors = "<li>".implode("</li>\n<li>", get_messages("CLUB", implode(",", $err)))."</li>";
	$tpl_content->addvar("errors", $htm_errors);
}

$tpl_content->addvar("ALLOW_COMMENTS_AD", $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_AD']);


/* Club-Einladungen */
$liste = $db->fetch_table("
	SELECT
		c.NAME as CLUB_NAME, ci.*, u.*
	FROM
		`club_invite` ci
	LEFT JOIN
		`club` c ON ci.FK_CLUB=c.ID_CLUB
	LEFT JOIN
		`user` u ON ci.FK_USER=u.ID_USER
	WHERE
		u.ID_USER=".(int)$uid);
if(count($liste)) {
    $tpl_content->addlist("liste_club_invites", $liste, "tpl/".$s_lang."/my-pages.club_invites.htm");
}

?>

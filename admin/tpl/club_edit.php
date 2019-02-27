<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.club.php";

$clubManagement = ClubManagement::getInstance($db);
$clubId = $_REQUEST['id'];
$clubAction = (isset($_REQUEST['ajax']) ? $_REQUEST['ajax'] : $_REQUEST['do']);
$ar_club = array();
$err = array();

switch ($clubAction) {
	case 'lock':
		if ($clubManagement->lock($clubId)) {
			die(forward("index.php?lang=de&page=clubs&locked=".$clubId));
		} else {
			$err[] = "NOT_FOUND";
			break;
		}
	case 'unlock':
		if ($clubManagement->unlock($clubId)) {
			die(forward("index.php?lang=de&page=clubs&unlocked=".$clubId));
		} else {
			$err[] = "NOT_FOUND";
			break;
		}
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
		if ($clubId > 0) {
			$ar_club = array_merge($clubManagement->getClubById($clubId), $_POST);
			if ($clubManagement->updateCheckFields($ar_club, $err)) {
				$clubManagement->update($ar_club, $langval);
			}
		} else {
			$ar_club = $_POST;
			if ($clubManagement->updateCheckFields($ar_club, $err)) {
				$clubId = $clubManagement->update($ar_club, $langval);
			}
		}
		if (empty($err)) {
			// Success
			die(forward("index.php?lang=de&page=club_edit&id=".$clubId));
		}
		// Error!
		$ar_club["NEW"] = ($clubId > 0 ? 0 : 1);
		$ar_club["EDITABLE"] = 1;
		break;
	default:
		if ($clubId > 0) {
			$ar_club = $clubManagement->getClubById($clubId);
		}
		$ar_club["NEW"] = 0;
		$ar_club["EDITABLE"] = 1;
		break;
}

$ar_club['LOGO'] = ($ar_club['LOGO'] != "" ? 'cache/club/logo/'.$ar_club['LOGO'] : null);

$tpl_content->addvars($ar_club, "CLUB_");
if (!empty($err)) {
	// Error!
	$htm_errors = "<li>".implode("</li>\n<li>", get_messages("CLUB", implode(",", $err)))."</li>";
	$tpl_content->addvar("errors", $htm_errors);
}

?>
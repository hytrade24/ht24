<?php
/* ###VERSIONSBLOCKINLCUDE### */

if (isset($_REQUEST['is_user_media'])) {
	include $ab_path."tpl/my-user-media.php";
	return;	// Nothing to do here!
}

require_once $ab_path."sys/lib.calendar_event.php";
require_once $ab_path."sys/lib.calendar_event.gallery.php";
require_once $ab_path."sys/lib.club.php";
require_once $ab_path."sys/lib.youtube.php";

$calendarEventManagement = CalendarEventManagement::getInstance($db);
$calendarEventGalleryManagement = CalendarEventGalleryManagement::getInstance($db);
$clubManagement = ClubManagement::getInstance($db);

// Sprachrelevante Felder
$languageSelection = $db->fetch_table("SELECT * FROM lang WHERE B_PUBLIC = 1");
$languageByBitval = array();
foreach ($languageSelection as $indexLang => $ar_lang) {
	$languageByBitval[ $ar_lang["BITVAL"] ] = $ar_lang["ABBR"];
}

$searchParameter = array();
$preselectedNodes = array();

$calendarEventId = $_REQUEST['ID_CALENDAR_EVENT']?(int)$_REQUEST['ID_CALENDAR_EVENT']:(int)$ar_params[1];
$calendarEventType = $_REQUEST['FK_REF_TYPE']?$_REQUEST['FK_REF_TYPE']:$ar_params[2];
$calendarEventRef = $_REQUEST['FK_REF']?(int)$_REQUEST['FK_REF']:(int)$ar_params[3];
$calendarEvent = $calendarEventManagement->fetchById($calendarEventId, $uid);

if (count($ar_params) > 2) {
	$arTypeMapping = array(
		"club"		=> "club",
		"group"		=> "club",
		"user"		=> "user",
		"vendor"	=> "user"
	);
	if (in_array($calendarEventType, array_keys($arTypeMapping))) {
		$tpl_content->addvar("TYPE_SET", 1);
		$tpl_content->addvar("FK_REF_TYPE", $arTypeMapping[$calendarEventType]);
		$tpl_content->addvar("FK_REF", $calendarEventRef);
	}
}

$calendarEventSessionId = $_REQUEST['CALENDAR_EVENT_SESSION_ID'];
if ( empty($_REQUEST['CALENDAR_EVENT_SESSION_ID']) || !array_key_exists($calendarEventSessionId, $_SESSION['CALENDAR_EVENT']) ) {
	if (empty($_REQUEST['CALENDAR_EVENT_SESSION_ID'])) {
		$calendarEventSessionId = md5(microtime(true));
	}
	$_SESSION['CALENDAR_EVENT'] = array();
	$_SESSION['CALENDAR_EVENT'][$calendarEventSessionId] = array(
		'ID_CALENDAR_EVENT' => $calendarEventId
	);

	if($calendarEventId != null && $calendarEvent != null) {
		$preselectedNodes = array($calendarEvent['FK_KAT']);
		$calendarEvent['STAMP_START_DATE'] = date("d.m.Y", strtotime($calendarEvent['STAMP_START']));
		$calendarEvent['STAMP_START_TIME'] = date("H:i", strtotime($calendarEvent['STAMP_START']));
		$calendarEvent['STAMP_END_DATE'] = date("d.m.Y", strtotime($calendarEvent['STAMP_END']));
		$calendarEvent['STAMP_END_TIME'] = date("H:i", strtotime($calendarEvent['STAMP_END']));
		$_SESSION['CALENDAR_EVENT'][$calendarEventSessionId] = $calendarEvent;

		// Add images
		$calendarEventImages = $calendarEventGalleryManagement->fetchAllByCalendarEventId($calendarEventId);
		foreach($calendarEventImages as $key => $calendarEventImage) {
			$_SESSION['CALENDAR_EVENT'][$calendarEventSessionId]['IMAGES'][$calendarEventImage['ID_CALENDAR_EVENT_GALLERY']] = array(
				'ID_IMAGE'		=> $calendarEventImage['ID_CALENDAR_EVENT_GALLERY'],
				'FILENAME'		=> $calendarEventImage['FILENAME'],
				'FILE'			=> $ab_path.'cache/event/'.$calendarEventImage['FILENAME'],
				'FILE_RELATIVE' =>  '/cache/event/'.$calendarEventImage['FILENAME'],
				'DIRTY'			=> false
			);

		}
		// Add videos
		$calendarEventVideos = $calendarEventGalleryManagement->fetchAllVideosByCalendarEventId($calendarEventId);
		foreach($calendarEventVideos as $key => $calendarEventVideo) {
			$_SESSION['CALENDAR_EVENT'][$calendarEventSessionId]['VIDEOS'][$calendarEventVideo['ID_CALENDAR_EVENT_GALLERY_VIDEO']] = array(
				'ID_VIDEO'		=> $calendarEventVideo['ID_CALENDAR_EVENT_GALLERY_VIDEO'],
				'CODE' 			=> $calendarEventVideo['YOUTUBEID'],
				'DIRTY'			=> false
			);

		}
		// Add searchwords
		$_SESSION['CALENDAR_EVENT'][$calendarEventSessionId]['SEARCHWORDS'] = array();
		foreach ($languageSelection as $langIndex => $ar_lang) {
			$calendarEventSearchwords = $calendarEventManagement->fetchAllSearchwordsByCalendarEventId($calendarEventId, $ar_lang["ABBR"]);
			$_SESSION['CALENDAR_EVENT'][$calendarEventSessionId]['SEARCHWORDS'] = array_merge($_SESSION['CALENDAR_EVENT'][$calendarEventSessionId]['SEARCHWORDS'], $calendarEventSearchwords);
		}
	} else if ($calendarEventId === 0) {
		$usersettings = $db->fetch1("SELECT ALLOW_COMMENTS FROM `usersettings` WHERE FK_USER=".(int)$uid);
		// Set default options
		$_SESSION['CALENDAR_EVENT'][$calendarEventSessionId]['ALLOW_COMMENTS'] = (($usersettings["ALLOW_COMMENTS"] & 2) == 2 ? 1 : 0);
		// Default times
		$_SESSION['CALENDAR_EVENT'][$calendarEventSessionId]['STAMP_START_TIME'] = "12:00";
		$_SESSION['CALENDAR_EVENT'][$calendarEventSessionId]['STAMP_END_TIME'] = "12:00";
	} else {
		$tpl_content->addvar("not_found", 1);
		return;
	}
}

$calendarEventSession = $_SESSION['CALENDAR_EVENT'][$calendarEventSessionId];
$calendarEventId = $calendarEventSession['ID_CALENDAR_EVENT'];


if(isset($_REQUEST['DO'])) {
	$data = array();

	if($_REQUEST['DO'] == "toggle_comments") {
		$enabled = 1 - (int)$calendarEventSession["ALLOW_COMMENTS"];
		$db->querynow("UPDATE `calendar_event` SET ALLOW_COMMENTS=".$enabled." WHERE ID_CALENDAR_EVENT=".$calendarEventId);
		header("Content-Type: application/json");
		die(json_encode(array('success' => true, 'enabled' => $enabled)));
	} elseif($_REQUEST['DO'] == "save_type") {
		$calendarEventSession['FK_REF_TYPE'] = $_POST['FK_REF_TYPE'];


		if($calendarEventSession['FK_REF_TYPE'] == 'user') {
			$calendarEventSession['FK_REF'] = $uid;
		} elseif($calendarEventSession['FK_REF_TYPE'] == 'club') {
			if($clubManagement->isMember((int)$_POST['FK_REF'], $uid)) {
				$calendarEventSession['FK_REF'] = $_POST['FK_REF'];
				$data['IS_MODERATOR'] = ($clubManagement->isClubModerator((int)$_POST['FK_REF'], $uid) || $clubManagement->isClubOwner((int)$_POST['FK_REF'], $uid));
			}
		} else {
			// Error
			header("Content-Type: application/json");
			die(json_encode(array('success' => false)));
		}

	} elseif($_REQUEST['DO'] == "save_kat") {
	    if ($_POST['FK_KAT']) {
				$calendarEventSession['FK_KAT'] = $_POST['FK_KAT'];
	    } else {
				// Error
				header("Content-Type: application/json");
				die(json_encode(array('success' => false)));
	    }
	} elseif($_REQUEST['DO'] == "save_description") {
		$location_required = false;
		$errors = array();
		if (strlen(trim($_POST['TITLE'])) > 3) {
			$calendarEventSession['TITLE'] = $_POST['TITLE'];
		} else {
			$errors[] = "TITLE";
		}
		$stampNowUnix = time();
		$stampStartUnix = 0;
		$stampEndUnix = 0;
		if (preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})/", $_POST['STAMP_START_DATE'], $ar_date)
			&& preg_match("/[0-9]{1,2}:[0-9]{1,2}/", $_POST['STAMP_START_TIME'])) {
			$stampStartUnix = strtotime($ar_date[1].'-'.$ar_date[2].'-'.$ar_date[3].' '.$_POST['STAMP_START_TIME']);
			$calendarEventSession['STAMP_START'] = date('Y-m-d H:i:s', $stampStartUnix);
		} else {
			$errors[] = "STAMP_START";
		}
		if (preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})/", $_POST['STAMP_END_DATE'], $ar_date)
			&& preg_match("/[0-9]{1,2}:[0-9]{1,2}/", $_POST['STAMP_END_TIME'])) {
			$stampEndUnix = strtotime($ar_date[1].'-'.$ar_date[2].'-'.$ar_date[3].' '.$_POST['STAMP_END_TIME']);
			$calendarEventSession['STAMP_END'] = date('Y-m-d H:i:s', $stampEndUnix);
		} else {
			$errors[] = "STAMP_END#INVALID";
		}
		if ($stampStartUnix > $stampEndUnix) {
			$errors[] = "STAMP_END#START_BEFORE_END";
		}
		$calendarEventSession['LOCATION'] = $_POST['LOCATION'];
		$calendarEventSession['STREET'] = $_POST['STREET'];
		$calendarEventSession['ZIP'] = $_POST['ZIP'];
		$calendarEventSession['CITY'] = $_POST['CITY'];
		$calendarEventSession['FK_COUNTRY'] = $_POST['FK_COUNTRY'];
		$calendarEventSession['PRIVACY'] = $_POST['PRIVACY'];
		$calendarEventSession['ALLOW_COMMENTS'] = ($_POST['ALLOW_COMMENTS'] > 0 ? 1 : 0);
		$calendarEventSession['IS_AUTO_CONFIRMED'] = ($_POST['IS_CONFIRMED'] > 0 ? 1 : 0);
		if (!empty($_POST['DESCRIPTION'])) {
			$calendarEventSession['DESCRIPTION'] = $_POST['DESCRIPTION'];
		} else {
			$errors[] = "DESCRIPTION";
		}

		$geoCoordinates = NULL;
		if ((!empty($_POST['LOCATION']) || !empty($_POST['STREET']))
			&& (!empty($_POST['ZIP']) || !empty($_POST['CITY']))) {
            $location = $_POST['STREET'];
            if (!empty($_POST['LOCATION'])) {
                $location = (empty($location) ? $_POST['LOCATION'] : $_POST['LOCATION'].", ".$location);
            }
            $land = $db->fetch_atom("SELECT V1 FROM string
                WHERE S_TABLE='country' AND BF_LANG=".$langval." AND FK=".(int)$_POST["FK_COUNTRY"]);
            $geoCoordinates = Geolocation_Generic::getGeolocationCached($location, $_POST['ZIP'], $_POST['CITY'], $land);
			if ($geoCoordinates === null) {
				// No results found, try without location.
				$geoCoordinates = Geolocation_Generic::getGeolocationCached($_POST['STREET'], $_POST['ZIP'], $_POST['CITY'], $land);
			}
            if (($geoCoordinates != false) && ($geoCoordinates != NULL)) {
                $calendarEventSession['LONGITUDE'] = $geoCoordinates['LONGITUDE'];
                $calendarEventSession['LATITUDE'] = $geoCoordinates['LATITUDE'];
            }
		}
		if ($location_required && ($geoCoordinates == NULL)) {
			$errors[] = "LOCATION";
			$errors[] = "STREET";
			$errors[] = "ZIP_CITY";
		}
		if (!empty($errors)) {
			// An error occured
            header("Content-Type: application/json");
            die(json_encode(array('success' => false, 'errors' => $errors)));
		}
	} elseif($_REQUEST['DO'] == "upload_image") {
		if(isset($_FILES) && $_FILES['UPLOAD_FILE']['tmp_name'] != "") {
			$galleryId = uniqid();
			$galleryFilename = md5_file($_FILES['UPLOAD_FILE']['tmp_name']).'_'.$_FILES['UPLOAD_FILE']['name'];
			$galleryFile = $ab_path.'cache/event/'.$galleryFilename;
			$galleryFileRelative = '/cache/event/'.$galleryFilename;

			move_uploaded_file($_FILES['UPLOAD_FILE']['tmp_name'], $galleryFile);
			chmod($galleryFile, 0777);

			$calendarEventSession['IMAGES'][$galleryId] = array(
				'ID_IMAGE' => $galleryId,
				'FILENAME' => $galleryFilename,
				'FILE' => $galleryFile,
				'FILE_RELATIVE' => $galleryFileRelative,
				'DIRTY' => true
			);

			$data = array(
				'filename' => $galleryFilename,
				'file' => $galleryFile
			);
		}
	} elseif($_REQUEST['DO'] == "upload_video") {
		$videos_max = 5;
		$videos_left = $videos_max - count($calendarEventSession['VIDEOS']);
		if (isset($_REQUEST["VIDEO_URL"]) && ($videos_left > 0)) {
			/*
			 * Video-Upload
			 */
			$url = $_REQUEST["VIDEO_URL"];
			$code = Youtube::ExtractCodeFromURL($url);
			if ($code != false && !in_array($code, array_column($calendarEventSession['VIDEOS'], "CODE"))) {
				$videoId = uniqid();
				$calendarEventSession['VIDEOS'][$videoId] = array(
					"ID_VIDEO"			=> $videoId,
					"CODE"	      		=> $code,
					"DIRTY"				=> true
				);
			} else {
				$error[] = "UPLOAD_VIDEO_FAILED_ANALYSE";
			}
			if (!empty($error)) {
				// An error occured
	            header("Content-Type: application/json");
	            die(json_encode(array('success' => false, 'errors' => $errors)));
			}
		}
		if(isset($_FILES) && $_FILES['UPLOAD_FILE']['tmp_name'] != "") {
			$galleryId = uniqid();
			$galleryFilename = md5_file($_FILES['UPLOAD_FILE']['tmp_name']).'_'.$_FILES['UPLOAD_FILE']['name'];
			$galleryFile = $ab_path.'cache/event/'.$galleryFilename;
			$galleryFileRelative = '/cache/event/'.$galleryFilename;

			move_uploaded_file($_FILES['UPLOAD_FILE']['tmp_name'], $galleryFile);
			chmod($galleryFile, 0777);

			$calendarEventSession['IMAGES'][$galleryId] = array(
				'ID_IMAGE' => $galleryId,
				'FILENAME' => $galleryFilename,
				'FILE' => $galleryFile,
				'FILE_RELATIVE' => $galleryFileRelative,
				'DIRTY' => true
			);

			$data = array(
				'filename' => $galleryFilename,
				'file' => $galleryFile
			);
		}
	} elseif($_REQUEST['DO'] == "add_searchword") {
		if (!empty($_REQUEST['WORD']) && ((int)$_REQUEST['LANG'] > 0)) {
			if (!is_array($calendarEventSession['SEARCHWORDS'])) {
				$calendarEventSession['SEARCHWORDS'] == array();
			}
			$language = (int)$_REQUEST['LANG'];
			$calendarEventSession['SEARCHWORDS'][] = array(
				"wort"		=> $_REQUEST['WORD'],
				"LANG"		=> $language,
				"S_LANG"	=> $languageByBitval[ $language ],
				"DIRTY"		=> true
			);
		} else {
			// Error
			header("Content-Type: application/json");
			die(json_encode(array('success' => false)));
		}
	} elseif($_REQUEST['DO'] == "del_searchword") {
		if (!empty($_REQUEST['SEARCHWORD']) && ((int)$_REQUEST['LANG'] > 0)) {
			if (!is_array($calendarEventSession['SEARCHWORDS'])) {
				$calendarEventSession['SEARCHWORDS'] == array();
			}
			foreach ($calendarEventSession['SEARCHWORDS'] as $index => $ar_searchword) {
				if (($ar_searchword['wort'] == $_REQUEST['SEARCHWORD'])
					&& ($ar_searchword['S_LANG'] == $languageByBitval[ $langval ])) {
					if (!$ar_searchword['DIRTY']) {
						// Remove from DB
						$calendarEventManagement->deleteCalendarEventSearchWordByCalendarEventId($ar_searchword['wort'], $calendarEventId, $ar_searchword['S_LANG']);
					}
					unset($calendarEventSession['SEARCHWORDS'][$index]);
				}
			}
		} else {
			// Error
			header("Content-Type: application/json");
			die(json_encode(array('success' => false)));
		}
	} elseif($_REQUEST['DO'] == "fetch_searchwords") {
		$langval = (int)$_REQUEST['LANG'];
		if ($langval > 0) {
			// Zeige die Schlagworte
			$calendarEventSearchWords = array();
			if (is_array($calendarEventSession['SEARCHWORDS'])) {
				foreach ($calendarEventSession['SEARCHWORDS'] as $index => $ar_searchword) {
					if ($ar_searchword['S_LANG'] == $languageByBitval[ $langval ]) {
						$calendarEventSearchWords[] = $ar_searchword;
					}
				}
			}
			$tpl_content = new Template($ab_path."tpl/".$s_lang."/my-calendar-events-searchword.htm");
			$tpl_content->addvar(($calendarEventId > 0 ? "EDIT" : "CREATE"), 1);
			$tpl_content->addvar("BITVAL", $langval);
			$tpl_content->addlist("searchwords", $calendarEventSearchWords, $ab_path.'tpl/'.$s_lang.'/my-calendar-events-searchword.row.htm');
			die( $tpl_content->process(true) );
		} else {
			// Error
			header("Content-Type: application/json");
			die(json_encode(array('success' => false)));
		}
	} elseif($_REQUEST['DO'] == "fetch_images") {
		$templateRow = new Template("tpl/".$s_lang."/empty.htm");
		$templateRow->tpl_text = '{liste}';
		$templateRow->addlist("liste", $calendarEventSession['IMAGES'], "tpl/".$s_lang."/my-calendar-events-add.images_row.htm");

		echo $templateRow->process(); die();
	} elseif($_REQUEST['DO'] == "fetch_videos") {
		$templateRow = new Template("tpl/".$s_lang."/empty.htm");
		$templateRow->tpl_text = '{liste}';
		$templateRow->addlist("liste", $calendarEventSession['VIDEOS'], "tpl/".$s_lang."/my-calendar-events-add.videos_row.htm");
		echo $templateRow->process(); die();
	} elseif($_REQUEST['DO'] == "delete_image" && $_REQUEST['ID_IMAGE']) {
		$id_image = $_REQUEST['ID_IMAGE'];
		$ar_image = $calendarEventSession['IMAGES'][$id_image];
		unset($calendarEventSession['IMAGES'][$id_image]);
		if (!$ar_image["DIRTY"]) {
			$db->querynow("DELETE FROM `calendar_event_gallery` WHERE ID_CALENDAR_EVENT_GALLERY=".$id_image);
		}
	} elseif($_REQUEST['DO'] == "delete_video" && $_REQUEST['ID_VIDEO']) {
		$id_video = $_REQUEST['ID_VIDEO'];
		$ar_video = $calendarEventSession['VIDEOS'][$id_video];
		unset($calendarEventSession['VIDEOS'][$id_video]);
		if (!$ar_video["DIRTY"]) {
			$db->querynow("DELETE FROM `calendar_event_gallery_video` WHERE ID_CALENDAR_EVENT_GALLERY_VIDEO=".$id_video);
		}
	}

	$_SESSION['CALENDAR_EVENT'][$calendarEventSessionId] = $calendarEventSession;

	header("Content-Type: application/json");
	die(json_encode(array('success' => true, 'data' => $data)));
} elseif (($_REQUEST['action'] == 'save') && ($_REQUEST["mode"] != "ajax")) {
	$emailToMods = false;
	$calendarEventData = $calendarEventSession;
	$calendarEventData['FK_USER'] = $uid;
	if ($calendarEventData['FK_REF_TYPE'] != "club") {
		$calendarEventData['PRIVACY'] = 1;
	}
	if($calendarEventSession['ID_CALENDAR_EVENT'] == NULL) {
		unset($calendarEventData['ID_CALENDAR_EVENT']);
		switch ($calendarEventData['FK_REF_TYPE']) {
			case "club":
                $calendarEventData['IS_CONFIRMED'] = 0;
                if ($calendarEventData['IS_AUTO_CONFIRMED'] == 1 &&
                        ($clubManagement->isClubOwner($calendarEventData['FK_REF'], $uid) ||
                         $clubManagement->isClubModerator($calendarEventData['FK_REF'], $uid))) {
                    $calendarEventData['IS_CONFIRMED'] = 1;
                }
				break;
			default:
				$calendarEventData['IS_CONFIRMED'] = 1;
				break;
		}
		$emailToMods = ($calendarEventData['IS_CONFIRMED'] == 0 ? true : false);
	}
    if ($nar_systemsettings['MARKTPLATZ']['MODERATE_EVENTS']) {
        $userIsAutoConfirmed = $db->fetch_atom("SELECT AUTOCONFIRM_EVENTS FROM `user` WHERE ID_USER=".$uid);
        $calendarEventData['MODERATED'] = ($userIsAutoConfirmed ? 1 : 0);
    } else {
        $calendarEventData['MODERATED'] = 1;
    }
	$calendarEventId = $db->update("calendar_event", $calendarEventData);
	// Move/save uploaded files
	require_once $ab_path."sys/lib.user_media.php";
	$userMedia = new UserMediaManagement($db, "calendar_event", $uid);
	$userMedia->save($calendarEventId, $_POST['META']);

	if(is_array($calendarEventData['SEARCHWORDS']) && count($calendarEventData['SEARCHWORDS']) >0) {
		foreach($calendarEventData['SEARCHWORDS'] as $language => $calendarEventSearchword) {
			if ($calendarEventSearchword["DIRTY"]) {
				$calendarEventManagement->addCalendarEventSearchWordByCalendarEventId($calendarEventSearchword['wort'], $calendarEventId, $calendarEventSearchword["S_LANG"]);
			}
		}
	}
	if ($emailToMods) {
		$emailVars = array();
		// Event data
		foreach ($calendarEventData as $key => $value) {
			$emailVars["EVENT_".$key] = $value;
		}
		switch ($calendarEventData['FK_REF_TYPE']) {
			case 'club':
				// Club/Group data
				$arClubData = $clubManagement->getClubById($calendarEventData['FK_REF']);
				foreach ($arClubData as $key => $value) {
					$emailVars["CLUB_".$key] = $value;
				}
				break;
			default:
				break;
		}
		$arClubModerators = $clubManagement->getClubModeratorsUserIds($calendarEventData['FK_REF']);
		foreach ($arClubModerators as $modIndex => $fk_user) {
			$emailVarsUser = $emailVars;
			$ar_mod = $db->fetch1("SELECT NAME, EMAIL FROM `user` WHERE ID_USER=".(int)$fk_user);
			// User data
			foreach ($ar_mod as $key => $value) {
				$emailVarsUser["USER_".$key] = $value;
			}
			sendMailTemplateToUser(0, $ar_mod["EMAIL"], "CLUB_EVENT_NEW_MOD", $emailVarsUser);
		}
	}
	$successUrl = (!empty($_SESSION["CALENDAR_EVENT_REFERER"]) ? $_SESSION["CALENDAR_EVENT_REFERER"] : $tpl_content->tpl_uri_action("my-events"));
	unset($_SESSION["CALENDAR_EVENT_REFERER"]);
	die(forward($successUrl));
}

if (!empty($_SERVER["HTTP_REFERER"])) {
	$_SESSION["CALENDAR_EVENT_REFERER"] = $_SERVER["HTTP_REFERER"];
}
// Zeige die Schlagworte
$ar_searchwords = $clubManagement->getSearchWordsByClubId($clubId, $langval);
// Clubs des Users
$clubs = $clubManagement->getClubsByUser($uid, $langval, 1, null);
//for ($i=count($clubs)-1; $i >= 0; $i--) {
//	if (!$clubs[$i]["IS_ADMIN"] && !$clubs[$i]["IS_MODERATOR"]) {
//		unset($clubs[$i]);
//	}
//}

$categoryJSONTree = $calendarEventManagement->getCalendarEventCategoryJSONTree($preselectedNodes);
$tpl_content->addvar(($calendarEventId > 0 ? "EDIT" : "CREATE"), 1);
$tpl_content->addvar("CATEGORY_JSON_TREE", $categoryJSONTree);
$tpl_content->addvar("CALENDAR_EVENT_SESSION_ID", $calendarEventSessionId);
$tpl_content->addlist("liste_clubs", $clubs, "tpl/".$s_lang."/my-calendar-events-add.clubs_row.htm");
$tpl_content->addlist("liste_images", $calendarEventSession["IMAGES"], "tpl/".$s_lang."/my-calendar-events-add.images_row.htm");
$tpl_content->addlist("liste_videos", $calendarEventSession["VIDEOS"], "tpl/".$s_lang."/my-calendar-events-add.videos_row.htm");
$tpl_content->addlist("searchWordLanguageHeader", $languageSelection, $ab_path.'tpl/'.$s_lang.'/my-calendar-events-searchword.lang.header.htm');
$tpl_content->addlist("searchWordLanguageBody", $languageSelection, $ab_path.'tpl/'.$s_lang.'/my-calendar-events-searchword.lang.body.htm');
$tpl_content->addvars($calendarEventSession);

if($calendarEventSession['FK_REF_TYPE']) {
	$tpl_content->addvar("FK_REF_TYPE_".strtoupper($calendarEventSession['FK_REF_TYPE']), 1);
}

<?php

require_once $ab_path."sys/lib.club.php";
require_once $ab_path."sys/lib.calendar_event.php";
require_once $ab_path."sys/lib.calendar_event.gallery.php";

$clubManagement = ClubManagement::getInstance($db);
$calendarEventManagement = CalendarEventManagement::getInstance($db);
$calendarEventGalleryManagement = CalendarEventGalleryManagement::getInstance($db);

function getImagesFromDb($idCalendarEvent) {
	global $db, $ab_path;
	$arImages = array();
	$calendarEventGalleryManagement = CalendarEventGalleryManagement::getInstance($db);
	$arImagesRaw = $calendarEventGalleryManagement->fetchAllByCalendarEventId($idCalendarEvent);
	foreach($arImagesRaw as $key => $calendarEventImage) {
		$arImages[$calendarEventImage['ID_CALENDAR_EVENT_GALLERY']] = array(
			'ID_IMAGE'		=> $calendarEventImage['ID_CALENDAR_EVENT_GALLERY'],
			'FILENAME'		=> $calendarEventImage['FILENAME'],
			'FILE'			=> $ab_path.'cache/event/'.$calendarEventImage['FILENAME'],
			'FILE_RELATIVE' =>  '/cache/event/'.$calendarEventImage['FILENAME'],
			'DIRTY'			=> false
		);
	}
	return $arImages;
}

function getVideosFromDb($idCalendarEvent) {
	global $db, $ab_path;
	$arVideos = array();
	$calendarEventGalleryManagement = CalendarEventGalleryManagement::getInstance($db);
	$arVideosRaw = $calendarEventGalleryManagement->fetchAllVideosByCalendarEventId($idCalendarEvent);
	foreach($arVideosRaw as $key => $calendarEventVideo) {
		$arVideos[$calendarEventVideo['ID_CALENDAR_EVENT_GALLERY_VIDEO']] = array(
			'ID_VIDEO'		=> $calendarEventVideo['ID_CALENDAR_EVENT_GALLERY_VIDEO'],
			'CODE' 			=> $calendarEventVideo['YOUTUBEID'],
			'DIRTY'			=> false
		);

	}
	return $arVideos;
}

$idCalendarEvent = (int)$_REQUEST["id"];
$arCalendarEvent = $calendarEventManagement->fetchById($idCalendarEvent);
if (is_array($arCalendarEvent)) {
	$preselectedNodes = array($arCalendarEvent['FK_KAT']);
	$arCalendarEvent['STAMP_START_DATE'] = date("d.m.Y", strtotime($arCalendarEvent['STAMP_START']));
	$arCalendarEvent['STAMP_START_TIME'] = date("H:i", strtotime($arCalendarEvent['STAMP_START']));
	$arCalendarEvent['STAMP_END_DATE'] = date("d.m.Y", strtotime($arCalendarEvent['STAMP_END']));
	$arCalendarEvent['STAMP_END_TIME'] = date("H:i", strtotime($arCalendarEvent['STAMP_END']));

	// Sprachrelevante Felder
	$languageSelection = $db->fetch_table("SELECT * FROM lang WHERE B_PUBLIC = 1");
	$languageByBitval = array();
	foreach ($languageSelection as $indexLang => $ar_lang) {
		$languageByBitval[ $ar_lang["BITVAL"] ] = $ar_lang["ABBR"];
	}

	if (!empty($_POST)) {
		// Save changes
		if(isset($_REQUEST['DO'])) {
			$data = array();
			if($_REQUEST['DO'] == "toggle_comments") {
				$enabled = 1 - (int)$calendarEventSession["ALLOW_COMMENTS"];
				$db->querynow("UPDATE `calendar_event` SET ALLOW_COMMENTS=".$enabled." WHERE ID_CALENDAR_EVENT=".$calendarEventId);
				header("Content-Type: application/json");
				die(json_encode(array('success' => true, 'enabled' => $enabled)));
			} elseif($_REQUEST['DO'] == "add_searchword") {
				if (!empty($_REQUEST['WORD']) && ((int)$_REQUEST['LANG'] > 0)) {
					$calendarEventManagement->addCalendarEventSearchWordByCalendarEventId($_REQUEST['WORD'], $idCalendarEvent, $languageByBitval[$langval]);
				} else {
					// Error
					header("Content-Type: application/json");
					die(json_encode(array('success' => false)));
				}
			} elseif($_REQUEST['DO'] == "del_searchword") {
				if (!empty($_REQUEST['SEARCHWORD']) && ((int)$_REQUEST['LANG'] > 0)) {
					$langval = (int)$_REQUEST['LANG'];
					$calendarEventManagement->deleteCalendarEventSearchWordByCalendarEventId($_REQUEST['SEARCHWORD'], $idCalendarEvent, $languageByBitval[$langval]);
				} else {
					// Error
					header("Content-Type: application/json");
					die(json_encode(array('success' => false)));
				}
			} elseif($_REQUEST['DO'] == "fetch_searchwords") {
				$langval = (int)$_REQUEST['LANG'];
				if ($langval > 0) {
					// Zeige die Schlagworte
					$calendarEventSearchWords = $calendarEventManagement->fetchAllSearchWordsByCalendarEventId($idCalendarEvent, $languageByBitval[$langval]);
					$tpl_content = new Template("tpl/".$s_lang."/veranstaltung_edit-searchword.htm");
					$tpl_content->addvar(($calendarEventId > 0 ? "EDIT" : "CREATE"), 1);
					$tpl_content->addvar("BITVAL", $langval);
					$tpl_content->addlist("searchwords", $calendarEventSearchWords, 'tpl/'.$s_lang.'/veranstaltung_edit-searchword.row.htm');
					die( $tpl_content->process(true) );
				} else {
					// Error
					header("Content-Type: application/json");
					die(json_encode(array('success' => false)));
				}
			} elseif($_REQUEST['DO'] == "fetch_images") {
				$arImages = getImagesFromDb($idCalendarEvent);
				$templateRow = new Template("tpl/".$s_lang."/empty.htm");
				$templateRow->tpl_text = '{liste}';
				$templateRow->addlist("liste", $arImages, $ab_path."tpl/".$s_lang."/my-calendar-events-add.images_row.htm");
				echo $templateRow->process(); die();
			} elseif($_REQUEST['DO'] == "fetch_videos") {
				$arVideos = getVideosFromDb($idCalendarEvent);
				$templateRow = new Template("tpl/".$s_lang."/empty.htm");
				$templateRow->tpl_text = '{liste}';
				$templateRow->addlist("liste", $arVideos, $ab_path."tpl/".$s_lang."/my-calendar-events-add.videos_row.htm");
				echo $templateRow->process(); die();
			} elseif($_REQUEST['DO'] == "delete_image" && $_REQUEST['ID_IMAGE']) {
				$id_image = (int)$_REQUEST['ID_IMAGE'];
				$db->querynow("DELETE FROM `calendar_event_gallery` WHERE ID_CALENDAR_EVENT_GALLERY=".$id_image);
			} elseif($_REQUEST['DO'] == "delete_video" && $_REQUEST['ID_VIDEO']) {
				$id_video = (int)$_REQUEST['ID_VIDEO'];
				$db->querynow("DELETE FROM `calendar_event_gallery_video` WHERE ID_CALENDAR_EVENT_GALLERY_VIDEO=".$id_video);
			}

			header("Content-Type: application/json");
			die(json_encode(array('success' => true, 'data' => $data)));
		} else {
			if ($arCalendarEvent['FK_REF_TYPE'] != "club") {
				$arCalendarEvent['PRIVACY'] = 0;
			}
			//$arCalendarEvent = array_merge($arCalendarEvent, $_POST);
			$arCalendarEvent['FK_REF_TYPE'] = $_POST['FK_REF_TYPE'];
			$arCalendarEvent['FK_REF'] = ($_POST['FK_REF_TYPE'] == "user" ? $arCalendarEvent["FK_USER"] : $_POST['FK_REF']);
			$arCalendarEvent['FK_KAT'] = $_POST['FK_KAT'];
			$errors = array();
			if (!empty($_POST['TITLE'])) {
				$arCalendarEvent['TITLE'] = $_POST['TITLE'];
			} else {
				$errors[] = "TITLE";
			}
			$stampNowUnix = time();
			$stampStartUnix = 0;
			$stampEndUnix = 0;
			if (preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})/", $_POST['STAMP_START_DATE'], $ar_date)
				&& preg_match("/[0-9]{1,2}:[0-9]{1,2}/", $_POST['STAMP_START_TIME'])) {
				$stampStartUnix = strtotime($ar_date[1].'-'.$ar_date[2].'-'.$ar_date[3].' '.$_POST['STAMP_START_TIME']);
				$arCalendarEvent['STAMP_START'] = date('Y-m-d H:i:s', $stampStartUnix);
			} else {
				$errors[] = "STAMP_START";
			}
			if (preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})/", $_POST['STAMP_END_DATE'], $ar_date)
				&& preg_match("/[0-9]{1,2}:[0-9]{1,2}/", $_POST['STAMP_END_TIME'])) {
				$stampEndUnix = strtotime($ar_date[1].'-'.$ar_date[2].'-'.$ar_date[3].' '.$_POST['STAMP_END_TIME']);
				$arCalendarEvent['STAMP_END'] = date('Y-m-d H:i:s', $stampEndUnix);
			} else {
				$errors[] = "STAMP_END#INVALID";
			}
			if ($stampStartUnix > $stampEndUnix) {
				$errors[] = "STAMP_END#START_BEFORE_END";
			}
			$arCalendarEvent['LOCATION'] = $_POST['LOCATION'];
			$arCalendarEvent['STREET'] = $_POST['STREET'];
			$arCalendarEvent['ZIP'] = $_POST['ZIP'];
			$arCalendarEvent['CITY'] = $_POST['CITY'];
			$arCalendarEvent['FK_COUNTRY'] = $_POST['FK_COUNTRY'];
			$arCalendarEvent['PRIVACY'] = $_POST['PRIVACY'];
			$arCalendarEvent['ALLOW_COMMENTS'] = ($_POST['ALLOW_COMMENTS'] > 0 ? 1 : 0);
			if (!empty($_POST['DESCRIPTION'])) {
				$arCalendarEvent['DESCRIPTION'] = $_POST['DESCRIPTION'];
			} else {
				$errors[] = "DESCRIPTION";
			}
			$geoCoordinates = NULL;
			if ((!empty($_POST['LOCATION']) || !empty($_POST['STREET']))
				&& (!empty($_POST['ZIP']) || !empty($_POST['CITY']))) {
				$location = (!empty($_POST['LOCATION']) ? $_POST['LOCATION']." " : "").$_POST['STREET'];
				$geoCoordinates = Geolocation_Generic::getGeolocationCached($_POST['LOCATION']." ".$_POST['STREET'], $_POST['ZIP'], $_POST['CITY']);
				if($geoCoordinates != NULL) {
					$arCalendarEvent['LONGITUDE'] = $geoCoordinates['LONGITUDE'];
					$arCalendarEvent['LATITUDE'] = $geoCoordinates['LATITUDE'];
				}
			}
			if ($location_required && ($geoCoordinates == NULL)) {
				$errors[] = "LOCATION";
				$errors[] = "STREET";
				$errors[] = "ZIP_CITY";
			}
			if (empty($errors)) {
				// Save changes
				$db->update("calendar_event", $arCalendarEvent);
				die(forward("index.php?page=veranstaltung_edit&frame=popup&id=".$idCalendarEvent."&saved=1"));
			} else {
				die(var_dump($errors));
			}
		}
	}

	// Get images
	$arImages = getImagesFromDb($idCalendarEvent);
	// Get videos
	$arVideos = getVideosFromDb($idCalendarEvent);

	// Clubs des Users
	$clubs = $clubManagement->getClubsByUser($arCalendarEvent["FK_USER"], $langval, 1, null);
	for ($i=count($clubs)-1; $i >= 0; $i--) {
		if (!$clubs[$i]["IS_ADMIN"] && !$clubs[$i]["IS_MODERATOR"]) {
			unset($clubs[$i]);
		}
	}
	$tpl_content->addlist("liste_clubs", $clubs, "tpl/".$s_lang."/veranstaltung_edit.clubs_row.htm");
	$tpl_content->addlist("liste_images", $arImages, $ab_path."tpl/".$s_lang."/my-calendar-events-add.images_row.htm");
	$tpl_content->addlist("liste_videos", $arVideos, $ab_path."tpl/".$s_lang."/my-calendar-events-add.videos_row.htm");
	$tpl_content->addlist("searchWordLanguageHeader", $languageSelection, $ab_path.'tpl/'.$s_lang.'/my-calendar-events-searchword.lang.header.htm');
	$tpl_content->addlist("searchWordLanguageBody", $languageSelection, $ab_path.'tpl/'.$s_lang.'/my-calendar-events-searchword.lang.body.htm');

	$categoryJSONTree = $calendarEventManagement->getCalendarEventCategoryJSONTree($preselectedNodes);
	if (isset($_REQUEST["saved"])) {
		$tpl_content->addvar("SAVED", 1);
	}
	$tpl_content->addvar("EDIT", 1);
	$tpl_content->addvar("CATEGORY_JSON_TREE", $categoryJSONTree);
	$tpl_content->addvar("FK_REF_TYPE_".strtoupper($arCalendarEvent["FK_REF_TYPE"]), 1);
	$tpl_content->addvars($arCalendarEvent);
}

<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once $ab_path.'sys/lib.calendar_event.php';
require_once $ab_path.'sys/lib.calendar_event.gallery.php';

$calendarEventManagement = CalendarEventManagement::getInstance($db);
$calendarEventGalleryManagement = CalendarEventGalleryManagement::getInstance($db);

$calendarEventId = ($ar_params[2] ? (int)$ar_params[2] : null);
$calendarEvent = $calendarEventManagement->fetchById($calendarEventId);
$vendorTemplate = array();

$actionEx = (!empty($ar_params[3]) ? $ar_params[3] : false);
$userIsAdmin = $db->fetch_atom("SELECT count(*) FROM `role2user` ru JOIN `role` r ON r.ID_ROLE=ru.FK_ROLE AND FK_USER=".$uid." WHERE r.LABEL='Admin'");

/**
 * Info-Text anzeigen?
 */
if (!empty($actionEx)) {
    $tpl_content->addvar("info_".$actionEx, 1);
}

if ($userIsAdmin) {
    if ($_REQUEST['decline'] > 0) {
        $id_event = (int)$_REQUEST["decline"];
        $calendarEventManagement->adminDecline($id_event, $_REQUEST["REASON"]);
        die(forward($tpl_content->tpl_uri_action("marktplatz_anzeige,".$id_article.",".chtrans($arAd["PRODUKTNAME"]).",declined")));
    }

    if ($_REQUEST["ajax"] == "unlockEvent") {
        header('Content-type: application/json');
        die(json_encode(array(
            "success"   => $calendarEventManagement->adminAccept($calendarEventId)
        )));
    }
}
if ($actionEx !== false) {
    switch ($actionEx) {
        case 'iCal':
            // Domain name
            $siteDomain = str_replace('http://', '', $nar_systemsettings["SITE"]["SITEURL"]);
            // Location
            $arLocation = array();
            if (!empty($calendarEvent["LOCATION"])) $arLocation[] = $calendarEvent["LOCATION"];
            if (!empty($calendarEvent["STREET"])) $arLocation[] = $calendarEvent["STREET"];
            if (!empty($calendarEvent["ZIP"])) $arLocation[] = $calendarEvent["ZIP"];
            if (!empty($calendarEvent["CITY"])) $arLocation[] = $calendarEvent["CITY"];
            if (!empty($calendarEvent["FK_COUNTRY"])) {
                $arLocation[] = $db->fetch_atom("SELECT V1 FROM string
                    WHERE S_TABLE='country' AND BF_LANG=".$langval." AND
                        FK=".(int)$calendarEvent["FK_COUNTRY"]);
            }
            // Description
            $description = strip_tags(str_replace(
                array("<br>", "<BR>", "<br/>", "<BR/>", "<br />", "<BR />"),
                "\n",
                $calendarEvent["DESCRIPTION"]
            ));
            // URL
            $urlTitle = addnoparse(chtrans($calendarEvent["TITLE"]));
            $url = $tpl_content->tpl_uri_action_full("calendar_events_view,".$urlTitle.",".$calendarEvent["ID_CALENDAR_EVENT"]);
            // Start date
            $startTime = date_parse($calendarEvent["STAMP_START"]);
            $start = array('year' => $startTime['year'], 'month' => $startTime['month'], 'day' => $startTime['day'],
                'hour' => $startTime['hour'], 'min' => $startTime['minute'], 'sec' => $startTime['second']);
            // End date
            $endTime = date_parse($calendarEvent["STAMP_END"]);
            $end = array('year' => $endTime['year'], 'month' => $endTime['month'], 'day' => $endTime['day'],
                'hour' => $endTime['hour'], 'min' => $endTime['minute'], 'sec' => $endTime['second']);
            // Create iCal object
            require_once $ab_path.'sys/iCalcreator/iCalcreator.class.php';
            $iCalConfig = array("unique_id" => $siteDomain);
            $iCal = new vcalendar($iCalConfig);
            $iCal->setProperty( "method", "PUBLISH" );
            $iCal->setProperty( "CLASS", "PUBLIC" );
            // required of some calendar software
            $iCal->setProperty( "x-wr-calname", $calendarEvent["TITLE"] );
            $iCal->setProperty( "X-WR-CALDESC", $calendarEvent["DESCRIPTION"] );
            $iCal->setProperty( "X-WR-TIMEZONE", "Europe/Stockholm" );
            $iCalEvent = $iCal->newComponent("vevent");
            $iCalEvent->setProperty( "DTSTART", $start );
            $iCalEvent->setProperty( "DTEND", $end );
            $iCalEvent->setProperty( "LOCATION", implode(" ", $arLocation) );
            $iCalEvent->setProperty( "SUMMARY", $calendarEvent["TITLE"]);
            $iCalEvent->setProperty( "DESCRIPTION", $description );
            $iCalEvent->setProperty( "URL", $url );
            // output ics file
            $iCal->returnCalendar();
            break;
    }
}

if (isset($_POST["ajax"])) {
	$result = false;
	switch ($_POST["ajax"]) {
		case 'signup':
			$result = $calendarEventManagement->userSignup($calendarEventId, $uid, (int)$_POST["state"]);
			break;
		case 'signup_cancel':
			//$result = $calendarEventManagement->userSignupCancel($calendarEventId, $uid);
			break;
	}
	header("Content-Type:application/json");
	die(json_encode(array("success" => $result)));
}

if($calendarEventId != null && $calendarEvent != null) {
	require_once $ab_path."sys/lib.club.php";
	$clubManagement = ClubManagement::getInstance($db);
	if (($calendarEvent["IS_CONFIRMED"] == 0) || ($calendarEvent["MODERATED"] != 1)) {
		if (!$userIsAdmin && !$clubManagement->isClubOwner($calendarEvent["FK_REF"], $uid)
			&& !$clubManagement->isClubModerator($calendarEvent["FK_REF"], $uid)) {
			$tpl_content->addvar("EVENT_NOT_FOUND", 1);
			return;
		}
	}
	if ($calendarEvent["PRIVACY"] == 0) {
		// Check rights
		switch ($calendarEvent["FK_REF_TYPE"]) {
			case "club":
				if (!$clubManagement->isMember($calendarEvent["FK_REF"], $uid)) {
					// Not a club member! Not allowed to signup for a club-exclusive event
					$tpl_content->addvar("EVENT_NOT_FOUND", 1);
					return;
				}
				break;
			default:
				// Not allowed to signup for a private event
				$tpl_content->addvar("EVENT_NOT_FOUND", 1);
				return;
		}
	}

	$tpl_main->addvar('newstitle',$calendarEvent['TITLE']);

	// Anmeldung
	$userSignedup = $calendarEventManagement->getUserSignupStatus($calendarEventId, $uid);
	$tpl_content->addvar("USER_IS_SIGNEDUP", ($userSignedup !== null));
	if ($userSignedup) {
		$tpl_content->addvar("USER_SIGNUP_STATUS", $userSignedup);
	}

	// Gallerie
	$galleries = $calendarEventGalleryManagement->fetchAllByCalendarEventId($calendarEventId);
	foreach($galleries as $key => $gallery) {
		$galleries[$key]['FILENAME'] = 'cache/event/'.$gallery['FILENAME'];
	}
	$tpl_content->addlist("EVENT_GALLERY", $galleries, $ab_path.'tpl/'.$s_lang.'/calendar_events_view.gallery.htm');

	// Gallerie Video
	$galleryVideos = $calendarEventGalleryManagement->fetchAllVideosByCalendarEventId($calendarEventId);

	$tpl_content->addlist("EVENT_GALLERY_VIDEO", $galleryVideos, $ab_path.'tpl/'.$s_lang.'/calendar_events_view.gallery_video.htm');


	//suchwörter

	$calendarEventSearchWords = $calendarEventManagement->fetchAllSearchWordsByCalendarEventId($calendarEvent['ID_CALENDAR_EVENT'], $s_lang);
	$tpl_content->addlist("EVENT_KEYWORDS", $calendarEventSearchWords, $ab_path . 'tpl/' . $s_lang . '/calendar_events-searchword.row.htm');


	// Meta Tags
	$defaultMetaTags = $tpl_main->vars['metatags'];
	$meta_description = trim(strip_tags($calendarEvent['DESCRIPTION']));
	if (strlen($meta_description) > 160) {
		// Text kürzen auf 160-200 Zeichen
		$meta_description_len = strrpos(substr($meta_description, 0, 200), " ");
		$meta_description = $calendarEvent['TITLE']." ".substr($meta_description, 0, $meta_description_len);
	}
	if (!empty($meta_description)) {
		$defaultMetaTags = preg_replace('/(<meta name="description" content=")(.*)(">)/i', "\${1}".htmlspecialchars($meta_description)."\${3}", $defaultMetaTags);
	}
	$tpl_main->vars['metatags'] = $defaultMetaTags;
	// ------

	if($calendarEvent['FK_REF_TYPE'] != "") {
		$tpl_content->addvar("EVENT_FK_REF_TYPE_".strtoupper($calendarEvent['FK_REF_TYPE']), 1);
	}

	$tpl_content->addvars($calendarEvent, "EVENT_");
	$tpl_content->addvar("comments_enabled", ($nar_systemsettings["MARKTPLATZ"]["ALLOW_COMMENTS_EVENT"] ? true : false));

    $tpl_content->addvar("USER_IS_ADMIN", $userIsAdmin);
    $tpl_content->addvar("MODERATED", $calendarEvent['MODERATED']);
    if ($article_data_master['MODERATED'] == 2) {
        $tpl_content->addvar("DECLINE_REASON", $calendarEvent['DECLINE_REASON']);
    }

    $tpl_content->addvar("VENDOR_OPEN_PAGE_view_user_events",1);

} else {
	$tpl_content->addvar("EVENT_NOT_FOUND", 1);
}

<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.club.php";
require_once $ab_path."sys/lib.calendar_event.php";
$clubManagement = ClubManagement::getInstance($db);
$calendarEventManagement = CalendarEventManagement::getInstance($db);

$id_club = ((int)$tpl_content->vars['OVERRIDE_CLUB_ID'] > 0) ? $tpl_content->vars['OVERRIDE_CLUB_ID'] : $ar_params[2];

/** Club **/
$ar_club = $clubManagement->getClubById($id_club);

if (!empty($ar_club) && (($ar_club['STATUS'] == 1) || ($ar_club['FK_USER'] == $uid))) {
	// Logo
	$ar_club['LOGO'] = ($ar_club['LOGO'] != "" ? 'cache/club/logo/'.$ar_club['LOGO'] : null);

	// Gallerie
	$galleries = $clubManagement->getImagesByClubId($id_club);
	foreach($galleries as $key => $gallery) {
		$galleries[$key]['FILENAME'] = 'cache/clubs/'.(int)$id_club.'/'.$gallery['FILENAME'];
	}
	// Gallerie Video
	$galleryVideos = $clubManagement->getVideosByClubId($id_club);
	$calendarCount = $calendarEventManagement->countByParam(array('FK_REF_TYPE' => 'club', 'FK_REF' => $id_club));

	// Templatevariablen schreiben
	$tpl_content->addvars($ar_club, "CLUB_");
	$tpl_content->addlist("CLUB_GALLERY", $galleries, $ab_path.'tpl/'.$s_lang.'/club.gallery.htm');
	$tpl_content->addlist("CLUB_GALLERY_VIDEO", $galleryVideos, $ab_path.'tpl/'.$s_lang.'/club.gallery_video.htm');
    $tpl_content->addvar("HAS_CALENDAR", ($calendarCount > 0 ? true : false));
    $tpl_content->addvar("HAS_FORUM", $ar_club["FORUM_ENABLED"] && ($ar_club["FORUM_PUBLIC"] || $ar_club["IS_ADMIN"] || $clubManagement->isMember($id_club, $uid)));
} else {

}

?>
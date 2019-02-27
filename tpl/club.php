<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.club.php";
require_once $ab_path."sys/lib.club.category.php";
require_once $ab_path."sys/lib.club_member_request.php";
$clubManagement = ClubManagement::getInstance($db);
$clubCategoryManagement = ClubCategoryManagement::getInstance($db);
$clubCategoryManagement->setLangval($langval);
$clubMemberRequestManagement = ClubMemberRequestManagement::getInstance($db);


$id_club = $ar_params[2];

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
    // Kategorie Liste
    $categories = $clubCategoryManagement->fetchAllClubCategoriesByClubId($id_club);
    $tpl_categories = new Template($ab_path."tpl/".$s_lang."/club.row.categories.htm");
    $tpl_categories->addlist("categories", $categories, $ab_path.'tpl/'.$s_lang.'/club.row.categories.row.htm');
    $ar_club['CATEGORIES'] = $tpl_categories->process();

	// Templatevariablen schreiben
	$tpl_content->addvars($ar_club, "CLUB_");
	$tpl_content->addlist("CLUB_GALLERY", $galleries, $ab_path.'tpl/'.$s_lang.'/club.gallery.htm');
	$tpl_content->addlist("CLUB_GALLERY_VIDEO", $galleryVideos, $ab_path.'tpl/'.$s_lang.'/club.gallery_video.htm');

	$tpl_content->addvar("IS_MEMBER_IN_CLUB", $clubManagement->isMember($id_club, $uid));
	$tpl_content->addvar("IS_MEMBER_REQUEST_OPEN_IN_CLUB", $clubMemberRequestManagement->existMembershipRequest($id_club, $uid));
	$tpl_content->addvar("IS_MEMBER_REQUEST_BLOCKED_IN_CLUB", $clubMemberRequestManagement->isMemberRequestBlocked($id_club, $uid));

	$tpl_content->addvar("IS_CLUB_INVITE", $clubManagement->existInviteForUserInClub($uid, $id_club));


    //suchwörter

    $clubSearchWords = $clubManagement->getSearchWordsByClubId($id_club, $langval);
    $tpl_content->addlist("searchwords", $clubSearchWords, $ab_path.'tpl/'.$s_lang.'/club-searchword.row.htm');

    if ($nar_systemsettings["MARKTPLATZ"]["ALLOW_COMMENTS_CLUB"]) {
    	$tpl_content->addvar("comments_enabled", $clubManagement->isCommentAllowed($id_club));
    }
$tpl_content->addvar("active_club", 1);


    
} else {
	$url = $tpl_content->tpl_uri_baseurl("/404.htm");
	die(forward( $url ));
}

?>
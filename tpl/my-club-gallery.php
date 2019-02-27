<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.club.php";

$clubManagement = ClubManagement::getInstance($db);
$clubId = (isset($ar_params[1]) ? $ar_params[1] : 0);
$clubAction = (isset($ar_params[2]) ? $ar_params[2] : "view");
$ar_club = array();
$err = array();

if (!empty($_POST)) {
	// Form abgeschickt
	$clubId = $_POST["ID_CLUB"];
	$clubAction = $_POST["do"];
}

switch ($clubAction) {
	case 'saved':
		$tpl_content->addvar($clubAction, 1);
		$clubAction = "view";
		break;
}

switch ($clubAction) {
	case 'add':
		$result = false;
		if ($_REQUEST['gallery_type'] == 'image') {
			// Bild
		    if(preg_match("/(\.gif|\.jpg|\.jpeg|\.png)$/", strtolower($_FILES['FILENAME']['name']))) {
		        $clubFilename = md5_file($_FILES['FILENAME']['tmp_name']).'_'.$_FILES['FILENAME']['name'];
		        $clubDir = $ab_path.'cache/clubs/'.(int)$clubId;
		        $clubFile = $clubDir.'/'.$clubFilename;
		        @mkdir($clubDir, 0777, true);
		        @move_uploaded_file($_FILES['FILENAME']['tmp_name'], $clubFile);
		        @chmod($clubFile, 0777);
		        $result = $clubManagement->insertFile("", $clubFilename, $clubId);
		    }
		} else if ($_REQUEST['gallery_type'] == 'video') {
			// Video
			require_once 'sys/lib.youtube.php';
			$youtube = new Youtube();

			$youtubeId = $youtube->ExtractCodeFromURL($_POST['youtubelink']);
			if($youtubeId != null) {
				$result = $clubManagement->insertVideo("", $youtubeId, $clubId);
			}
		}
		// Success
		$url = $tpl_content->tpl_uri_action("my-club-gallery,".$clubId.",saved");
		die(forward($url));
	case 'delete':
		$type = $ar_params[3];
		$id = (int)$ar_params[4];
		if ($type == 'image') {
	        $result = $clubManagement->deleteFile($clubId, $id);
		} else if ($type == 'video') {
	        $result = $clubManagement->deleteVideo($clubId, $id);
		}
		// Success
		$url = $tpl_content->tpl_uri_action("my-club-gallery,".$clubId.",saved");
		die(forward($url));
	case 'view':
		if ($clubId > 0) {
			$ar_club = $clubManagement->getClubById($clubId);
		}
		$ar_club["ADMIN"] = ($ar_club["FK_USER"] == $uid);
		$ar_club["MODERATOR"] = ($ar_club["ADMIN"] || $clubManagement->isClubModerator($clubId, $uid));
		break;
}

$ar_club['LOGO'] = ($ar_club['LOGO'] != "" ? 'cache/club/logo/'.$ar_club['LOGO'] : null);

// Bilder auslesen
$clubGalleries = $clubManagement->getImagesByClubId($clubId);
foreach($clubGalleries as $key => $clubGallery) {
    $clubGalleries[$key]['FILENAME'] = 'cache/clubs/'.(int)$clubId.'/'.$clubGallery['FILENAME'];
}
// Videos auslesen
$clubGalleryVideos = $clubManagement->getVideosByClubId($clubId);

// Templatevariablen übergeben
$tpl_content->addvars($ar_club, "CLUB_");
$tpl_content->addlist("liste", $clubGalleries, $ab_path.'tpl/'.$s_lang.'/my-club-gallery.row.htm');
$tpl_content->addlist("liste_video", $clubGalleryVideos, $ab_path.'tpl/'.$s_lang.'/my-club-gallery.row_video.htm');

    if (
        (count($clubGalleries)+count($clubGalleryVideos)) >= $nar_systemsettings['USER']['CLUB_GALLERY_MAX_IMAGES']
    )
        $tpl_content->addvar("maxbilder_voranden",1);
    else
        $tpl_content->addvar("maxbilder_voranden",0);

$tpl_content->addvar ("maxbilder",$nar_systemsettings['USER']['CLUB_GALLERY_MAX_IMAGES']);

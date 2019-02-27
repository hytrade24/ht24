<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.vendor.php';
require_once 'sys/lib.vendor.gallery.php';
 
$userId = $uid;
$vendorManagement = VendorManagement::getInstance($db);
$vendorGalleryManagement = VendorGalleryManagement::getInstance($db);

$vendorGalleries = $vendorGalleryManagement->fetchAllByUserId($userId);

foreach($vendorGalleries as $key => $vendorGallery) {
    $vendorGalleries[$key]['FILENAME'] = 'cache/vendor/gallery/'.$vendorGallery['FILENAME'];
}

$vendorGalleryVideos = $vendorGalleryManagement->fetchAllVideosByUserId($userId);


$tpl_content->addlist("liste", $vendorGalleries, $ab_path.'tpl/'.$s_lang.'/my-vendor-gallery.row.htm');


// Trigger plugin event
$paramVendorVideo = new Api_Entities_EventParamContainer(array(
    "vendorManagement"          => $vendorManagement,
    "vendorGalleryManagement"   => $vendorGalleryManagement,
    "list"		                => $vendorGalleryVideos,
    "result"	                => null
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::VENDOR_RENDER_VIDEOS, $paramVendorVideo);
if ($paramVendorVideo->isDirty() && ($paramVendorVideo->getParam("result") !== null)) {
    $tpl_content->addvar("videos", $paramVendorVideo->getParam("result"));
} else {
    $tpl_videos = new Template("tpl/" . $s_lang . "/my-vendor-gallery.videos.htm");
    $tpl_videos->addlist("liste", $vendorGalleryVideos, "tpl/" . $s_lang . "/my-vendor-gallery.row_video.htm");
    $tpl_content->addvar("videos", $tpl_videos->process(true));
}

if (
    (count($vendorGalleries)+count($vendorGalleryVideos)) >= $nar_systemsettings['USER']['VENDOR_GALLERY_MAX_IMAGES']
    )
    $tpl_content->addvar("maxbilder_voranden",1);
else
    $tpl_content->addvar("maxbilder_voranden",0);

$tpl_content->addvar ("maxbilder",$nar_systemsettings['USER']['VENDOR_GALLERY_MAX_IMAGES']);

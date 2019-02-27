<?php
if (!empty($_POST)) {
    header('Content-type: application/json');

    $json = array(
        'success' => false,
        'error' => false,
    );

    if (!is_array($_POST['ID'])) {
        $_POST['ID'] = array($_POST['ID']);
        $_POST['TYPE'] = array($_POST['TYPE']);
    }

    foreach($_POST['ID'] as $i => $id) {
        $data = null;
        switch ($_POST['TYPE'][$i]) {
            case 'marktplatz':
                    $data = getMarktplatzMarkerInfo((int)$id);
                    if (!$data['SRC']) {
                        $data['SRC'] = '/cache/design/resources/de/images/marketplace/nopic.jpg';
                    }
                    $data['THUMBNAIL'] = $tpl_content->tpl_thumbnail('"' . $data['SRC'] . '",' . (int)$_POST['WIDTH'] . ',' . (int)$_POST['HEIGHT']);
                break;
            case 'vendor':
                    $data = getVendorMarkerInfo((int)$id);
                    if (!$data['SRC']) {
                        $data['SRC'] = '/cache/design/resources/de/images/marketplace/nopic_vendor.jpg';
                    }
                    $data['THUMBNAIL'] = $tpl_content->tpl_thumbnail('"' . $data['SRC'] . '",' . (int)$_POST['WIDTH'] . ',' . (int)$_POST['HEIGHT']);
                break;
            case 'event':
                    $data = getEventMarkerInfo((int)$id);
                    if ($data['SRC']) {
                        $data['THUMBNAIL'] = $tpl_content->tpl_thumbnail('"' . $data['SRC'] . '",' . (int)$_POST['WIDTH'] . ',' . (int)$_POST['HEIGHT']);
                    }
                break;
	        case 'useroverview':
	        	$data = getUserMarkerInfo((int)$id,$db,$tpl_content);
	        	if ( $data['SRC'] ) {
			        $data['THUMBNAIL'] = $tpl_content->tpl_thumbnail('"' . $data['SRC'] . '",' . (int)$_POST['WIDTH'] . ',' . (int)$_POST['HEIGHT']);
		        }

	        	break;
        }
        if ($data !== null) {
            if (array_key_exists("FK_KAT", $data) && ($data["FK_KAT"] > 0)) {
                $data["KAT_PATH"] = $tpl_content->tpl_market_kat_path_url($data["FK_KAT"]);
            }
            $json[] = $data;
        }
    }

    $json['success'] = true;
    die(json_encode($json));
}

function getUserMarkerInfo($id,$db,&$tpl_content) {
	global $ab_path;

	require_once $ab_path . "sys/lib.user.php";

	$userManagement = UserManagement::getInstance($db);

	$info = $userManagement->fetchById($id);

	$user = array();
	$user['USEROVERVIEW'] = true;
	$user['ID'] = $info["ID_USER"];
	$user['TITLE'] = $info["NAME"];
	$user['URL_TITLE'];
	$user['SRC'] = "cache/users/".$info["CACHE"]."/".$info["ID_USER"]."/".$info["ID_USER"].".jpg";
	$user['URL_TITLE'] = $tpl_content->tpl_uri_baseurl("anbieter/view_user,".$info["NAME"].",".$info["ID_USER"].".htm");
	$user['STREET'] = $info["STRASSE"];
	$user['ZIP'] = $info["PLZ"];
	$user['CITY'] = $info["ORT"];

	return $user;
}

function getMarktplatzMarkerInfo($id) {
    global $ab_path;

    require_once $ab_path . "sys/lib.ads.php";

    $info = AdManagment::getAdById($id);

    $info['USEROVERVIEW'] = true;
    $info['ID'] = $info['ID_AD_MASTER'];
    $info['TITLE'] = $info['PRODUKTNAME'];
    $info['KATEGORIE'] = $info['KAT'];
    $info['COUNTRY'] = $info['LAND'];
    $info['URL_TITLE'] = chtrans($info['PRODUKTNAME']);


    return $info;
}


function getVendorMarkerInfo($id) {
    global $ab_path, $db;

    require_once $ab_path . "sys/lib.vendor.php";

    $vendorManagement = VendorManagement::getInstance($db);
    $info = $vendorManagement->fetchByUserId($id);

    $info['VENDOR'] = true;
    $info['ID'] = $info['FK_USER'];
    $info['TITLE'] = $info['NAME'];
    if ($info['LOGO'] != null) {
        $info['SRC'] = 'cache/vendor/logo/' . $info['LOGO'];
    }
    $info['URL_TITLE'] = chtrans($info['NAME']);
    $info['STREET'] = $info['STRASSE'];
    $info['ZIP'] = $info['PLZ'];
    $info['CITY'] = $info['ORT'];

    return $info;
}


function getEventMarkerInfo($id) {
    global $ab_path, $db;

    require_once $ab_path . "sys/lib.calendar_event.php";

    $eventManagement = CalendarEventManagement::getInstance($db);
    $info = $eventManagement->fetchById($id);

    $info['EVENT'] = true;
    $info['SIGNUPS'] = $info['SIGNUPS_DECLINED'] + $info['SIGNUPS_CONFIRMED'] + $info['SIGNUPS_UNSURE'];
    $info['EVENT_STAMP_START'] = date("d.m.y", strtotime($info['STAMP_START']));
    $info['URL_TITLE'] = chtrans($info['TITLE']);
    $info['ID'] = $info['ID_CALENDAR_EVENT'];

    return $info;
}


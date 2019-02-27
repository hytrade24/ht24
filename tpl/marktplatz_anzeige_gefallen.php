<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.ad_like.php';

$adId = ((int)$ar_params[1] ? (int)$ar_params[1] : null);
$task = ((string)$ar_params[2] ? (string)$ar_params[2] : null);

$adLikeManagement = AdLikeManagement::getInstance($db);

try {
	switch($task) {
		case 'toggle':
			$adLikeManagement->toggleLike($uid, $adId);
			echo json_encode(array('success' => true)); die();
			break;
		case 'get':
			$isLike = $adLikeManagement->isLike($uid, $adId);
			echo json_encode(array('success' => true, 'like' => $isLike)); die();
			break;
		case 'count':
			$countLike = $adLikeManagement->countLikesByAdId($adId);
			echo json_encode(array('success' => true, 'count' => $countLike)); die();
			break;
			
	}
} catch(Exception $e) {
	echo json_encode(array('success' => false)); die();
}
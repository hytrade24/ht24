<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . 'sys/lib.cache.adapter.php';

$cacheAdapter = new CacheAdapter();

$resultSuccess = true;
$stepResult = array();

try {
	switch ($_GET['type']) {
		case 'less_browser':
			$arLanguagesActive = array();
			foreach ($lang_list as $langIndex => $langDetails) {
				if ($langDetails["B_PUBLIC"]) {
					$arLanguagesActive[] = $langDetails["ABBR"];
				}
			}
			$tpl_content = new FrameTemplate("tpl/".$s_lang."/cache_rewrite_less", $frame);
			$tpl_content->addvar("languages", json_encode($arLanguagesActive));
			$tpl_content->addvar("cssDirectory", "css");
			$tpl_content->addvar("accessToken", sha1($db_user."~".$db_pass));
			die($tpl_content->process());
			break;
		case 'template':
			$cacheAdapter->cacheTemplate();
			$cacheAdapter->cacheLess();
			break;
		case 'content':
			$cacheAdapter->cacheContent();
			break;
		case 'step_template':
		case 'step_less':
		case 'step_content':
		case 'step_all':
			$realType = 'all';
			if (strpos($_GET['type'], 'step_') === 0) {
				$realType = substr($_GET['type'], 5);
			}
			$stepResult = $cacheAdapter->cacheStep($_GET['step'], $realType);
			if($stepResult == false) {
				$resultSuccess = false;
			} else {
				$resultData = $stepResult;
			}
	        break;
		case 'step_statistik':
			$resultSuccess = Api_DatabaseCacheStorage::getInstance()->deleteContentByRelations(array("STATISTIC" => 1));
			break;
		default:
			$cacheAdapter->cacheAll();
	}
} catch (Exception $e) {
	$exceptionMessage = substr($e->getMessage(), 0, 2048);
	die(json_encode(
		array('success' => false, 'data' => $stepResult, 'error' => utf8_encode($exceptionMessage))
	));
}

if ($cacheAdapter->getLastError() !== false) {
    die(json_encode(
        array('success' => false, 'data' => $stepResult, 'error' => $cacheAdapter->getLastError())
    ));
} else {
    die(json_encode(
        array('success' => $resultSuccess, 'data' => $stepResult)
    ));
}

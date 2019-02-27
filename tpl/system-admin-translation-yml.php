<?php

require_once $ab_path . 'sys/lib.translation.php';
require_once $ab_path . 'sys/lib.template.design.php';
require_once $ab_path . 'sys/lib.cache.template.php';
require_once $ab_path . 'sys/lib.cache.translation.php';
require_once $ab_path . 'sys/lib.translation_tool.php';

$templateDesignManagement = TemplateDesignManagement::getInstance($db);
$cacheTemplateManangement = new CacheTemplate();
$cacheTranslation = new CacheTranslation();
$translationTool = new TranslationTool();


if(!$nar_systemsettings['SITE']['TEMPLATE_TRANSLATION_TOOL']) {
	return;
}
if(isset($_POST['DO']) && $_POST['DO'] == 'save') {

	$data = $_POST['TRANSLATION'];
	$templateName = $nar_systemsettings['SITE']['TEMPLATE'];
	$translationUsage = CacheTemplate::readCacheTranslationUsageFile();
	$numberOfUpdatedTranslations = 0;


	foreach($data as $lang => $translationNamespaces) {
		$userLanguageTranslationDirectory = $templateDesignManagement->getDesignPath().$templateName.'/'.$lang.'/translation/';

		foreach($translationNamespaces as $translationNamespace => $translation) {
			$userLanguageTranslationFile = $userLanguageTranslationDirectory.$translationNamespace.'.yml';
			$newTranslationsInFile = array();

			if(file_exists($userLanguageTranslationFile)) {
				$currentTranslationsInFile = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($userLanguageTranslationFile));
			}
			if(!is_array($currentTranslationsInFile)) {
				$currentTranslationsInFile = array();
			}

			foreach($translation as $translationIdent => $translationValue) {

				$currentTranslation = Translation::readTranslation($translationNamespace, $translationIdent, $lang, $translationUsage['IDENT'][$translationNamespace.':'.$translationIdent]['TRANSLATION_PARAMETER'], $translationUsage['IDENT'][$translationNamespace.':'.$translationIdent]['TRANSLATION_FALLBACK'], false, array(), false);

				$compareCurrentTranslation = trim($currentTranslation);
				$translationValue = trim(str_replace(array("\r\n", "\r"), array("\n", "\n"), $translationValue));

				if($compareCurrentTranslation != $translationValue && $translationValue != "") {
					$newTranslationsInFile[$translationIdent] = $translationValue;
				}
			}

			if(count($newTranslationsInFile) > 0) {
				$numberOfUpdatedTranslations += count($newTranslationsInFile);

				$newTranslationsInFile = array_merge($currentTranslationsInFile, $newTranslationsInFile);
				if(!is_dir($userLanguageTranslationDirectory)) {
					mkdir($userLanguageTranslationDirectory, 0777, true);
				}

				file_put_contents($userLanguageTranslationFile, \Symfony\Component\Yaml\Yaml::dump($newTranslationsInFile));

			}
		}
	}


	Translation::clearTranslationCache();

	$cacheTranslation->cacheAll();
	$cacheTemplateManangement->cacheAll();


	echo json_encode(array('success' => true, 'data' => array('affectedRows' => $numberOfUpdatedTranslations)));
	die();
} elseif(isset($_POST['DO']) && $_POST['DO'] == 'startrecord') {

} elseif(isset($_POST['DO']) && $_POST['DO'] == 'reload') {
    if (array_key_exists('ebiz_trader_translation_tool_loaded_tags', $_SESSION)) {
        foreach ($_SESSION['ebiz_trader_translation_tool_loaded_tags'] as $transIdent => $transParams) {
            TranslationTool::logAdditionalTranslationUsage($transParams[0], $transParams[1], $transParams[2], $transParams[3]);
        }
    }
	echo $translationTool->renderTranslationTool($_SESSION['ebiz_trader_translation_tool_loaded_templates'], false);
	die();
}
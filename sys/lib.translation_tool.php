<?php
/* ###VERSIONSBLOCKINLCUDE### */


class TranslationTool {
    public static $additionalTranslationFiles = array();
	public static $additionalTranslationUsageStack = array();

	public function initPageRequest() {
		$_SESSION['ebiz_trader_translation_tool_loaded_templates'] = array();
        $_SESSION['ebiz_trader_translation_tool_loaded_tags'] = array();
	}

	public function renderTranslationTool($usedFiles = NULL, $shouldAddNoParse = true) {
		global $assoc_loaded_templates, $s_lang, $lang_list, $ab_path;

		if($usedFiles == NULL) {
			$usedFiles = array_keys($assoc_loaded_templates);
            if (!empty(self::$additionalTranslationFiles)) {
                foreach (self::$additionalTranslationFiles as $key => $usedFile) {
                    $usedFiles[] = $usedFile;
		        }
            }
		}

		$usedTranslations = array();
		$translationUsage = CacheTemplate::readCacheTranslationUsageFile();

		foreach($usedFiles as $key => $usedFile) {

			if(isset($translationUsage['FILENAME'][$usedFile])) {
				foreach($translationUsage['FILENAME'][$usedFile] as $identifier => $translation) {
					$usedTranslations[$identifier] =  $translationUsage['IDENT'][$identifier];
					$usedTranslations[$identifier]['BASEFILENAME'] = $translation['BASEFILENAME'];
				}
			}
		}

		$usedTranslations = array_merge(self::$additionalTranslationUsageStack, $usedTranslations);

		$p = 0;
		foreach($usedTranslations as $key => $translation) {
			$translationLanguages = array();
			foreach($lang_list as $l => $lang) {
				$parsedValue = $shouldAddNoParse?addnoparse($translation['TRANSLATION'][$l]):$translation['TRANSLATION'][$l];
				$parsedFallback = $shouldAddNoParse?addnoparse($translation['TRANSLATION_FALLBACK']):$translation['TRANSLATION_FALLBACK'];
				$tpl_lang = new Template($ab_path.'tpl/'.$s_lang.'/translation_tool.translation.lang.htm');
				$tpl_lang->addvars($translation);
				$tpl_lang->addvars($lang);
				$tpl_lang->addvar('VALUE', $parsedValue);
				$tpl_lang->addvar('TABINDEX', (($lang['ID_LANG']+1)*1000) + $p);
                $tpl_lang->addvar('TRANSLATION_FALLBACK', $parsedFallback);
				$tpl_lang->addvar('MULTILINE', (strpos($translation['TRANSLATION'][$l], "\n") !== FALSE || strpos($translation['TRANSLATION_FALLBACK'], "\n") !== FALSE));
				$translationLanguages[] = $tpl_lang;
			}

            $usedTranslations[$key]['TRANSLATION_FALLBACK'] = addnoparse($translation['TRANSLATION_FALLBACK']);
			$usedTranslations[$key]['TRANSLATION_LANGS'] = $translationLanguages;
			$p++;
		}

		$tpl = new Template($ab_path."tpl/".$s_lang."/translation_tool.htm");
		$tpl->addlist('TRANSLATIONS', $usedTranslations, $ab_path.'tpl/'.$s_lang.'/translation_tool.translation.row.htm');
		$tpl->addlist('lang_header', $lang_list, $ab_path.'tpl/'.$s_lang.'/translation_tool.translation.lang_header.htm');

        return $tpl->process();
	}

    public static function logAdditionalTranslationFile($filename) {
        if (file_exists($filename)) {
            self::$additionalTranslationFiles[] = str_replace($GLOBALS["ab_path"], "", $filename);
        }
    }

	public static function logAdditionalTranslationUsage($transNamespace, $transIdent, $arParamAliases, $transFallback) {
		global $s_lang, $lang_list;
		$translationIdentifier = $transNamespace.':'.$transIdent;

        if (!array_key_exists('ebiz_trader_translation_tool_loaded_tags', $_SESSION)) {
            $_SESSION['ebiz_trader_translation_tool_loaded_tags'] = array();
        }
        if (!array_key_exists($translationIdentifier, $_SESSION['ebiz_trader_translation_tool_loaded_tags'])) {
            $_SESSION['ebiz_trader_translation_tool_loaded_tags'][$translationIdentifier] = array($transNamespace, $transIdent, $arParamAliases, $transFallback);
        }
        
		if(!array_key_exists($translationIdentifier, self::$additionalTranslationUsageStack)) {
			self::$additionalTranslationUsageStack[$translationIdentifier] = array(
				'NAMESPACE' => $transNamespace,
				'IDENT' => $transIdent,
				'IDENTIFIER' => $translationIdentifier,
				'BASEFILENAME' => '',
				'TRANSLATION_PARAMETER' => $arParamAliases,
				'TRANSLATION_FALLBACK' => $transFallback
			);


			$tmpTransLation = array();
			foreach($lang_list as $key => $language) {
				$tmpTransLation[$language['ABBR']] = Translation::readTranslationRaw($transNamespace, $transIdent, $language['ABBR'], $arParamAliases, $transFallback, false, array(), false);
			}
			self::$additionalTranslationUsageStack[$translationIdentifier]['TRANSLATION'] = $tmpTransLation;


			$translationUsage = CacheTemplate::readCacheTranslationUsageFile();
			if(!array_key_exists($translationIdentifier, $translationUsage['IDENT'])) {
				$translationUsage['IDENT'][$translationIdentifier] = self::$additionalTranslationUsageStack[$translationIdentifier];
				CacheTemplate::writeCacheTranslationUsageFileData($translationUsage);
			}
		}

	}
}




function callback_translation_tool_shutdown() {
	global $assoc_loaded_templates;

	if(!isset($_SESSION['ebiz_trader_translation_tool_loaded_templates'])) {
		$_SESSION['ebiz_trader_translation_tool_loaded_templates'] = array();
	}

	$_SESSION['ebiz_trader_translation_tool_loaded_templates'] = array_merge($_SESSION['ebiz_trader_translation_tool_loaded_templates'], array_keys($assoc_loaded_templates));
    if (!empty(TranslationTool::$additionalTranslationFiles)) {
        foreach (TranslationTool::$additionalTranslationFiles as $key => $usedFile) {
            $_SESSION['ebiz_trader_translation_tool_loaded_templates'][] = $usedFile;
        }
    }
}


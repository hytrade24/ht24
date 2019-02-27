<?php
/* ###VERSIONSBLOCKINLCUDE### */

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

require_once $ab_path."sys/lib.translation_tool.php";
require_once $ab_path."lib/yaml/Yaml.php";
require_once $ab_path."lib/yaml/Parser.php";
require_once $ab_path."lib/yaml/Inline.php";
require_once $ab_path."lib/yaml/Unescaper.php";
require_once $ab_path."lib/yaml/DumperMod.php";
require_once $ab_path."lib/yaml/Escaper.php";
require_once $ab_path."lib/yaml/Exception/ExceptionInterface.php";
require_once $ab_path."lib/yaml/Exception/RuntimeException.php";
require_once $ab_path."lib/yaml/Exception/ParseException.php";
require_once $ab_path."lib/yaml/Exception/DumpException.php";

class Translation {

    private static  $arTranslations = array();

    public static function readTranslationRaw($namespace, $ident, $language = null, $arParamAliases = array(), $default = null, $debugFile = false, $debugInfo = array(), $logAdditionalUsage = true) {
        global $ab_path, $nar_systemsettings;

        if ($language === null) {
            global $s_lang;
            $language = $s_lang;
        }
        if (!is_array(self::$arTranslations[$language])) {
            // Initialize language
            self::$arTranslations[$language] = array();
        }
        if (!is_array(self::$arTranslations[$language][$namespace])) {
            if (file_exists($ab_path.'cache/design/translation/'.$language.'/'.$namespace.'.yml')) {
                // Check for refresh
                require_once $ab_path.'sys/lib.cache.translation.php';
                $cacheTranslation = new CacheTranslation();
                if($nar_systemsettings['CACHE']['TEMPLATE_AUTO_REFRESH'] == 1) {
                    $translationFilename = 'translation/'.$language.'/'.$namespace.'.yml';
                    if($cacheTranslation->isFileDirty($translationFilename)) {
                        $cacheTranslation->cacheFile($translationFilename);
                    }
                }
                // Translation file found, load translations
                self::$arTranslations[$language][$namespace] = Yaml::parse(file_get_contents($ab_path.'cache/design/translation/'.$language.'/'.$namespace.'.yml'));
            } else {
                // Translation not found! Initialize empty dictionary.
                self::$arTranslations[$language][$namespace] = array();
            }
        }
        if (isset(self::$arTranslations[$language][$namespace][$ident])) {
            // Translation found! Return it.
            $translationResult = self::$arTranslations[$language][$namespace][$ident];
            // Compare with default
            if ($translationResult != $default) {
                self::loadStatistics($language);
                $fileIdent = ($debugFile ? $debugFile : "Unknown File");
                if (!is_array(self::$arTranslations[$language]["_stats"]["mismatchFallback"])) {
                    self::$arTranslations[$language]["_stats"]["mismatchFallback"] = array();
                }
                if (!is_array(self::$arTranslations[$language]["_stats"]["mismatchFallback"][$fileIdent])) {
                    self::$arTranslations[$language]["_stats"]["mismatchFallback"][$fileIdent] = array();
                }
                self::$arTranslations[$language]["_stats"]["mismatchFallback"][$fileIdent][$namespace.':'.$ident]
                    = $default."\n----------------\n".$translationResult;
            }
        } else {
            if ($default === null) {
                // Translation not found! Return error.
                $translationResult = ($default === null ? "TRANSLATION NOT FOUND: ".$language." / ".$namespace.".".$ident : $default);
                if (empty($debugInfo)) {
                    eventlog('error', 'Translation "'.$namespace.':'.$ident.'" not found!'.($debugFile !== false ? ' ('.$debugFile.')' : ''));
                } else {
                    eventlog('error', 'Translation "'.$namespace.':'.$ident.'" not found!'.($debugFile !== false ? ' ('.$debugFile.')' : ''), $debugInfo);
                }
                // Add info to statistic
                self::loadStatistics($language);
                if (!isset(self::$arTranslations[$language]["_stats"]["missingTranslation"][$namespace.':'.$ident])) {
                    self::$arTranslations[$language]["_stats"]["missingTranslation"][$namespace.':'.$ident] = ($debugFile !== false ? '('.$debugFile.') ' : '').$translationResult;
                } else {
                    self::$arTranslations[$language]["_stats"]["missingTranslation"][$namespace.':'.$ident] .= "\n".($debugFile !== false ? '('.$debugFile.') ' : '').$translationResult;
                }
            } else {
                // Translation not found! Return default translation.
                $translationResult = $default;
                // Add missing translation to cache
                self::$arTranslations[$language][$namespace][$ident] = $default;
                if (empty($debugInfo)) {
                    eventlog('info', 'Used default translation for "'.$namespace.':'.$ident.'"'.($debugFile !== false ? ' ('.$debugFile.')' : ''));
                } else {
                    eventlog('info', 'Used default translation for "'.$namespace.':'.$ident.'"'.($debugFile !== false ? ' ('.$debugFile.')' : ''), $debugInfo);
                }
                // Add info to statistic
                self::loadStatistics($language);
                self::$arTranslations[$language]["_stats"]["addedFromDefault"][$namespace.':'.$ident] = ($debugFile !== false ? ' ('.$debugFile.') ' : '').$translationResult;
            }
        }
        if ($nar_systemsettings['SITE']['TEMPLATE_TRANSLATION_TOOL'] && array_key_exists('ebizTranslationTool', $_COOKIE) && $logAdditionalUsage) {
            TranslationTool::logAdditionalTranslationUsage($namespace, $ident, $arParamAliases, $default);
        }

        return $translationResult;
    }

    public static function readTranslation($namespace, $ident, $language = null, $arParamAliases = array(), $default = null, $debugFile = false, $debugInfo = array(), $logAdditionalUsage = true) {
        $translationResult = self::readTranslationRaw($namespace, $ident, $language, $arParamAliases, $default, $debugFile, $debugInfo, $logAdditionalUsage);
        $translationResult = self::replaceTranslationParameters($translationResult, $arParamAliases);
        return $translationResult;
    }

    public static function replaceTranslationParameters($translationResult, $arParamAliases) {
        // Replace parameters
        foreach ($arParamAliases as $varSource => $varTarget) {
            $charFirst = substr($varTarget, 0, 1);
            $charLast = substr($varTarget, -1, 1);
            if ( (($charFirst == "'") && ($charLast == "'")) || (($charFirst == '"') && ($charLast == '"')) ) {
                ## REPLACE VARIABLE BY VALUE
                // Simple variable
                $translationResult = preg_replace('/\{'.preg_quote($varSource, '/').'\}/', trim($varTarget, $charFirst), $translationResult);
            } else {
                ## RENAME VARIABLE
                // Simple variable
                $translationResult = preg_replace('/\{'.preg_quote($varSource, '/').'\}/', "{".$varTarget."}", $translationResult);
                // Template function with variable as parameter
                $translationResult = preg_replace('/(\{[^\(\{\}]+\(([^\)]+\,)?)'.preg_quote($varSource, '/').'((\,[^\)]+)?\)\})/', "\$1".$varTarget."\$3", $translationResult);
                // If constructs containg the variable
                $translationResult = preg_replace('/(\{if ([^\}]*[^A-Za-z0-9_\}]+)?)'.preg_quote($varSource, '/').'(([^A-Za-z0-9_\}]+[^\}]*)?\})/', "\$1".$varTarget."\$3", $translationResult);
            }
        }
        return $translationResult;
    }

    public static function findDuplicates($language) {
        if (is_array(self::$arTranslations[$language])) {
            $tmp_content = array();
            foreach (self::$arTranslations[$language] as $namespace => $arCurrent) {
                if ($namespace == "_stats") continue;
                foreach ($arCurrent as $ident => $translation) {
                    $identFull = $namespace.":".$ident;
                    if (!is_array($tmp_content[$translation])) {
                        $tmp_content[$translation] = array();
                    }
                    if (!in_array($identFull, $tmp_content[$translation])) {
                        $tmp_content[$translation][] = $identFull;
                    }
                }
            }
            self::loadStatistics($language);
            foreach ($tmp_content as $translation => $arIdents) {
                if (count($arIdents) > 1) {
                    self::$arTranslations[$language]["_stats"]["duplicateContent"][$translation] = $arIdents;
                }
            }
        }
    }

    public static function loadStatistics($language) {
        if (!is_array(self::$arTranslations[$language]["_stats"])) {
            global $ab_path;
            if (file_exists($ab_path.'cache/design/translation/'.$language.'/_stats.yml')) {
                // Statistics file found, load...
                self::$arTranslations[$language]["_stats"] = Yaml::parse(file_get_contents($ab_path.'cache/design/translation/'.$language.'/_stats.yml'));
            } else {
                // Statistics not found! Initialize empty.
                self::$arTranslations[$language]["_stats"] = array(
                    'addedFromDefault'      => array(),
                    'duplicateContent'      => array(),
                    'mismatchFallback'      => array(),
                    'missingTranslation'    => array()
                );
            }
        }
    }

    public static function checkTranslations($language = null, &$error = false) {
        global $ab_path, $s_lang;
        if ($language === null) {
            $language = $s_lang;
        }
        if (is_array(self::$arTranslations[$language])) {
            foreach (self::$arTranslations[$language] as $namespace => $arCurrent) {
                if ($namespace == "_stats") continue;
                try {
                    $arResult = Yaml::parse(file_get_contents($ab_path.'cache/design/translation/'.$language.'/'.$namespace.'.yml'));
                } catch (ParseException $exception) {
                    $error = "Fehler in Übersetzungs-Datei '".$language."/".$namespace.".yml'! Parse-Fehler in der Zeile ".$exception->getLine().": ".$exception->getSnippet();
                }
            }
        }
    }

    public static function saveTranslations($language = null) {
        global $ab_path, $s_lang;
        if ($language === null) {
            $language = $s_lang;
        }
        if (is_array(self::$arTranslations[$language])) {
            self::findDuplicates($language);
            foreach (self::$arTranslations[$language] as $namespace => $arCurrent) {
                
                $pluginTranslationParams = new Api_Entities_EventParamContainer(array(
                    "namespace"     => $namespace,
                    "translations"  => $arCurrent
                ));
                Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::SYSTEM_CACHE_TRANSLATIONS, $pluginTranslationParams);
                if ($pluginTranslationParams->isDirty()) {
                    $arCurrent = $pluginTranslationParams->getParam("translations");
                }
                
                ksort($arCurrent);
                file_put_contents($ab_path.'cache/design/translation/'.$language.'/'.$namespace.'.yml', Yaml::dump($arCurrent, 4, 2));
            }
        }
    }

	public static function clearTranslationCache() {
		self::$arTranslations = array();
	}

}
<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.translation.php";

class CacheTemplate {

    private $lastError = false;
	protected $tmpCacheUsage = array();

    public function __construct() {

    }

	public function __destruct() {
		$this->writeCacheTranslationUsageFile();
    }
   
    public function isFileIgnored($file) {
        if (preg_match('/resources\/[^\/]+\/css\/design.css/i', $file)) {
            return true;
        }
        return false;
    }
      
    public function isFileDirty($file) {
        global $ab_path;
        $cacheIndex = $ab_path . 'cache/design/_INDEX/';

        if ($this->isFileIgnored($file)) {
            return false;
        }
        
        $indexKey = md5($file);
        $cacheIndexFile = $cacheIndex.$indexKey;

        if(!is_file($cacheIndexFile)) {
            return true;
        } else {
            $lastCached = filemtime($cacheIndexFile);
            $originalFiles = file($cacheIndexFile);
            foreach($originalFiles as $key => $originalFile) {
                $originalFile = trim($originalFile);
                $originalFileExt = preg_replace('/\.([^\.]+)$/', '.ext.$1', $originalFile);
                if ((is_file($originalFile) && filemtime($originalFile) > $lastCached)
                    || (is_file($originalFileExt) && filemtime($originalFileExt) > $lastCached)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function cacheFile($file) {
        global $ab_path, $nar_systemsettings;

        $templateName = $nar_systemsettings['SITE']['TEMPLATE'];
        $cachePath = $ab_path . 'cache/design/';
        $designPath = $ab_path . 'design/';
        $cacheIndex = $ab_path . 'cache/design/_INDEX/';

        $indexKey = md5($file);
        $cacheIndexFile = $cacheIndex.$indexKey;


        preg_match("/^([a-zA-Z0-9]+)\/([a-zA-Z]+)\/(.*)$/", $file, $matches);

        if($matches['1'] == 'module') {
            preg_match("/^([a-zA-Z0-9]+)\/tpl\/([a-zA-Z]+)\/(.*)$/", $file, $matches);

            $type = $matches['1'];
            $lang = $matches['2'];
            $filename = $matches['3'];

            if(!is_dir($cachePath . $type)) { mkdir($cachePath . $type . '/', 0777); chmod($cachePath . $type, 0777); }
            if(!is_dir($cachePath . $type. '/tpl')) { mkdir($cachePath . $type . '/tpl/', 0777); chmod($cachePath . $type . '/tpl', 0777);  }
            if(!is_dir($cachePath . $type . '/tpl/' . $lang)) { mkdir($cachePath . $type . '/tpl/' . $lang . '/', 0777); chmod($cachePath . $type . '/tpl/' . $lang, 0777);  }
        } else {
            $type = $matches['1'];
            $lang = $matches['2'];
            $filename = $matches['3'];

            if(!is_dir($cachePath . $type)) { mkdir($cachePath . $type . '/', 0777); chmod($cachePath . $type, 0777); }
            if(!is_dir($cachePath . $type . '/' . $lang)) { mkdir($cachePath . $type . '/' . $lang . '/', 0777); chmod($cachePath . $type . '/' . $lang, 0777); }
        }

        @unlink($cachePath . $file);

        $fileSource = null;
        if(file_exists($designPath . $templateName . '/' . $lang . '/'.$type.'/'.$filename)) {
            $fileSource = $designPath . $templateName. '/'.$lang.'/'.$type.'/'.$filename;
        } elseif(file_exists($designPath . $templateName . '/default/'.$type.'/'.$filename)) {
            $fileSource = $designPath . $templateName. '/default/'.$type.'/'.$filename;
        } elseif(file_exists($designPath . 'default/' . $lang . '/' . $type . '/' . $filename)) {
            $fileSource = $designPath . 'default/' . $lang . '/' . $type . '/' . $filename;
        } elseif(file_exists($designPath . 'default/default/' . $type . '/' . $filename)) {
            $fileSource = $designPath . 'default/default/' . $type . '/' . $filename;
        }

        $fileExt = preg_replace('/\.([^\.]+)$/', '.ext.$1', $file);
        $filenameExt = preg_replace('/\.([^\.]+)$/', '.ext.$1', $filename);
        $fileExtSource = null;
        if(file_exists($designPath . $templateName . '/' . $lang . '/'.$type.'/'.$filenameExt)) {
            $fileExtSource = $designPath . $templateName. '/'.$lang.'/'.$type.'/'.$filenameExt;
        } elseif(file_exists($designPath . $templateName . '/default/'.$type.'/'.$filenameExt)) {
            $fileExtSource = $designPath . $templateName. '/default/'.$type.'/'.$filenameExt;
        } elseif(file_exists($designPath . 'default/' . $lang . '/' . $type . '/' . $filenameExt)) {
            $fileExtSource = $designPath . 'default/' . $lang . '/' . $type . '/' . $filenameExt;
        } elseif(file_exists($designPath . 'default/default/' . $type . '/' . $filenameExt)) {
            $fileExtSource = $designPath . 'default/default/' . $type . '/' . $filenameExt;
        }

        if ($fileSource !== null) {
            $targetPath = pathinfo($cachePath.$file, PATHINFO_DIRNAME);
            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }
            $this->translateFile($fileSource, $cachePath . $file, $lang);
            $this->indexFile($file);
            if ($fileExtSource !== null) {
                $this->translateFile($fileExtSource, $cachePath . $fileExt, $lang);
            }
            Template_Extend_TemplateBlockExtender::extendTemplate($cachePath.$file, $cachePath.$fileExt);
        }
    }

    public function translateFile($filenameSource, $filenameTarget, $lang, $logTranslationUsage = false) {
        global $nar_systemsettings;
        $htmlContent = file_get_contents($filenameSource);
        if (preg_match_all('/\[\[\s+translation\s*:([^:]*)\:([^:]*)\:([^:]*)(\:(.+?))?\s+\]\]/s', $htmlContent, $arMatches)) {
            $arReplace = array('from' => array(), 'to' => array());
            $countMatches = count($arMatches[0]);
            for ($indexMatch = 0; $indexMatch < $countMatches; $indexMatch++) {
                // Include translation class
                global $ab_path;
                require_once $ab_path."sys/lib.translation.php";
                // Get parameters
                $fullCode = $arMatches[0][$indexMatch];
                $transNamespace = trim($arMatches[1][$indexMatch]);
                $transIdent = trim($arMatches[2][$indexMatch]);
                $transParameters = array();
                $transParametersRaw = explode(",", trim($arMatches[3][$indexMatch]));
                $paramQuoted = null;
                $paramQuotedName = null;
                $paramQuotedValue = null;
                foreach ($transParametersRaw as $transParamIndex => $transParamAlias) {
                    if ($paramQuoted === null) {
                        $arParamPair = explode("=", $transParamAlias);
                        $paramCurName = array_shift($arParamPair);
                        $paramCurValue = implode("=", $arParamPair);
                        $charFirst = substr($paramCurValue, 0, 1);
                        $charLast = substr($paramCurValue, -1, 1);
                        if (($charFirst == "'") || ($charFirst == '"')) {
                            // Quoted value
                            if ($charLast == $charFirst) {
                                // No comma in quoted value
                                $transParameters[ $paramCurName ] = $paramCurValue;
                            } else {
                                // Comma contained, search for end of value
                                $paramQuoted = $charFirst;
                                $paramQuotedName = $paramCurName;
                                $paramQuotedValue = $paramCurValue.",";
                            }
                        } else {
                            // Non-quoted value
                            $transParameters[ $paramCurName ] = $paramCurValue;
                        }
                    } else {
                        if (substr($transParamAlias, -1, 1) == $paramQuoted) {
                            // End of quoted value found
                            $transParameters[ $paramQuotedName ] = $paramQuotedValue.$transParamAlias;
                            $paramQuoted = null;
                        } else {
                            $paramQuotedValue .= $transParamAlias.",";
                        }
                    }
                }

                $transFallback = trim($arMatches[5][$indexMatch]);
                // Translate given phrase
                $transResultRaw = Translation::readTranslationRaw($transNamespace, $transIdent, $lang, $transParameters, $transFallback, str_replace($ab_path, "", $filenameSource), array(), false);
                $transResult = Translation::replaceTranslationParameters($transResultRaw, $transParameters);
                // Replace by translation
                if (!$nar_systemsettings["SITE"]["TEMPLATE_TRANSLATION_DEBUG"]) {
					$replaceWith = $transResult;
                } else {
					$replaceWith = "[[".$transNamespace.":".$transIdent."]]";
                }

				if ($logTranslationUsage) {
					$this->cacheTranslationUsage($transNamespace, $transIdent, $lang, $transResultRaw, $transParameters, $transFallback, $filenameTarget);
				}


				$arReplace['from'][] = $fullCode;
				$arReplace['to'][] = $replaceWith;
            }
            $htmlContent = str_replace($arReplace['from'], $arReplace['to'], $htmlContent);
        }
        file_put_contents($filenameTarget, $htmlContent);
    }

    public function cacheAll()
    {
        global $ab_path, $nar_systemsettings, $db;

        $templateName = $nar_systemsettings['SITE']['TEMPLATE'];
        $cachePath = $ab_path . 'cache/design/';
        $designPath = $ab_path . 'design/';

        // remove old cache
        if (is_dir($cachePath.'module')) {
            system('rm -R ' . $cachePath.'module');
        }
        if (is_dir($cachePath.'resources')) {
            system('rm -R ' . $cachePath.'resources');
        }
        if (is_dir($cachePath.'skin')) {
            system('rm -R ' . $cachePath.'skin');
        }
        if (is_dir($cachePath.'tpl')) {
            system('rm -R ' . $cachePath.'tpl');
        }
        if (is_dir($cachePath.'mail')) {
            system('rm -R ' . $cachePath.'mail');
        }

        if(!is_dir($cachePath)) { mkdir($cachePath); chmod($cachePath, 0777); }

        // default
        $ar_lang = $db->fetch_table("select BITVAL,ABBR from lang");
        for ($i = 0; $i < count($ar_lang); $i++) {
            $lang = $ar_lang[$i]['ABBR'];

            if (!is_dir($cachePath . 'skin/')) {
                mkdir($cachePath . 'skin/');
                chmod($cachePath . 'skin', 0777);
            }
            if (!is_dir($cachePath . 'tpl/')) {
                mkdir($cachePath . 'tpl/');
                chmod($cachePath . 'tpl', 0777);
            }
            if (!is_dir($cachePath . 'module/')) {
                mkdir($cachePath . 'module/');
                chmod($cachePath . 'module', 0777);
            }
            if (!is_dir($cachePath . 'resources/')) {
                mkdir($cachePath . 'resources/');
                chmod($cachePath . 'resources', 0777);
            }
            if (!is_dir($cachePath . 'skin/' . $lang . '/')) {
                mkdir($cachePath . 'skin/' . $lang . '/');
                chmod($cachePath . 'skin/' . $lang, 0777);
            }
            if (!is_dir($cachePath . 'tpl/' . $lang . '/')) {
                mkdir($cachePath . 'tpl/' . $lang . '/');
                chmod($cachePath . 'tpl/' . $lang, 0777);
            }
            if (!is_dir($cachePath . 'mail/' . $lang . '/')) {
                mkdir($cachePath . 'mail/' . $lang . '/', 0777, true);
                chmod($cachePath . 'mail/' . $lang, 0777);
            }
            if (!is_dir($cachePath . 'module/tpl')) {
                mkdir($cachePath . 'module/tpl');
                chmod($cachePath . 'module/tpl', 0777);
            }
            if (!is_dir($cachePath . 'module/tpl/' . $lang . '/')) {
                mkdir($cachePath . 'module/tpl/' . $lang . '/');
                chmod($cachePath . 'module/tpl/' . $lang, 0777);
            }
            if (!is_dir($cachePath . 'resources/' . $lang . '/')) {
                mkdir($cachePath . 'resources/' . $lang . '/');
                chmod($cachePath . 'resources/' . $lang, 0777);
            }

            // ==============================
            // Get available skins
            $arSkins = array();
            $arSkinsDirs = array(
                $designPath . 'default/default/skin/',
                $designPath . 'default/' . $lang . '/skin/',
                $designPath . $templateName . '/default/skin/',
                $designPath . $templateName . '/' . $lang . '/skin/'
            );
            foreach ($arSkinsDirs as $dirIndex => $dirPath) {
                if (is_dir($dirPath)) {
                    $dirCurrent = dir($dirPath);
                    while (false !== ($filename = $dirCurrent->read())) {
                        if (preg_match("/([A-Za-z0-9-_]+\.html?)/", $filename, $arMatches)) {
                            $arSkins[ $filename ] = $dirPath.$filename;
                        }
                    }
                }
            }
            // Translate all existing templates and save them into the cache directory
            foreach ($arSkins as $filename => $filenameSource) {
                $this->translateFile($filenameSource, $cachePath . 'skin/' . $lang . '/'.$filename, $lang, true);
            }
            // ==============================
            // Get available templates
            $arTemplates = array();
            $arTemplatesExt = array();
            $arTemplatesDirs = array(
                $designPath . 'default/default/tpl/',
                $designPath . 'default/' . $lang . '/tpl/',
                $designPath . $templateName . '/default/tpl/',
                $designPath . $templateName . '/' . $lang . '/tpl/'
            );
            foreach ($arTemplatesDirs as $dirIndex => $dirPath) {
                if (is_dir($dirPath)) {
                    $dirCurrent = dir($dirPath);
                    while (false !== ($filename = $dirCurrent->read())) {
                        if (preg_match("/([A-Za-z0-9-_]+\.ext\.html?)/", $filename, $arMatches)) {
                            $arTemplatesExt[ $filename ] = $dirPath.$filename;
                        } else if (preg_match("/([A-Za-z0-9-_]+\.html?)/", $filename, $arMatches)) {
                            $arTemplates[ $filename ] = $dirPath.$filename;
                        }
                    }
                }
            }
            // Translate all existing templates and save them into the cache directory
            foreach ($arTemplates as $filename => $filenameSource) {
                $this->translateFile($filenameSource, $cachePath . 'tpl/' . $lang . '/'.$filename, $lang, true);
            }
            // Merge block for extended templates
            foreach ($arTemplatesExt as $filenameExt => $filenameExtSource) {
                $filename = str_replace(".ext.htm", ".htm", $filenameExt);
                if (array_key_exists($filename, $arTemplates)) {
                    $filenameCache = $cachePath.'tpl/'.$lang.'/'.$filename;
                    $filenameSource = $arTemplates[$filename];
                    // Translate extension
                    $filenameExtCache = $cachePath.'tpl/'.$lang.'/'.$filenameExt;
                    $this->translateFile($filenameExtSource, $filenameExtCache, $lang, true);
                    // Extend cached template
                    Template_Extend_TemplateBlockExtender::extendTemplate($filenameCache, $filenameExtCache);
                }
            }
            // ==============================
            // Get available html mails
            $arMails = array();
            $arMailsDirs = array(
                $designPath . 'default/default/mail/',
                $designPath . 'default/' . $lang . '/mail/',
                $designPath . $templateName . '/default/mail/',
                $designPath . $templateName . '/' . $lang . '/mail/'
            );
            foreach ($arMailsDirs as $dirIndex => $dirPath) {
                if (is_dir($dirPath)) {
                    $dirCurrent = dir($dirPath);
                    while (false !== ($filename = $dirCurrent->read())) {
                        if (preg_match("/([A-Za-z0-9-_]+\.html?)/", $filename, $arMatches)) {
                            $arMails[ $filename ] = $dirPath.$filename;
                        }
                    }
                }
            }
            // Translate all existing templates and save them into the cache directory
            foreach ($arMails as $filename => $filenameSource) {
                $this->translateFile($filenameSource, $cachePath . 'mail/' . $lang . '/'.$filename, $lang, true);
            }
            // ==============================
            // Get available module templates
            $arTemplatesModule = array();
            $arTemplatesModuleDirs = array(
                $designPath . 'default/default/module/',
                $designPath . 'default/' . $lang . '/module/',
                $designPath . $templateName . '/default/module/',
                $designPath . $templateName . '/' . $lang . '/module/'
            );
            foreach ($arTemplatesModuleDirs as $dirIndex => $dirPath) {
                if (is_dir($dirPath)) {
                    $dirCurrent = dir($dirPath);
                    while (false !== ($filename = $dirCurrent->read())) {
                        if (preg_match("/([A-Za-z0-9-_]+\.html?)/", $filename, $arMatches)) {
                            $arTemplatesModule[ $filename ] = $dirPath.$filename;
                        }
                    }
                }
            }
            // Translate all existing templates and save them into the cache directory
            foreach ($arTemplatesModule as $filename => $filenameSource) {
                $this->translateFile($filenameSource, $cachePath . 'module/tpl/' . $lang . '/'.$filename, $lang, true);
            }
            // Copy resources
            system('cp -R ' . $designPath . 'default/default/resources/* ' . $cachePath . 'resources/' . $lang . '/');
            system('cp -R ' . $designPath . 'default/' . $lang . '/resources/* ' . $cachePath . 'resources/' . $lang . '/');

            if ($templateName !== '' && file_exists($designPath . $templateName)) {
                system('cp -R ' . $designPath . $templateName . '/default/resources/* ' . $cachePath . 'resources/' . $lang . '/');
                system('cp -R ' . $designPath . $templateName . '/' . $lang . '/resources/* ' . $cachePath . 'resources/' . $lang . '/');
            }
            // ==============================
            // Translate javascript files
            $jsPath = $cachePath . 'resources/' . $lang . '/js/';
            $jsFiles = array();
            $this->readDirectoryRecursive($jsPath, $jsFiles, $jsPath);
            foreach ($jsFiles as $jsFileIndex => $jsFile) {
                $this->translateFile($jsFile[0], $jsFile[0], $lang, true);
            }
            // Cache plugin templates
            $pluginTranslationParams = new Api_Entities_EventParamContainer(array(
                "lang"              => $lang,
                "cacheTemplate"     => $this,
                "logTranslation"    => true
            ));
            Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::SYSTEM_CACHE_TEMPLATES, $pluginTranslationParams);
            // Change file rights
            system("chmod -R 777 $cachePath");
            // Update missing translations
            Translation::saveTranslations($lang);
            // Update missing translations
            $error = $this->lastError;
            Translation::checkTranslations($lang, $error);
            $this->lastError = $error;
        }

        $this->indexAll();
    }

    public function indexFile($file) {
        global $ab_path, $nar_systemsettings;

        $templateName = $nar_systemsettings['SITE']['TEMPLATE'];
        $cachePath = $ab_path . 'cache/design/';
        $designPath = $ab_path . 'design/';
        $cacheIndex = $ab_path . 'cache/design/_INDEX/';

        $indexKey = md5($file);
        $cacheIndexFile = $cacheIndex.$indexKey;

        preg_match("/^([a-zA-Z0-9]+)\/([a-zA-Z]+)\/(.*)$/", $file, $matches);
        $type = $matches['1'];
        $lang = $matches['2'];
        $filename = $matches['3'];

        if($matches['1'] == 'module') {
        	preg_match("/^([a-zA-Z0-9]+)\/tpl\/([a-zA-Z]+)\/(.*)$/", $file, $matches);
        	$type = $matches['1'];
        	$lang = $matches['2'];
        	$filename = $matches['3'];
        }

        if(!is_dir($cacheIndex)) { mkdir($cacheIndex, 0777); chmod($cacheIndex, 0777); }

        $originalFiles = array(
            $designPath . $templateName. '/'.$lang.'/'.$type.'/'.$filename,
            $designPath . $templateName . '/default/'.$type.'/'.$filename,
            $designPath . 'default/' . $lang . '/' . $type . '/' . $filename,
            $designPath . 'default/default/' . $type . '/' . $filename
        );


        file_put_contents($cacheIndexFile, implode("\n", $originalFiles));
    }

    public function indexAll() {
        global $ab_path, $nar_systemsettings;

        $templateName = $nar_systemsettings['SITE']['TEMPLATE'];
        $cachePath = $ab_path . 'cache/design/';
        $designPath = $ab_path . 'design/';

        $files = array();
        $this->readDirectoryRecursive($designPath, $files, $designPath);

        foreach ($files as $key => $file) {
            preg_match("/^([a-zA-Z0-9]+)\/([a-zA-Z]+)\/([a-zA-Z0-9]+)\/(.*)$/", $file['1'], $matches);

            $type = $matches['3'];
            $lang = $matches['2'];
            $filename = $matches['4'];

            $this->indexFile($type . '/' . $lang . '/' . $filename);
        }
    }

	protected function cacheTranslationUsage($transNamespace, $transIdent, $lang, $transResult, $transParameters, $transFallback, $filenameSource) {
		global $ab_path;
		$baseFilename = str_replace($ab_path, "", $filenameSource);

		$translationIdentifier = $transNamespace.':'.$transIdent;

		$data = array(
			'NAMESPACE' => $transNamespace,
			'IDENT' => $transIdent,
			'IDENTIFIER' => $translationIdentifier,
			'BASEFILENAME' => $baseFilename,
			'TRANSLATION_PARAMETER' => $transParameters,
			'TRANSLATION_FALLBACK' => $transFallback
		);

		if(isset($this->tmpCacheUsage['IDENT'][$translationIdentifier]['TRANSLATION'])) {
			$data['TRANSLATION'] = $this->tmpCacheUsage['IDENT'][$translationIdentifier]['TRANSLATION'];
		}

		if(!isset($this->tmpCacheUsage['IDENT'][$translationIdentifier]['TRANSLATION']) || (isset($this->tmpCacheUsage['IDENT'][$translationIdentifier]['TRANSLATION']) && !array_key_exists($lang, $this->tmpCacheUsage['IDENT'][$translationIdentifier]['TRANSLATION']))) {
			$data['TRANSLATION'][$lang] = $transResult;
		}


		$this->tmpCacheUsage['FILENAME'][$data['BASEFILENAME']][$translationIdentifier] = $data;
		$this->tmpCacheUsage['IDENT'][$translationIdentifier] = $data;
	}

	protected function writeCacheTranslationUsageFile() {
		return self::writeCacheTranslationUsageFileData($this->tmpCacheUsage);
	}

	static function writeCacheTranslationUsageFileData($data) {
		global $ab_path;

		$cacheFile = $ab_path.'cache/design/translation/usage';

		if(count($data) != 0) {
			file_put_contents($cacheFile, serialize($data));
		}
	}


	static function readCacheTranslationUsageFile() {
		global $ab_path;

		$cacheFile = $ab_path.'cache/design/translation/usage';
		return unserialize(file_get_contents($cacheFile));

	}


    private function readDirectoryRecursive($dir, &$fileinfo = array(), $basedir = '') {

        foreach (glob($dir . '*') as $file) {
            if (is_dir($file)) {
                $this->readDirectoryRecursive($file.'/', $fileinfo, $basedir);
            } else {
                $fileinfo[] = array($file, str_replace($basedir, '', $file), basename($file), filesize($file), filemtime($file));
            }
        }
        return $fileinfo;
    }


    static function getHeadFile($file) {
        global $ab_path, $nar_systemsettings;

        $cacheTemplate = new CacheTemplate();
        if($nar_systemsettings['CACHE']['TEMPLATE_AUTO_REFRESH'] == 1) {
            if($cacheTemplate->isFileDirty($file)) {
                $cacheTemplate->cacheFile($file);
            }
        }

        return $ab_path.'cache/design/'.$file;

    }


    static function getSourceFile($cacheFile) {
        global $ab_path, $nar_systemsettings;

        $templateName = $nar_systemsettings['SITE']['TEMPLATE'];
        $cacheFile = ltrim(str_replace($ab_path, "", $cacheFile), "/");
        if (strpos($cacheFile, "sys/Api/Plugins/") === 0) {
            // Plugin template
            $tplFileRelative = str_replace("sys/Api/Plugins/", "", $cacheFile);
            list($pluginName, $pluginCache, $pluginBase, $pluginLang, $pluginFile) = explode("/", $tplFileRelative);
            if (file_exists($ab_path."sys/Api/Plugins/".$pluginName."/".$pluginBase."/".$pluginLang."/".$pluginFile)) {
                return $ab_path."sys/Api/Plugins/".$pluginName."/".$pluginBase."/".$pluginLang."/".$pluginFile;
            }
            if (file_exists($ab_path."sys/Api/Plugins/".$pluginName."/".$pluginBase."/default/".$pluginFile)) {
                return $ab_path."sys/Api/Plugins/".$pluginName."/".$pluginBase."/default/".$pluginFile;
            }
        } else {
            // Regular template
            $tplFileRelative = str_replace("cache/design/", "", $cacheFile);
            $arFileRelative = explode("/", $tplFileRelative);
            $tplType = array_shift($arFileRelative);
            if ($tplType == "module") {
                //Remove "tpl" part of "module/tpl/de/example.htm"
                array_shift($arFileRelative);
            }
            $tplLang = array_shift($arFileRelative);
            $tplFile = implode("/", $arFileRelative);
            //list($tplType, $tplLang, $tplFile) = explode("/", $tplFileRelative);
            if (file_exists($ab_path."design/".$templateName."/".$tplLang."/".$tplType."/".$tplFile)) {
                return $ab_path."design/".$templateName."/".$tplLang."/".$tplType."/".$tplFile;
            }
            if (file_exists($ab_path."design/".$templateName."/default/".$tplType."/".$tplFile)) {
                return $ab_path."design/".$templateName."/default/".$tplType."/".$tplFile;
            }
            if (file_exists($ab_path."design/default/".$tplLang."/".$tplType."/".$tplFile)) {
                return $ab_path."design/default/".$tplLang."/".$tplType."/".$tplFile;
            }
            if (file_exists($ab_path."design/default/default/".$tplType."/".$tplFile)) {
                return $ab_path."design/default/default/".$tplType."/".$tplFile;
            }
        }
        return $ab_path.$cacheFile;
    }

    /**
     * Get the last error message
     * @return  string|bool     The error message (false if no error occured)
     */
    public function getLastError() {
        return $this->lastError;
    }
}

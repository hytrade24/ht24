<?php
/* ###VERSIONSBLOCKINLCUDE### */

use Symfony\Component\Yaml\Yaml;

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

class CacheTranslation {


    public function __construct() {

    }

    public function isFileDirty($file) {
        global $ab_path;
        $cacheIndex = $ab_path . 'cache/design/_INDEX/';

        $indexKey = md5($file);
        $cacheIndexFile = $cacheIndex.$indexKey;

        if(!is_file($cacheIndexFile)) {
            return true;
        } else {
            $lastCached = filemtime($cacheIndexFile);
            $originalFiles = file($cacheIndexFile);
            foreach($originalFiles as $key => $originalFile) {
                $originalFile = trim($originalFile);
                if(is_file($originalFile) && filemtime($originalFile) > $lastCached) {
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
        $type = $matches['1'];
        $lang = $matches['2'];
        $filename = $matches['3'];
        if(!preg_match('/^(.+)\.yml$/', $filename, $arMatchesNamespace)) {
            return false;
        }
        $namespace = $arMatchesNamespace[1];

        if(!is_dir($cachePath . $type)) { mkdir($cachePath . $type . '/', 0777); chmod($cachePath . $type, 0777); }
        if(!is_dir($cachePath . $type . '/' . $lang)) { mkdir($cachePath . $type . '/' . $lang . '/', 0777); chmod($cachePath . $type . '/' . $lang, 0777); }

        @unlink($cachePath . $file);

        $transFileDirs = array(
            $designPath . 'default/default/translation/',
            $designPath . 'default/' . $lang . '/translation/',
            $designPath . $templateName . '/default/translation/',
            $designPath . $templateName . '/' . $lang . '/translation/'
        );
        // Cache translations
        $arTranslations = array();
        foreach ($transFileDirs as $transDirIndex => $transDir) {
            $transFileBase = $transDir.$namespace;
            if (file_exists($transFileBase.'.yml')) {
                $arTransNew = Yaml::parse(file_get_contents($transFileBase.'.yml'));
                if (is_array($arTransNew)) {

                    $arTranslations = array_merge($arTranslations, $arTransNew);
                }
                // Add further translation files if available (namespace.1.yml, namespace.2.yml, ...)
                for ($fileIndex = 1; file_exists($transFileBase.'.'.$fileIndex.'.yml'); $fileIndex++) {
                    // Translation file found, load translations
                    $arTransNew = Yaml::parse(file_get_contents($transFileBase.'.'.$fileIndex.'.yml'));
                    if (is_array($arTransNew)) {
                        $arTranslations = array_merge($arTranslations, $arTransNew);
                    }
                }
            }
        }
        file_put_contents($cachePath . 'translation/' . $lang . '/'.$namespace.'.yml', Yaml::dump($arTranslations, 2, 2));
        $this->indexFile($file);
    }

    public function cacheAll()
    {
        global $ab_path, $nar_systemsettings, $db;

        $templateName = $nar_systemsettings['SITE']['TEMPLATE'];
        $cachePath = $ab_path . 'cache/design/';
        $designPath = $ab_path . 'design/';

        // remove old cache
        system('rm -R ' . $cachePath.'_INDEX');
        system('rm -R ' . $cachePath.'translation');

        if(!is_dir($cachePath)) { mkdir($cachePath); chmod($cachePath, 0777); }

        // default
        $ar_lang = $db->fetch_table("select BITVAL,ABBR from lang");
        for ($i = 0; $i < count($ar_lang); $i++) {
            $lang = $ar_lang[$i]['ABBR'];

            if (!is_dir($cachePath . 'translation/')) {
                mkdir($cachePath . 'translation/');
                chmod($cachePath . 'translation', 0777);
            }
            if (!is_dir($cachePath . 'translation/' . $lang . '/')) {
                mkdir($cachePath . 'translation/' . $lang . '/');
                chmod($cachePath . 'translation/' . $lang, 0777);
            }

            $transFileDirs = array(
                $designPath . 'default/default/translation/',
                $designPath . 'default/' . $lang . '/translation/',
                $designPath . $templateName . '/default/translation/',
                $designPath . $templateName . '/' . $lang . '/translation/'
            );
            $arNamespaces = array();
            // Get translation namespaces
            foreach ($transFileDirs as $transDirIndex => $transDir) {
                if (is_dir($transDir)) {
                    $dirTranslations = dir($transDir);
                    while (false !== ($filename = $dirTranslations->read())) {
                        if (preg_match("/^([A-Za-z0-9-_]+)\.yml$/", $filename, $arMatches) && (!in_array($arMatches[1], $arNamespaces))) {
                            $arNamespaces[] = $arMatches[1];
                        }
                    }
                }
            }
            // Cache translations
            foreach ($arNamespaces as $index => $namespace) {
                $arTranslations = array();
                foreach ($transFileDirs as $transDirIndex => $transDir) {
                    $transFileBase = $transDir.$namespace;
                    if (file_exists($transFileBase.'.yml')) {
                        $arTransNew = Yaml::parse(file_get_contents($transFileBase.'.yml'));
                        if (is_array($arTransNew)) {

                            $arTranslations = array_merge($arTranslations, $arTransNew);
                        }
                        // Add further translation files if available (namespace.1.yml, namespace.2.yml, ...)
                        for ($fileIndex = 1; file_exists($transFileBase.'.'.$fileIndex.'.yml'); $fileIndex++) {
                            // Translation file found, load translations
                            $arTransNew = Yaml::parse(file_get_contents($transFileBase.'.'.$fileIndex.'.yml'));
                            if (is_array($arTransNew)) {
                                $arTranslations = array_merge($arTranslations, $arTransNew);
                            }
                        }
                    }
                }
                $pluginTranslationParams = new Api_Entities_EventParamContainer(array(
                    "namespace"     => $namespace,
                    "translations"  => $arTranslations
                ));
                Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::SYSTEM_CACHE_TRANSLATIONS, $pluginTranslationParams);
                if ($pluginTranslationParams->isDirty()) {
                    $arTranslations = $pluginTranslationParams->getParam("translations");
                }
                
                ksort($arTranslations);
                file_put_contents($cachePath . 'translation/' . $lang . '/'.$namespace.'.yml', Yaml::dump($arTranslations, 2, 2));
            }

            system("chmod -R 777 $cachePath");
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
        	preg_match("/^([a-zA-Z0-9]+)\/translation\/([a-zA-Z]+)\/(.*)$/", $file, $matches);
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
}

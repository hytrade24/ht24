<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 08.09.14
 * Time: 11:58
 */

abstract class Api_TraderApiPlugin implements Api_TraderApiPluginInterface {

    private $apiHandler;
    
    protected $db;
    protected $pluginConfiguration;
    protected $pluginConfigurable;
    protected $pluginName;
    protected $pluginPath;

    function __construct(Api_TraderApiHandler $apiHandler, $pluginBasePath = NULL) {
        $this->apiHandler = $apiHandler;
        $this->db = $apiHandler->getDb();
        // Get plugin path
        $this->pluginName = str_replace(array("Api_Plugins_", "_Plugin"), "", get_class($this));
        if ($pluginBasePath === NULL) {
            $this->pluginPath = $GLOBALS['ab_path']."sys/Api/Plugins/".$this->pluginName."/";
        } else {
            $this->pluginPath = $pluginBasePath.$this->pluginName."/";
        }
        $this->pluginConfiguration = array();
        if (file_exists($this->pluginPath."config.json")) {
            $this->pluginConfiguration = json_decode(file_get_contents($this->pluginPath."config.json"), true);
        }
        $this->pluginConfigurable = $this->utilGetTemplateExists("config.htm");
        // Bind translation update event
        if (is_dir($this->pluginPath."tpl")) {
            if (!is_dir($this->pluginPath."cache/tpl")) {
                mkdir($this->pluginPath."cache/tpl", 0777, true);
            }
            Template_Twig::getTwigLoader()->addPath($this->pluginPath."cache/tpl", $this->pluginName);
            $this->registerEvent(Api_TraderApiEvents::SYSTEM_CACHE_TEMPLATES, "_templates_systemCacheTemplates");
        }
    }

    /**
     * Return the loaded instance of this plugin (or false if unknown/not loaded)
     * @param ebiz_db   $db
     * @return Api_TraderApiPlugin|bool
     */
    public static function getInstance(ebiz_db $db = NULL) {
        if (preg_match("/^Api_Plugins_(.+)_Plugin$/", get_called_class(), $arMatch)) {
            return Api_TraderApiHandler::getInstance($db)->getPlugin($arMatch[1]);
        }
        return false;
    }

    /**
     * Returns the plugins name
     * @return string
     */
    public function getName() {
        return $this->pluginName;
    }

    /**
     * Returns the plugins description
     * @return string
     */
    public function getDescription() {
        if (file_exists($this->pluginPath."description.txt")) {
            return file_get_contents($this->pluginPath."description.txt");
        } else {
            return "Keine Beschreibung vorhanden.";
        }
    }

    /**
     * Returns the path to the plugins main directory
     * @return string
     */
    public function getPath() {
        return $this->pluginPath;
    }
    
    /**
     * Get the cache storage object for the given cache directory
     * @param string    $cacheDirectory
     * @return Api_CacheStorage
     */
    public function getCacheStorage($cacheDirectory) {
        return Api_CacheStorage::getInstance($this->pluginPath."cache/".$cacheDirectory);
    }

    /**
     * Returns the configuration of this plugin
     * @return array
     */
    public function getConfiguration() {
        return $this->pluginConfiguration;
    }

    /**
     * Returns the configuration form for this plugin
     * @return Template
     */
    public function getConfigurationForm() {
        $arConfig = $this->pluginConfiguration;
        $tplConfig = $this->utilGetTemplate("config.htm");
        $tplConfig->addvar("PLUGIN_NAME", $this->pluginName);
        $tplConfig->addvars(array_merge(array_flatten($arConfig, false), array_flatten($arConfig, true)), "CONFIG_");
        return $tplConfig;
    }

    /**
     * Returns some specific information about the plugins configuration
     * @param $arParams (optional) Array with parameters for this request.
     * @return mixed
     */
    public function getConfigurationAjax($arParams = array()) {
        return null;
    }

    /**
     * Returns true if this plugin has a configuration form
     * @return bool
     */
    public function isConfigurable() {
        return $this->pluginConfigurable;
    }

    /**
     * Returns true if this plugin is a system-plugin which can not be disabled!
     * @return bool
     */
    public function isSystemPlugin() {
        return false;
    }

    /**
     * Updates the configuration of the plugin (usually done by posting the plugin config form)
     * @param array $arConfig
     * @return bool
     */
    public function setConfiguration($arConfig) {
        if (is_array($arConfig)) {
            $this->pluginConfiguration = $arConfig;
        } else {
            return false;
        }
        return $this->saveConfiguration();
    }

    /**
     * Writes the configuration of the plugin
     * @return bool
     */
    public function saveConfiguration() {
        file_put_contents($this->pluginPath."config.json", json_encode($this->pluginConfiguration));
        return true;
    }

    /**
     * Registers an event callback
     * @param string    $eventName
     * @param string    $eventCallback
     * @return bool
     */
    protected function registerEvent($eventName, $eventCallback) {
        if (!method_exists($this, $eventCallback)) {
            // Callback not found! Failed!
            return false;
        }
        $this->apiHandler->registerEvent($eventName, $this, $eventCallback);
    }

    /**
     * Get a template object for the given file
     * @param string        $templateFilename
     * @param string|null   $language
     * @return Template
     */
    function utilGetTemplate($templateFilename, $language = null) {
        if ($language === null) {
            $language = $GLOBALS['s_lang'];
        }
        // Create template and return result
        $tplResult = null;
        if (preg_match("/\.twig\.html?$/", $templateFilename)) {
            $this->utilRecacheTemplate($templateFilename);
            $tplResult = new Template_Twig("@".$this->pluginName."/".$language."/".$templateFilename, array(
                "language" => $language
            ));
        } else {
            $tplResult = new Template("tpl/".$language."/empty.htm");
            $tplResult->filename = $this->utilGetTemplateCachedPath($templateFilename);
            $tplResult->tpl_text = $this->utilGetTemplateRaw($templateFilename, $language);
        }
        return $tplResult;
    }

    /**
     * Get a list of template objects filled with the given row variables (similar to $template->addlist)
     * @param string        $templateFilename
     * @param array         $arRows
     * @param string|null   $language
     * @return array
     */
    function utilGetTemplateList($templateFilename, $arRows, $language = null, $flattenRowArrays = false) {
        if ($language === null) {
            $language = $GLOBALS['s_lang'];
        }
        $arResult = array();
        foreach ($arRows as $rowIndex => $arRow) {
            if ($arRow instanceof JsonSerializable) {
                $arRow = $arRow->jsonSerialize();
            }
            if ($flattenRowArrays) {
                $arRow = array_merge($arRow, array_flatten($arRow));
            }
            // Create template and add to list
            $tplRow = new Template("tpl/".$language."/empty.htm");
            $tplRow->tpl_text = $this->utilGetTemplateRaw($templateFilename, $language);
            $tplRow->addvar("i", $rowIndex);
            $tplRow->addvars($arRow);
            $arResult[] = $tplRow;
        }
        return $arResult;
    }

    /**
     * Check if the given template exists
     * @param string        $templateFilename
     * @param string|null   $language
     * @return bool
     */
    function utilGetTemplateExists($templateFilename, $language = null) {
        if ($language === null) {
            $language = $GLOBALS['s_lang'];
        }
        // Check for cached template
        $templateBasePath = $this->pluginPath."tpl/";
        $templateBaseFilenameAbs = $templateBasePath."default/".$templateFilename;
        if (file_exists($templateBasePath.$language."/".$templateFilename)) {
            $templateBaseFilenameAbs = $templateBasePath.$language."/".$templateFilename;
        }
        if (!file_exists($templateBaseFilenameAbs)) {
            // Template not found
            return false;
        }
        return true;
    }

    /**
     * Returns the full filename (including path) of the given template/resource
     * @param string        $templateFilename
     * @param string|null   $language
     * @return string
     */
    function utilGetTemplatePath($templateFilename, $language = null) {
        if ($language === null) {
            $language = $GLOBALS['s_lang'];
        }
        $templateBasePath = $this->pluginPath."tpl/";
        $templateBaseFilenameAbs = $templateBasePath."default/".$templateFilename;
        if (file_exists($templateBasePath.$language."/".$templateFilename)) {
            $templateBaseFilenameAbs = $templateBasePath.$language."/".$templateFilename;
        }
        return $templateBaseFilenameAbs;
    }

    /**
     * Returns the full filename (including path) of the given template/resource
     * @param string        $templateFilename
     * @param string|null   $language
     * @return string
     */
    function utilGetTemplateCachedPath($templateFilename, $language = null) {
        if ($language === null) {
            $language = $GLOBALS['s_lang'];
        }
        $templateCachePath = $this->pluginPath."cache/tpl/".$language."/";
        $templateCacheFilenameAbs = $templateCachePath.$templateFilename;
        return $templateCacheFilenameAbs;
    }

    /**
     * Returns the raw file contents of the given template
     * @param string        $templateFilename
     * @param string|null   $language
     * @return string
     */
    function utilGetTemplateRaw($templateFilename, $language = null) {
        // Language fallback
        if ($language === null) {
            $language = $GLOBALS['s_lang'];
        }
        // Ensure cache is up to date
        $this->utilRecacheTemplate($templateFilename, $language);
        // Get template cache path
        $templateCachePath = $this->pluginPath."cache/tpl/".$language."/";
        $templateCacheFilenameAbs = $templateCachePath.$templateFilename;
        // Translation-Tool informieren.
        require_once $GLOBALS["ab_path"]."sys/lib.translation_tool.php";
        TranslationTool::logAdditionalTranslationFile($templateCacheFilenameAbs);
        // Return template
        return file_get_contents($templateCacheFilenameAbs);
    }


    /**
     * Returns the raw file contents of the given template
     * @param string        $templateFilename
     * @param string|null   $language
     * @return string
     */
    function utilGetTemplateTranslate($templateFilename, $language = null, CacheTemplate $cacheTemplate = null, $logTranslations = false) {
        if ($language === null) {
            $language = $GLOBALS['s_lang'];
        }
        // Check for cached template
        $templateCacheFilenameAbs = $this->pluginPath."cache/tpl/".$language."/".$templateFilename;
        $templateCachePathInfo = pathinfo($templateCacheFilenameAbs);
        $templateCachePath = $templateCachePathInfo["dirname"]."/";
        if (!is_dir($templateCachePath)) {
            mkdir($templateCachePath, 0777, true);
        }
        $templateBasePath = $this->pluginPath."tpl/";
        $templateBaseFilenameAbs = $templateBasePath.$language."/".$templateFilename;
        if (!file_exists($templateBaseFilenameAbs)) {
            $templateBaseFilenameAbs = $templateBasePath."default/".$templateFilename;
        }
        // (Re)write cache file
        if ($cacheTemplate === null) {
            require_once $GLOBALS['ab_path']. 'sys/lib.cache.template.php';
            $cacheTemplate = new CacheTemplate();
        }
        $cacheTemplate->translateFile($templateBaseFilenameAbs, $templateCacheFilenameAbs, $language, $logTranslations);
        return true;
    }

    /**
     * Returns the raw file contents of the given template
     * @param string        $templateFilename
     * @param string|null   $language
     * @return string
     */
    function utilRecacheTemplate($templateFilename, $language = null) {
        if ($language === null) {
            $language = $GLOBALS['s_lang'];
        }
        // Check for cached template
        $templateCachePath = $this->pluginPath."cache/tpl/".$language."/";
        $templateCacheFilenameAbs = $templateCachePath.$templateFilename;
        $templateBasePath = $this->pluginPath."tpl/";
        $templateBaseFilenameAbs = $templateBasePath."default/".$templateFilename;
        if (file_exists($templateBasePath.$language."/".$templateFilename)) {
            $templateBaseFilenameAbs = $templateBasePath.$language."/".$templateFilename;
        }
        if (!file_exists($templateBaseFilenameAbs)) {
            // Template not found!!
            return "Plugin template not found: ".$templateFilename." / ".$templateBaseFilenameAbs;
        }
        if (!file_exists($templateCacheFilenameAbs) ||
            ($GLOBALS['nar_systemsettings']['CACHE']['TEMPLATE_AUTO_REFRESH'] && filemtime($templateCacheFilenameAbs) < filemtime($templateBaseFilenameAbs))) {
            $this->utilGetTemplateTranslate($templateFilename, $language);
        }
    }

    /**
     * Escape the given value for usage within CSV
     * @param string    $value
     * @return mixed|string
     */
    function utilEscapeCsv($value) {
        $value = str_replace('"', '""', $value);
        if (preg_match('/,/', $value) ||  preg_match("/\n/", $value) || preg_match('/"/', $value)) {
            return '"'.$value.'"';
        } else {
            return $value;
        }
    }

    /**
     * Transform the given array into a CSV line
     * @param array     $arAssoc
     * @param array     $arLabels
     * @param string    $seperator
     * @return string
     */
    function utilTransformCsv($arAssoc, $arLabels, $seperator = ";") {
        $arCSV = array();
        foreach ($arLabels as $labelIndex => $labelName) {
            if (array_key_exists($labelName, $arAssoc)) {
                $arCSV[] = $this->utilEscapeCsv($arAssoc[$labelName]);
            } else {
                $arCSV[] = "";
            }
        }
        return implode($seperator, $arCSV);
    }

    /**
     * Transform the given CSV dataset into an assoc array
     * @param array     $arFields
     * @param array     $arLabels
     * @return array
     */
    function utilTransformCsvReverse($arFields, $arLabels) {
        $arAssoc = array();
        foreach ($arLabels as $labelIndex => $labelName) {
            $arAssoc[$labelName] = $arFields[$labelIndex];
        }
        return $arAssoc;
    }

    /**
     * Get absolute path/filename of the given cache file
     * @param string    $cacheFilename
     * @return string
     */
    function utilGetCachePathAbsolute() {
        return $this->pluginPath."cache";
    }

    /**
     * Get absolute path/filename of the given cache file
     * @param string    $cacheFilename
     * @return string
     */
    function utilGetCacheFileAbsolute($cacheFilename) {
        return $this->utilGetCachePathAbsolute()."/".$cacheFilename;
    }

    /**
     * Delete the given cache file
     * @param string    $cacheFilename
     * @return bool
     */
    function utilDeleteCacheFile($cacheFilename) {
        $cacheFilenameAbs = $this->utilGetCacheFileAbsolute($cacheFilename);
        if (file_exists($cacheFilenameAbs)) {
            return unlink($cacheFilenameAbs);
        } else {
            return true;
        }
    }

    /**
     * Get the contents of the given cache file
     * @param string    $cacheFilename
     * @return bool|string
     */
    function utilReadCacheFile($cacheFilename) {
        $cacheFilenameAbs = $this->utilGetCacheFileAbsolute($cacheFilename);
        if (!file_exists($cacheFilenameAbs)) {
            return false;
        }
        return file_get_contents($cacheFilenameAbs);
    }

    /**
     * Write new content into the given cache file
     * @param string    $cacheFilename
     * @param string    $cacheContent
     * @return bool
     */
    function utilWriteCacheFile($cacheFilename, $cacheContent) {
        $cacheFilenameAbs = $this->utilGetCacheFileAbsolute($cacheFilename);
        $cachePath = dirname( $cacheFilenameAbs );
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0777, true);
        }
        file_put_contents($cacheFilenameAbs, $cacheContent);
        return true;
    }

    public function _templates_systemCacheTemplates(Api_Entities_EventParamContainer $params) {
        $lang = $params->getParam("lang");
        $cacheTemplate = $params->getParam("cacheTemplate");
        $logTranslation = $params->getParam("logTranslation");
        // Get available module templates
        $arTemplatesModule = array();
        $arTemplatesModuleDirs = array(
            $this->pluginPath . 'tpl/default/',
            $this->pluginPath . 'tpl/'.$lang.'/'
        );
        foreach ($arTemplatesModuleDirs as $dirIndex => $dirPath) {
            if (is_dir($dirPath)) {
                $this->_templates_systemCacheTemplatesRecursive($dirPath, "", $arTemplatesModule);
            }
        }
        // Translate all existing templates and save them into the cache directory
        foreach ($arTemplatesModule as $filename => $filenameSource) {
            $this->utilGetTemplateTranslate($filename, $lang, $cacheTemplate, $logTranslation);
        }
    }

    public function _templates_systemCacheTemplatesRecursive($dirPath, $dirSuffix = "", &$arTemplatesModule) {
        $dirCurrent = dir($dirPath.$dirSuffix);
        while (false !== ($filename = $dirCurrent->read())) {
            $filenameFull = (empty($dirSuffix) ? "" : $dirSuffix."/").$filename;
            if (($filename != ".") && ($filename != "..") && is_dir($dirPath.$filenameFull)) {
                $this->_templates_systemCacheTemplatesRecursive($dirPath, $filenameFull, $arTemplatesModule);
            } else if (preg_match("/([A-Za-z0-9-_]+\.(html?|twig|twig\.html?|js))/", $filename, $arMatches)) {
                $arTemplatesModule[ $filenameFull ] = $dirPath.$filenameFull;
            }
        }
    }
    
} 
<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 23.05.16
 * Time: 09:35
 */

require_once $GLOBALS["ab_path"]."sys/lib.cache.template.php";

class Template_Twig implements Template_Interface {

    private static $twigEnv = null;
    private static $twigLoader = null;
    
    private static $cacheTemplate = null;
    
    private $twigTemplate;
    private $recurse;
    private $variables;
    
    /**
     * @return Twig_Loader_Filesystem
     */
    public static function getTwigLoader() {
        if (self::$twigLoader === null) {
            self::$twigLoader = new Twig_Loader_Filesystem($GLOBALS["ab_path"]."cache/design");
        }
        return self::$twigLoader;
    }

    /**
     * @param array     $options
     * @return Twig_Environment
     */
    public static function getTwigEnv($options = array()) {
        if (self::$twigEnv === null) {
            self::$twigEnv = new Twig_Environment(self::getTwigLoader(), $options);
            // Ensure caching used templates
            self::$twigEnv->addFunction(new Twig_SimpleFunction("usingTemplate", function($filename) {
                self::updateTemplateCache($filename);
            }));
            // Convert number to formatted price
            self::$twigEnv->addFilter(new Twig_SimpleFilter("price", function($value, $digits = 2, $seperator = ",", $seperatorThousand = ".", $zeroReplace = "-") {
                $result = number_format((double)$value, $digits, $seperator, $seperatorThousand);
                if ($zeroReplace === false) {
                    return $result;
                } else {
                    return preg_replace("/" . preg_quote($seperator . str_repeat("0", $digits)) . "$/", $seperator . $zeroReplace, $result);
                }
            }));
        }
        return self::$twigEnv;
    }
    
    public static function getCacheTemplate() {
        if (self::$cacheTemplate === null) {
            self::$cacheTemplate = new CacheTemplate();
        }
        return self::$cacheTemplate;
    }
    
    public static function updateTemplateCache($filename) {
        if($GLOBALS["nar_systemsettings"]["CACHE"]["TEMPLATE_AUTO_REFRESH"] == 1) {
            if (self::getCacheTemplate()->isFileDirty($filename)) {
                self::getCacheTemplate()->cacheFile($filename);
            }
        }
    }
    
    public function __construct($filename, $variables = array()) {
        if (preg_match("/^(tpl|mail|skin|module)\/([a-z]+)\/.+\.html?$/i", $filename, $arMatchTemplate)) {
            $variables["LANGUAGE"] = $arMatchTemplate[2];
            self::updateTemplateCache($filename);
        } else {
            $variables["LANGUAGE"] = $GLOBALS["s_lang"];
        }
        $this->twigTemplate = self::getTwigEnv()->loadTemplate($filename);
        $this->recurse = false;
        $this->variables = $variables;
    }


    public function addVariable($name, $value) {
        $this->variables[$name] = $value;
    }

    public function addVariables($values, $prefix = null) {
        if ($prefix === null) {
            // Simply merge values
            $this->variables = array_merge($this->variables, $values);
        } else {
            // Add values with prefix
            foreach ($values as $valueName => $valueContent) {
                $this->variables[$prefix.$valueName] = $valueContent;
            }
        }
    }

    public function getRecurse() {
        return $this->recurse;
    }
    
    public function getVariable($name) {
        if (array_key_exists($name, $this->variables)) {
            return $this->variables[$name];
        }
        return null;
    }

    public function getVariables() {
        return $this->variables;
    }
    
    public function setRecurse($parseRecursive = true) {
        $this->recurse = $parseRecursive;
    }

    public function render() {
        $result = $this->twigTemplate->render($this->variables);
        if ($this->recurse) {
            echo $result;
            return self::getTwigEnv()->createTemplate($result)->render($this->variables);
        } else {
            return $result;
        }
    }
}
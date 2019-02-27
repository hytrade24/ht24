<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once( dirname(__FILE__).'/LessPhp/Less.php');

class Design_Less_DesignCompiler {
    
    static function generateCss($cssFile, $cachePath = null, $variables = array(), $copyLessFiles = true) {
        if (preg_match("/^resources\/([^\/]+)\/css\/([^\/]+)\.css$/i", $cssFile, $arMatches)) {
            $cacheLang = $arMatches[1];
            $cacheFileName = $arMatches[2];
            $cachePathDefault = $GLOBALS['ab_path'].'cache/design/resources/'.$cacheLang.'/css/';
            if ($cachePath === null) {
                // Use default css cache path
                $cachePath = $cachePathDefault;
            } else {
                $cachePath = str_replace("{LANG}", $cacheLang, $cachePath);
                if (strpos($cachePath, $GLOBALS['ab_path']) === false) {
                    $cachePath = $GLOBALS['ab_path'].ltrim($cachePath, DIRECTORY_SEPARATOR);
                }
            }
            if (!is_dir($cachePath)) {
                mkdir($cachePath, 0777, true);
                $copyLessFiles = true;
            }
            $cacheFileCss = $cachePath.$cacheFileName.".css";
            $cacheFileLess = $cachePath.$cacheFileName.".less";
            $cacheFileVars = $cachePath.$cacheFileName.".vars.php";
            if (file_exists($cachePathDefault.$cacheFileName.".less")) {
                $templateName = $GLOBALS['nar_systemsettings']['SITE']['TEMPLATE'];
                if (empty($templateName)) {
                    // Fallback to default design if none is given
                    $templateName = "default";
                }
                $designPath = $GLOBALS['ab_path'].'design/';
                $designPathUser = $designPath.$templateName."/".$cacheLang."/resources/css/";
                $designFileUser = $designPathUser.$cacheFileName.".css";
                $designFileVarsUser = $designPathUser.$cacheFileName.".vars.php";
                if ($copyLessFiles) {
                    system("cp ".$designPath."default/default/resources/css/*.less ".$cachePath);
                    system("cp ".$designPath."default/".$cacheLang."/resources/css/*.less ".$cachePath);
                    system("cp ".$designPath.$templateName."/default/resources/css/*.less ".$cachePath);
                    system("cp ".$designPath.$templateName."/".$cacheLang."/resources/css/*.less ".$cachePath);
                } else {
                    system("cp ".$designPath.$templateName."/default/resources/css/config_user.less ".$cachePath);
                }
                $options = array( 'sourceMap' => true, 'compress' => true );
                $parser = new Less_Parser($options);
                $parser->SetImportDirs(array($GLOBALS['ab_path'] .'/cache/design/resources/'.$cacheLang.'/css/'));
                $parser->parseFile($cacheFileLess);
                $parser->ModifyVars( $variables );
                $lessVars = array();
                // Write to cache
                file_put_contents($cacheFileCss, $parser->getCss($lessVars));
                // Copy to active design (for updating template cache without compiling less again)
                if (!is_dir($designPathUser)) {
                    mkdir($designPathUser, 0777, true);
                }
                copy($cacheFileCss, $designFileUser);
                /** Serialize variables
                 * @var Less_Tree_Rule $variableRule
                 */
                $arVariables = array(
                    "NVP"   =>  array(),    // Name => Wert
                    "EXT"   =>  array()     // Name => Array("TYPE" => "Foo", "VALUE" => "Bar")
                );
                foreach ($lessVars as $variableName => $variableRule) {
                    $variableNameRaw = $variableName;
                    $variableName = ltrim($variableName, "@");
                    $variableValue = $variableRule->value->toCSS();
                    $arVariables["NVP"][$variableName] = $variableValue;
                    $arVariables["EXT"][$variableName] = array(
                        "TYPE"  =>  $variableRule->value->type,
                        "VALUE" =>  $variableValue,
                    );
                }
                file_put_contents($cacheFileVars, "<?php \$arLessVariables = ".php_dump($arVariables).";");
                copy($cacheFileVars, $designFileVarsUser);
                return $cacheFileCss;
            }
        }
        return false;
    }
    
}
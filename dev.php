<?php

require_once "api.php";

// Error reporting
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// Welche aktionen sind mit welcher methode verfügbar? 
$arPermissions = array(
    "COMMANDLINE"   => array("cache"),
    "REQUEST"       => array("getCssFile", "getLessFile")
);

$commandLine = !array_key_exists("do", $_REQUEST);
$permissionType = ($commandLine ? "COMMANDLINE" : "REQUEST"); 
$action = ($commandLine ? $argv[1] : $_REQUEST['do']);
$parameters = ($commandLine ? array_slice($argv, 2) : $_REQUEST['params']);

if (!in_array($action, $arPermissions[$permissionType])) {
    die("Zugriff verweigert! (".$permissionType."/".$action.")\n");
}

switch ($action) {
    case 'cache':
        require_once $ab_path . 'sys/lib.cache.adapter.php';
        $cacheAdapter = new CacheAdapter();
        $cacheType = (count($parameters) > 0 ? $parameters[0] : "all");
        switch ($_GET['type']) {
        	case 'template':
        		$cacheAdapter->cacheTemplate();
        		$cacheAdapter->cacheLess();
        		break;
        	case 'content':
        		$cacheAdapter->cacheContent();
        		break;
            case 'all':
           		$cacheAdapter->cacheAll();
                break;
            default:
                $stepResult = array("nextStep" => array("step" => 0, "name" => $cacheType));
                while (array_key_exists("nextStep", $stepResult)) {
                    $nextStep = $stepResult["nextStep"]["step"];
                    $nextStepName = $stepResult["nextStep"]["name"];
                    $stepResult = $cacheAdapter->cacheStep($nextStep, $cacheType);
                    if($stepResult == false) {
                        $resultSuccess = false;
                        break;
                    } else {
                        $resultData = $stepResult;
                        if ($commandLine && ($nextStep > 0)) {
                            echo " - Schritt ".$nextStep." (".$nextStepName.") fertig.\n";
                        }
                    }
                }
                break;
        }
        if ($commandLine) {
            if ($stepResult) {
                echo "Cache erfolgreich erneuert! (".$cacheType.")\n";
            } else {
                echo "Fehler beim erneuern des Cache! (".$cacheType.")\n";
            }
            die(($stepResult ? 0 : 1));
        } else {
            header("Content-Type: application/json");
            die(json_encode(array("success" => $stepResult)));
        }
        break;
    case 'getCssFile':
        $fileName = $_REQUEST['path'];
        $fileNameCached = Design_Less_DesignCompiler::generateCss($fileName);
        if ($fileNameCached === false) {
            // No less file found. Try to read cached css file.
            $fileCache = new CacheTemplate();
            if ($fileCache->isFileDirty($fileName)) {
                $fileCache->cacheFile($fileName);
            }
            $fileNameCached = $ab_path.'cache/design/'.$fileName;
        }
        header("Content-Type: text/css");
        die(file_get_contents($fileNameCached));
    case 'getLessFile':
        $fileName = $_REQUEST['path'];
        $fileCache = new CacheTemplate();
        if ($fileCache->isFileDirty($fileName)) {
            $fileCache->cacheFile($fileName);
        }
        $fileNameCached = 'cache/design/'.$fileName;
        die(file_get_contents($ab_path.$fileNameCached));
}
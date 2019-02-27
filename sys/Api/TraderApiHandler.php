<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 08.09.14
 * Time: 15:43
 */

class Api_TraderApiHandler {

    private static $instance = NULL;

    public static function getInstance(ebiz_db $db = NULL) {
        if (self::$instance === NULL) {
            if ($db === NULL) {
                $db = $GLOBALS["db"];
            }
            self::$instance = new Api_TraderApiHandler($db);
        }
        return self::$instance;
    }

    private $db;

    private $arHandlersRegistered;
    private $arPlugins;
    private $arPluginsDescription;
    private $arPluginsDisabled;

    function __construct(ebiz_db $db) {
        $this->db = $db;
        $this->arHandlersRegistered = array();
        $this->arPlugins = array();
        $this->arPluginsDescription = array();
        $this->arPluginsDisabled = array();
    }

    public function enablePlugin($pluginName, $pluginPath = NULL) {
        if ($pluginPath === NULL) {
            $pluginPath = __DIR__."/Plugins/";
        }
        $pluginFileName = $pluginPath.$pluginName."/Plugin.php";
        $pluginFileNameDisabled = $pluginPath.$pluginName."/disabled";
        if (file_exists($pluginFileName)) {
            if (file_exists($pluginFileNameDisabled)) {
                unlink($pluginFileNameDisabled);
            }
            return true;
        }
        return false;
    }

    public function disablePlugin($pluginName, $pluginPath = NULL) {
        if ($pluginPath === NULL) {
            $pluginPath = __DIR__."/Plugins/";
        }
        $pluginFileName = $pluginPath.$pluginName."/Plugin.php";
        $pluginFileNameDisabled = $pluginPath.$pluginName."/disabled";
        if (file_exists($pluginFileName)) {
            if (!file_exists($pluginFileNameDisabled)) {
                touch($pluginFileNameDisabled);
            }
            return true;
        }
        return false;
    }
    
    public function isPluginLoaded($pluginName) {
        foreach ($this->arPlugins as $pluginPriority => $arPluginList) {
            /**
             * @var Api_TraderApiPlugin $plugin
             */
            foreach ($arPluginList as $pluginIndex => $plugin) {
                $pluginNameCurrent = $plugin->getName();
                if ($pluginNameCurrent == $pluginName) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getPlugin($pluginName) {
        foreach ($this->arPlugins as $pluginPriority => $arPluginList) {
            /**
             * @var Api_TraderApiPlugin $plugin
             */
            foreach ($arPluginList as $pluginIndex => $plugin) {
                $pluginNameCurrent = $plugin->getName();
                if ($pluginNameCurrent == $pluginName) {
                    return $plugin;
                }
            }
        }
        return false;
    }

    public function getPlugins() {
        $arResult = array();
        foreach ($this->arPlugins as $pluginPriority => $arPluginList) {
            /**
             * @var Api_TraderApiPlugin $plugin
             */
            foreach ($arPluginList as $pluginIndex => $plugin) {
                $arResult[] = array(
                    "NAME"          => $plugin->getName(),
                    "DESCRIPTION"   => $this->arPluginsDescription[$plugin->getName()],
                    "SYSTEM"        => $plugin->isSystemPlugin(),
                    "ENABLED"       => true,
                    "CONFIG"        => ($plugin->isConfigurable() ? $plugin->getConfigurationForm() : false)
                );
            }
        }
        foreach ($this->arPluginsDisabled as $pluginIndex => $pluginName) {
            $arResult[] = array(
                "NAME"          => $pluginName,
                "DESCRIPTION"   => $this->arPluginsDescription[$pluginName],
                "ENABLED"       => false,
                "CONFIG"        => false
            );
        }
        return $arResult;
    }

    public function loadPlugins($pluginPath = NULL) {
        if ($pluginPath === NULL) {
            $pluginPath = __DIR__."/Plugins/";
        }
        // Load plugins
        $iterator = new \DirectoryIterator($pluginPath);
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isDot() && $fileInfo->isDir()) {
                $pluginName = $fileInfo->getFilename();
                $pluginClassName = "Api_Plugins_".$pluginName."_Plugin";
                $pluginFileName = $pluginPath.$pluginName."/Plugin.php";
                $pluginFileNameDisabled = $pluginPath.$pluginName."/disabled";
                $pluginFileNameDescription = $pluginPath.$pluginName."/description.txt";
                if (file_exists($pluginFileName)) {
                    if (file_exists($pluginFileNameDisabled)) {
                        $this->arPluginsDisabled[] = $pluginName;
                        if (file_exists($pluginFileNameDescription)) {
                            $this->arPluginsDescription[$pluginName] = file_get_contents($pluginFileNameDescription);
                        } else {
                            $this->arPluginsDescription[$pluginName] = "";
                        }
                    } else {
                        require_once $pluginFileName;
                        if (class_exists($pluginClassName) && is_subclass_of($pluginClassName, 'Api_TraderApiPlugin')) {
                            $pluginObject = new $pluginClassName($this, $pluginPath);
                            $pluginPriority = (int)$pluginClassName::getPriority();
                            if (!array_key_exists($pluginPriority, $this->arPlugins)) {
                                $this->arPlugins[$pluginPriority] = array();
                            }
                            $this->arPlugins[$pluginPriority][] = $pluginObject;
                            $this->arPluginsDescription[$pluginName] = $pluginObject->getDescription();
                        }
                    }
                }
            }
        }
        
        // Sort by priority
        krsort($this->arPlugins, SORT_NUMERIC);
        // Register events
        foreach ($this->arPlugins as $pluginPriority => $arPluginList) {
            /**
             * @var Api_TraderApiPlugin $plugin
             */
            foreach ($arPluginList as $pluginIndex => $plugin) {
                if ($plugin->registerEvents()) {
                    // Register autoloader
                    $pluginPath = $plugin->getPath();
                    if (is_dir($pluginPath."/classes")) {
                        Api_TraderApiPluginAutoloader::addClassPath($pluginPath."/classes");
                    }
                } else {
                    $this->unregisterEventsByPlugin( $plugin->getName() );
                }
            }
        }
    }

    public function registerEvent($eventName, Api_TraderApiPlugin $pluginObject, $pluginMethodName) {
        if (!array_key_exists($eventName, $this->arHandlersRegistered)) {
            $this->arHandlersRegistered[$eventName] = array();
        }
        foreach ($this->arHandlersRegistered[$eventName] as $handlerIndex => $arHandler) {
            if (($arHandler['object'] == $pluginObject) && ($arHandler['method'] == $pluginMethodName)) {
                // Already registered!
                return false;
            }
        }
        $this->arHandlersRegistered[$eventName][] = array(
            'object'    => $pluginObject,
            'method'    => $pluginMethodName
        );
        return true;
    }

    public function triggerEvent($eventName, $eventParameters = array(), $targetPlugin = NULL) {
        if (!array_key_exists($eventName, $this->arHandlersRegistered)) {
            $this->arHandlersRegistered[$eventName] = array();
        }
        foreach ($this->arHandlersRegistered[$eventName] as $handlerIndex => $arHandler) {
            if (($targetPlugin === NULL) || ($arHandler['object']->getName() == $targetPlugin)) {
                $handlerMethod = $arHandler['method'];
                $consumeEvent = $arHandler['object']->$handlerMethod($eventParameters);
                if ($consumeEvent === true) {
                    break;
                }
            }
        }
        return true;
    }

    public function unregisterEventsByPlugin($pluginName) {
        $pluginClassName = "Api_Plugins_".$pluginName."_Plugin";
        foreach ($this->arHandlersRegistered as $eventName => $arRegisteredPlugins) {
            for ($pluginIndex = count($arRegisteredPlugins)-1; $pluginIndex >= 0; $pluginIndex--) {
                $pluginCallback = $arRegisteredPlugins[$pluginIndex];
                if (is_a($pluginCallback['object'], $pluginClassName)) {
                    // Callback of the given plugin, remove entry from handlers
                    array_splice($this->arHandlersRegistered[$eventName], $pluginIndex, 1);
                }
            }
        }
        return true;
    }

    /**
     * @return ebiz_db
     */
    public function getDb() {
        return $this->db;
    }

} 
<?php

class Api_TraderApiPluginAutoloader {
    
    private static $registered = false;
    private static $arClassPaths = array();
    
    public static function addClassPath($classPath) {
        self::register();
        $classPath = rtrim($classPath, DIRECTORY_SEPARATOR);
        if (!in_array($classPath, self::$arClassPaths)) {
            self::$arClassPaths[] = $classPath;
        }
    }
    
    public static function loadClass($className) {
        if (class_exists($className, false)) {
            // Class already loaded
            return;
        }
        foreach (self::$arClassPaths as $pathIndex => $pathDir) {
            // PSR-0
            $class_file = $pathDir.DIRECTORY_SEPARATOR.str_replace('_', DIRECTORY_SEPARATOR, $className).".php";
            if (file_exists($class_file)) {
                require_once($class_file);
                return;
            }
        }
    }
    
    public static function register() {
        if (self::$registered === false) {
            spl_autoload_register(function ($class) {
                Api_TraderApiPluginAutoloader::loadClass($class);
            });
        }
    }
}
<?php

class TwigExtensions_ScriptAppend {
    
    private static $scripts = [];

    /**
     * @param string $type
     * @param string $ident
     * @param string $content
     */
    public static function addBlock($type, $ident, $content) {
        if (!array_key_exists($type, self::$scripts)) {
            self::$scripts[$type] = [];
        }
        if (!array_key_exists($ident, self::$scripts[$type])) {
            self::$scripts[$type][$ident] = $content;
        }
    }

    public static function hasBlock($type, $ident = null) {
        if (!array_key_exists($type, self::$scripts)) {
            return false;
        }
        if (($ident !== null) && !array_key_exists($ident, self::$scripts[$type])) {
            return false;
        }
        return true;
    }
    

    public static function hasBlocks() {
        return !empty(self::$scripts);
    }
    
    /**
     * @return array
     */
    public static function getBlocks() {
        return self::$scripts;
    }

    /**
     * @return array
     */
    public static function getBlocksByType($type, $implode = false) {
        if ($implode) {
            return implode("\n", (array_key_exists($type, self::$scripts) ? self::$scripts[$type] : []));
        } else {
            return (array_key_exists($type, self::$scripts) ? self::$scripts[$type] : []);
        }
    }
    
}
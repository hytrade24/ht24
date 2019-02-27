<?php

abstract class Rest_Abstract {
    
    protected static $lastError = null;
    
    protected static function setLastError($errorIdent, $errorMessage, $errorVariables = array()) {
        if ($errorIdent !== null) {
            self::$lastError = Translation::readTranslation("general", "rest.api.error.".$errorIdent, null, $errorVariables, $errorMessage);
        } else {
            self::$lastError = $errorMessage;
        }
    }
    
    public static function getLastError() {
        return self::$lastError;
    }
    
}
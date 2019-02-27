<?php

function crashHandler_traderPublic() {
	$ar_error_last = error_get_last();
	if (!empty($ar_error_last)) {
        switch($ar_error_last['type']) {
            case E_PARSE:
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
                errorHandler_traderPublic($ar_error_last['type'], $ar_error_last['message'], $ar_error_last['file'], $ar_error_last['line']);
                break;
        }
	}
}

function errorHandler_traderPublic($errno, $errstr, $errfile, $errline) {
    $typeLog = "info";
    $type = "Info";
    switch($errno) {
        case E_PARSE:
            $type = "Parse error";
            break;
        case E_ERROR:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_USER_ERROR:
            $type = "Fatal error";
            $typeLog = "error";
            break;
        case E_WARNING:
        case E_CORE_WARNING:
        case E_COMPILE_WARNING:
        case E_USER_WARNING:
            $type = "Warning";
            break;
    }
    // Fehler loggen
    eventlog($typeLog, "PHP Debug-Info", str_replace($GLOBALS["ab_path"], "/", $errfile).":".$errline."	".$type.": ".$errstr);
	return false;
}

register_shutdown_function('crashHandler_traderPublic');
# Uncomment this to log all errors into eventlog (not only fatal ones)
#set_error_handler(errorHandler_traderPublic, error_reporting());

error_reporting(E_ERROR | E_PARSE);
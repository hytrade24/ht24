<?php
/* ###VERSIONSBLOCKINLCUDE### */

function checkOptionValue($plugin, $typ, $value) {
    if ($plugin == "SITE") {
        if ($typ == "SITEURL") {
            // Remove tailing slash(es)
            return rtrim($value, "/");
        }
    }
    // Default behaviour: return value unchanged.
    return $value;
}

?>
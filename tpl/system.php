<?php

$feature = preg_replace("/[^a-z0-9_-]+/i", "", (array_key_exists("feature", $_REQUEST) ? $_REQUEST["feature"] : $ar_params[1]));
if (file_exists(__DIR__."/system-user-".$feature.".php")) {
    include __DIR__."/system-user-".$feature.".php";
}
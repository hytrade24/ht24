<?php
/* ###VERSIONSBLOCKINLCUDE### */

$adapterType = $ar_params[1];

switch ($adapterType) {
    case 'sofort':
        include $ab_path.'tpl/system-payment.'.$adapterType.'.php';
}

die();

?>
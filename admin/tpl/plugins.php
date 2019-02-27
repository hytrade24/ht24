<?php

$apiHandler = Api_TraderApiHandler::getInstance($db);

if (array_key_exists("do", $_REQUEST) && array_key_exists("plugin", $_REQUEST)) {
    $pluginName = $_REQUEST["plugin"];
    switch ($_REQUEST["do"]) {
        case "enable":
            $apiHandler->enablePlugin($pluginName);
            break;
        case "disable":
            $apiHandler->disablePlugin($pluginName);
            break;
        case "configAjax":
            $plugin = $apiHandler->getPlugin($pluginName);
            // Get config ajax response
            $result = $plugin->getConfigurationAjax($_POST);
            header("Content-Type: application/json");
            die(json_encode($result));
        case "configSave":
            $plugin = $apiHandler->getPlugin($pluginName);
            // Save config
            $plugin->setConfiguration($_POST["CONFIG"]);
            // Return config form
            $tplConfig = $plugin->getConfigurationForm();
            $tplConfig->addvars(array_flatten($_POST["CONFIG"], "both", "_", "CONFIG_"));
            $tplConfig->addvars($_POST);
            $tplConfig->addvar("SAVED", 1);
            die($tplConfig->process(true));
    }
    die(forward("index.php?page=plugins&done=".$_REQUEST["do"]));
}

if (array_key_exists("done", $_REQUEST)) {
    $tpl_content->addvar("DONE_".strtoupper($_REQUEST["done"]), 1);
}

$arPlugins = $apiHandler->getPlugins();

$tpl_content->addlist("liste", $arPlugins, "tpl/de/plugins.row.htm");
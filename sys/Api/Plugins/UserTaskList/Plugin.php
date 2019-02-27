<?php

class Api_Plugins_UserTaskList_Plugin extends Api_TraderApiPlugin {

    /**
     * Defines the priority of the Plugin. (Higher number = more important)
     * @return int
     */
    static public function getPriority() {
        return 0;
    }

    /**
     * Register the events needed by your plugin within this function.
     * @return bool     False for error, plugin will not be loaded if an error occurs
     */
    public function registerEvents() {
        $this->registerEvent( Api_TraderApiEvents::AJAX_PLUGIN, "ajaxPlugin" );
        $this->registerEvent( Api_TraderApiEvents::SYSTEM_CACHE_ALL, "systemCacheAll");
        $this->registerEvent( Api_TraderApiEvents::USER_DELETE, "userDelete" );
        $this->registerEvent( Api_TraderApiEvents::USER_VIEW_HOME, "userViewHome" );
        
        return true;
    }
    
    public function ajaxPlugin(Api_Entities_EventParamContainer $params) {
        $jsonResult = array("success" => false);
        switch ($params->getParam("action")) {
            case 'resolveTask':
                $jsonResult["success"] = $this->userSetTaskDone($GLOBALS["uid"], $_POST["id"]);
                break;
        }
        header("Content-Type: application/json");
        die(json_encode($jsonResult));
    }

    /**
     * Returns the configuration form for this plugin
     * @return Template
     */
    public function getConfigurationForm() {
        global $db, $lang_list, $s_lang;
        $tplConfig = parent::getConfigurationForm();
        // Sprachen auflisten
        $arLanguages = $lang_list;
        foreach ($arLanguages as $langIndex => $arLang) {
            $arLanguages[$langIndex]["LANG_ACTIVE"] = ($arLang["ABBR"] == $s_lang);
        }
        // Rollen auflisten
        $arRoles = $db->fetch_table("select ID_ROLE, LABEL from role order by ID_ROLE");
        $tplConfig->addvar("table_head_roles", $this->utilGetTemplateList("config.tableRole.htm", $arRoles));
        // Task row template
        $tplTableRow = $this->utilGetTemplate("config.tasksRow.htm");
        $tplTableRow->addvar("names", $this->utilGetTemplateList("config.tasksRow.name.htm", $arLanguages));
        $tplTableRow->addvar("roles", $this->utilGetTemplateList("config.tasksRow.role.htm", $arRoles));
        $tplConfig->addvar("table_row", $tplTableRow->process(true));
        // Aufgaben auflisten
        $arTasks = (array_key_exists("TASKS", $this->pluginConfiguration) ? $this->pluginConfiguration["TASKS"] : array());
        foreach ($arTasks as $taskIndex => $arTask) {
            // - Typ
            $arTasks[$taskIndex]["TYPE_".$arTask["TYPE"]] = true;
            // - Sprachen
            $arTaskNames = $arLanguages;
            foreach ($arTaskNames as $nameIndex => $arName) {
                $arTaskNames[$nameIndex]["NAME"] = $arTask["NAME"][ $arName["ABBR"] ];
            }
            $arTasks[$taskIndex]["names"] = $this->utilGetTemplateList("config.tasksRow.name.htm", $arTaskNames);
            // - Erledigt wenn...
            $arTasks[$taskIndex]["RESOLVE_".$arTask["RESOLVE"]] = true;
            // - Rollen
            $arTaskRoles = $arRoles;
            foreach ($arTaskRoles as $roleIndex => $arRole) {
                $arTaskRoles[$roleIndex]["ENABLED"] = in_array($arRole["ID_ROLE"], $arTask["ROLES"]);
            }
            $arTasks[$taskIndex]["roles"] = $this->utilGetTemplateList("config.tasksRow.role.htm", $arTaskRoles);
        }

        $tplConfig->addvar("liste_tasks", $this->utilGetTemplateList("config.tasksRow.htm", $arTasks));
        return $tplConfig;
    }

    /**
     * Updates the configuration of the plugin (usually done by posting the plugin config form)
     * @param array $arConfig
     * @return bool
     */
    public function setConfiguration($arConfig) {
        global $db, $lang_list;
        if ((int)$arConfig["COUNT_PER_LINE"] <= 0) {
            $arConfig["COUNT_PER_LINE"] = 3;
        }
        $arTaskList = $arConfig["TASKS"];
        $arTaskList["FILLED"] = array();
        // Convert inputs to numbers
        foreach ($arTaskList["PRIORITY"] as $index => $value) {
            if ($value !== "") {
                $arTaskList["PRIORITY"][$index] = (int)$arTaskList["PRIORITY"][$index];
            } else {
                $arTaskList["PRIORITY"][$index] = 1;
            }
        }
        // Group name settings
        for ($index = 0; $index < count($arTaskList["ID"]); $index++) {
            $arTaskList["NAME"][$index] = array();
            $arTaskList["FILLED"][$index] = false;
            foreach ($lang_list as $langIndex => $arLang) {
                $arTaskList["NAME"][$index][ $arLang["ABBR"] ] = $arTaskList["NAME_".$arLang["ABBR"]][$index];
                if (!empty($arTaskList["NAME_".$arLang["ABBR"]][$index])) {
                    $arTaskList["FILLED"][$index] = true;
                }
            }
        }
        // Group role settings
        $arRoles = $db->fetch_nar("select ID_ROLE, LABEL from role order by ID_ROLE");
        for ($index = 0; $index < count($arTaskList["ID"]); $index++) {
            $arTaskList["ROLES"][$index] = array();
            foreach ($arRoles as $roleId => $roleLabel) {
                if ($arTaskList["ROLES_".$roleId][$index]) {
                    $arTaskList["ROLES"][$index][] = $roleId;
                }
            }
        }
        // Sort by price
        array_multisort($arTaskList["PRIORITY"], $arTaskList["ID"], $arTaskList["FILLED"], $arTaskList["TYPE"], $arTaskList["NAME"], $arTaskList["TARGET"], $arTaskList["RESOLVE"], $arTaskList["ROLES"]);
        // Convert into list of assoc arrays
        $arConfig["TASKS"] = array();
        $count = count($arTaskList["ID"]);
        for ($index = 0; $index < $count; $index++) {
            if ($arTaskList["FILLED"][$index]) {
                $arConfig["TASKS"][] = array(
                    "ID"            => $arTaskList["ID"][$index],
                    "PRIORITY"      => $arTaskList["PRIORITY"][$index],
                    "TYPE"          => $arTaskList["TYPE"][$index],
                    "NAME"          => $arTaskList["NAME"][$index],
                    "TARGET"        => $arTaskList["TARGET"][$index],
                    "RESOLVE"       => $arTaskList["RESOLVE"][$index],
                    "ROLES"         => $arTaskList["ROLES"][$index]
                );
            }
        }
        // Save configuration
        return parent::setConfiguration($arConfig);
    }
    
    public function systemCacheAll(Api_Entities_EventParamContainer $params) {
        $cachePath = $this->utilGetCachePathAbsolute();
        system('rm -R ' . $cachePath);
    }
    
    private function userCheckResolved($userId, &$userConfig, $type, &$taskInfo = array()) {
        if (in_array($type, $userConfig["RESOLVED"])) {
            return true;
        } else {
            global $db;
            switch ($type) {
                case 'USER_IMPRINT':
                    // Impressum ausgefüllt?
                    $user_content = $db->fetch1("SELECT * FROM `usercontent` WHERE FK_USER=".(int)$userId);
                    if (!is_array($user_content)) {
                        return false;
                    } else {
                        $impressumPlain = strip_tags($user_content["IMPRESSUM"]);
                        return !empty($impressumPlain);
                    }
                    break;
                case 'USER_PAYMENT_METHOD':
                    // Impressum ausgefüllt?
                    $user_payment_count = $db->fetch_atom("SELECT COUNT(*) FROM `user2payment_adapter` WHERE FK_USER=".(int)$userId." AND STATUS=1 AND CONFIG_VALID=1");
                    return ($user_payment_count > 0);
                    break;
                case 'USER_MEMBERSHIP_OPEN':
                    require_once $GLOBALS["ab_path"]."sys/packet_management.php";
                    $packets = PacketManagement::getInstance($db);
                    // Mitgliedschaft bezahlt?
                    $arUnpaidMemberships = array();
                    $arUnpaidPacketIds = $db->fetch_col("
                                                SELECT pi.FK_PACKET_ORDER FROM `packet_order_invoice` pi
                                                JOIN `billing_invoice` i ON i.ID_BILLING_INVOICE=pi.FK_INVOICE 
                                                WHERE i.FK_USER=".(int)$userId." AND i.STATUS=0");
                    foreach ($arUnpaidPacketIds as $index => $idPacketOrder) {
                        /** @var PacketOrderBase $packetOrder */
                        $packetOrder = $packets->order_get($idPacketOrder);
                        if (($packetOrder instanceof PacketOrderMembershipOnce) || ($packetOrder instanceof PacketOrderMembershipRecurring)) {
                            $arUnpaidMemberships[] = $packetOrder;
                        }
                    }
                    return empty($arUnpaidMemberships);
                    break;
            }
        }
    }
    
    private function userSetTaskDone($userId, $taskId) {
        $userConfig = $this->userGetConfig($userId, false);
        if (!in_array($taskId, $userConfig["TASKS_DONE"])) {
            $userConfig["TASKS_DONE"][] = $taskId;
            return $this->userSetConfig($userId, $userConfig);
        }
        return true;
    }
    
    private function userGetConfig($userId, $updateTasks = true) {
        $userConfig = array(
            "TASKS_DONE"    => array(),
            "TASKS_OPEN"    => array(),
            "RESOLVED"      => array()
        );
        $userConfigFile = "status/userTasks.".$userId.".json";
        if (file_exists( $this->utilGetCacheFileAbsolute($userConfigFile) )) {
            $userConfig = json_decode( $this->utilReadCacheFile($userConfigFile), true );
        }
        if ($updateTasks && is_array($this->pluginConfiguration["TASKS"]) && !empty($this->pluginConfiguration["TASKS"])) {
            $userRoles = $GLOBALS["db"]->fetch_col("SELECT FK_ROLE FROM `role2user` WHERE FK_USER=".$userId);
            $userConfig["TASKS_OPEN"] = array();
            foreach ($this->pluginConfiguration["TASKS"] as $taskIndex => $arTask) {
                $arRoleMatch = array_intersect($userRoles, $arTask["ROLES"]);
                if (!empty($arRoleMatch)) {
                    // Task is open, check if resolved now.
                    if ($arTask["RESOLVE"] != "USER") {
                        // Automatically detecting resolution
                        if ($this->userCheckResolved($userId, $userConfig, $arTask["RESOLVE"], $arTask)) {
                            // Task is resolved!
                            if (!in_array($arTask["ID"], $userConfig["TASKS_DONE"])) {
                                $userConfig["TASKS_DONE"][] = $arTask["ID"];
                            }
                        } else {
                            // Task is open!
                            if (in_array($arTask["ID"], $userConfig["TASKS_DONE"])) {
                                // Task was done before, remove from finished tasks
                                $userConfig["TASKS_DONE"] = array_diff($userConfig["TASKS_DONE"], array($arTask["ID"]));
                            }
                        }
                    } 
                    if (!in_array($arTask["ID"], $userConfig["TASKS_DONE"])) {
                        // Get the name in the current language
                        $arTask["NAME"] = $arTask["NAME"][ $GLOBALS["s_lang"] ];
                        // Task is still open
                        $userConfig["TASKS_OPEN"][] = $arTask;
                    }
                }
            }
            $this->userSetConfig($userId, $userConfig);
        }
        return $userConfig;
    }
    
    private function userSetConfig($userId, &$userConfig) {
        $userConfigFile = "status/userTasks.".$userId.".json";
        return $this->utilWriteCacheFile($userConfigFile, json_encode($userConfig));
    }
    
    public function userDelete(Api_Entities_EventParamContainer $params) {
        $userId = $params->getParam("ID_USER");
        $userConfigFile = "status/userTasks.".$userId.".json";
        $this->utilDeleteCacheFile($userConfigFile);
    }
    
    public function userViewHome(Api_Entities_EventParamContainer $params) {
        $userId = (int)$GLOBALS["uid"];
        if ($userId > 0) {
            $userConfig = $this->userGetConfig($userId);
            if (!empty($userConfig["TASKS_OPEN"])) {
                $arTaskRows = array();
                $arTaskList = $userConfig["TASKS_OPEN"];
                $colCountMaxSM = (int)$this->pluginConfiguration["COUNT_PER_LINE_SM"];
                $colCountMaxMD = (int)$this->pluginConfiguration["COUNT_PER_LINE_MD"];
                if ($colCountMaxSM <= 0) {
                    $colCountMaxSM = 2;
                }
                if ($colCountMaxMD <= 0) {
                    $colCountMaxMD = 3;
                }
                while (!empty($arTaskList)) {
                    $arTask = array_shift($arTaskList);
                    if (preg_match("/^(.+)#(.+)$/", $arTask["TARGET"], $arMatchTarget)) {
                        $arTask["TARGET"] = $arMatchTarget[1];
                        $arTask["TARGET_HASH"] = $arMatchTarget[2];
                    }
                    $rowTemplate = $this->utilGetTemplate("tasks.row.htm");
                    $rowTemplate->addvars( array_merge($arTask, array_flatten($arTask)) );
                    $arTaskRows[] = $rowTemplate;
                }
                $tplTaskList = $this->utilGetTemplate("tasks.htm");
                $tplTaskList->addvar("COUNT_PER_LINE_SM", $colCountMaxSM);
                $tplTaskList->addvar("COUNT_PER_LINE_MD", $colCountMaxMD);
                $tplTaskList->addvar("liste", $arTaskRows);
                $pluginInfoHtml = $params->getParam("pluginInfo");
                $pluginInfoHtml .= $tplTaskList->process();
                $params->setParam("pluginInfo", $pluginInfoHtml);
            }
        }
    }
}
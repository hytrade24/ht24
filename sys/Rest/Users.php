<?php

class Rest_Users extends Rest_Abstract {
    
    public static function createUser(&$arData, ebiz_db $db = null, $langval = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        $langId = 1;
        foreach ($GLOBALS['lang_list'] as $langIndex => $langCurrent) {
            if ($langCurrent["BITVAL"] == $langval) {
                $langId = $langCurrent["ID_LANG"];
            }
        }
        // Disallow creating users with a specific id
        if (!self::createUserCheck($arData, $db, $langval)) {
            return false;
        }
        // Check/encrypt password
        if (array_key_exists("PASS_PLAIN", $arData) && !empty($arData["PASS_PLAIN"])) {
            // Get password and remove it from data
            $passPlain = $arData["PASS_PLAIN"];
            unset($arData["PASS_PLAIN"]);
            // Encrypt password
            $passSalt = pass_generate_salt();
            $arData['SALT'] = $passSalt;
            $arData['PASS'] = pass_encrypt($passPlain, $passSalt);
        }
        // Check usergroup
        if (array_key_exists("FK_USERGROUP", $arData)) {
            $usergroupExists = $db->fetch_atom("SELECT COUNT(*) FROM `usergroup` WHERE ID_USERGROUP=".(int)$arData["FK_USERGROUP"]);
            if (!$usergroupExists) {
                // Usergroup does not exist. Use default instead.
                $arData["FK_USERGROUP"] = $db->fetch_atom("SELECT ID_USERGROUP FROM `usergroup` WHERE IS_DEFAULT=1");
            }
        } else {
            $arData["FK_USERGROUP"] = $db->fetch_atom("SELECT ID_USERGROUP FROM `usergroup` WHERE IS_DEFAULT=1");
        }
        // Check language
        if (array_key_exists("FK_LANG", $arData)) {
            $languageExists = $db->fetch_atom("SELECT COUNT(*) FROM `lang` WHERE ID_LANG=".(int)$arData["FK_LANG"]);
            if (!$languageExists) {
                // Usergroup does not exist. Use default instead.
                $arData["FK_LANG"] = $langId;
            }
        } else {
            $arData["FK_LANG"] = $langId;
        }
        // Check status flag
        if (!array_key_exists("STAT", $arData)) {
            // TODO: E-Mail confirm implementation
            $arData["STAT"] = 1;
        }
        // Get default user role
        $defaultRoleOption = $db->fetch_atom("SELECT ID_MODULOPTION FROM `moduloption` WHERE OPTION_VALUE='DEFAULT_ROLE'");
        $defaultRole = $db->fetch_atom("
            SELECT s.V1 FROM `moduloption` t
            LEFT JOIN string_opt s ON s.S_TABLE='moduloption' AND s.FK=t.ID_MODULOPTION AND s.BF_LANG=if(t.BF_LANG_OPT & " . $langval . ", " . $langval . ", 1 << floor(log(t.BF_LANG_OPT+0.5)/log(2)))
            WHERE S_TABLE='moduloption' AND FK=".$defaultRoleOption
        );
        // Write user to database
        $arData["ID_USER"] = $db->update("user", $arData);
        if ($arData["ID_USER"] > 0) {
            require_once $GLOBALS["ab_path"]."sys/lib.usercreate.php";
            $arData["PASS_PLAIN"] = $arData["pass1"] = $passPlain;
            createUser($arData["ID_USER"], $arData);
            AddRole2User($defaultRole, $arData["ID_USER"]);
            // TODO: Add to newsletter implementation
            // TODO: Buy/assign membership implementation
            return (int)$arData["ID_USER"];
        } else {
            self::setLastError("user.database.create", "Datenbankfehler beim erstellen des Benutzers!");
            return false;
        }
    }
    
    public static function createUserCheck(&$arData, ebiz_db $db = null, $langval = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        // Disallow creating users with a specific id
        if ($arData["ID_USER"] > 0) {
            return false;
        }
        // Check/encrypt password
        if ((!array_key_exists("PASS_PLAIN", $arData) || empty($arData["PASS_PLAIN"]))
            && (!array_key_exists("PASS", $arData) || !array_key_exists("SALT", $arData))) {
            self::setLastError("user.password.missing", "Es wurde kein Passwort angegeben!");
            return false;
        }
        // Check username
        if (!array_key_exists("NAME", $arData) || validate_nick($arData["NAME"])) {
            self::setLastError("user.name.invalid", "Es wurde kein (gültiger) Benutzername angegeben!");
            return false;
        } else {
            $usernameExists = $db->fetch_atom("SELECT COUNT(*) FROM `user` WHERE NAME='".mysql_real_escape_string($arData["NAME"])."'");
            if ($usernameExists) {
                self::setLastError("user.name.exists", "Der Benutzername '{NAME}' ist bereits vergeben!", array("NAME" => $arData["NAME"]));
                return false;
            }
        }
        // Check email
        if (!array_key_exists("EMAIL", $arData) || (trim($arData["EMAIL"]) == "")) {
            self::setLastError("user.email.missing", "Es wurde keine E-Mail Adresse angegeben!");
            return false;
        } else {
            $emailExists = $db->fetch_atom("SELECT COUNT(*) FROM `user` WHERE EMAIL='".mysql_real_escape_string($arData["EMAIL"])."'");
            if ($emailExists) {
                self::setLastError("user.email.exists", "Ein Benutzer mit dieser E-Mail Adresse existiert bereits!");
                return false;
            }
        }
        return true;
    }
    
    public static function updateUser($userId, &$arData, ebiz_db $db = null, $langval = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        // Change password?
        if (array_key_exists("PASS_PLAIN", $arData) && !empty($arData["PASS_PLAIN"])) {
            // Get password and remove it from data
            $passPlain = $arData["PASS_PLAIN"];
            unset($arData["PASS_PLAIN"]);
            // Encrypt password
            $passSalt = pass_generate_salt();
            $arData['SALT'] = $passSalt;
            $arData['PASS'] = pass_encrypt($passPlain, $passSalt);
        }
        // Check username
        if (array_key_exists("NAME", $arData)) {
            if (empty($arData["NAME"]) || validate_nick($arData["NAME"])) {
                self::setLastError("user.name.invalid", "Es wurde kein (gültiger) Benutzername angegeben!");
                return false;
            } else {
                $usernameExists = $db->fetch_atom("SELECT ID_USER FROM `user` WHERE NAME='".mysql_real_escape_string($arData["NAME"])."'");
                if (($usernameExists !== null) && ((int)$usernameExists != (int)$userId)) {
                    self::setLastError("user.name.exists", "Der Benutzername '{NAME}' ist bereits vergeben!", array("NAME" => $arData["NAME"]));
                    return false;
                }
            }
        }
        // Check usergroup
        if (array_key_exists("FK_USERGROUP", $arData)) {
            $usergroupExists = $db->fetch_atom("SELECT COUNT(*) FROM `usergroup` WHERE ID_USERGROUP=".(int)$arData["FK_USERGROUP"]);
            if (!$usergroupExists) {
                // Usergroup does not exist. Use default instead.
                $arData["FK_USERGROUP"] = $db->fetch_atom("SELECT ID_USERGROUP FROM `usergroup` WHERE IS_DEFAULT=1");
            }
        }
        // Check language
        if (array_key_exists("FK_LANG", $arData)) {
            $languageExists = $db->fetch_atom("SELECT COUNT(*) FROM `lang` WHERE ID_LANG=".(int)$arData["FK_LANG"]);
            if (!$languageExists) {
                // Language does not exist. Keep current.
                unset($arData["FK_LANG"]);
            }
        }
        // Check email
        if (array_key_exists("EMAIL", $arData)) {
            $emailExists = $db->fetch_atom("SELECT ID_USER FROM `user` WHERE EMAIL='".mysql_real_escape_string($arData["EMAIL"])."'");
            if (($emailExists !== null) && ((int)$emailExists != (int)$userId)) {
                self::setLastError("user.email.exists", "Ein Benutzer mit dieser E-Mail Adresse existiert bereits!");
                return false;
            }
        }
        // Write user to database
        $arData["ID_USER"] = (int)$userId;
        $result = $db->update("user", $arData);
        if ($result == (int)$userId) {
            // TODO: Add to newsletter implementation
            // TODO: Buy/assign membership implementation
            return true;
        } else {
            self::setLastError("user.database.update", "Datenbankfehler beim aktualisieren des Benutzers!");
            return false;
        }
    }
    
    /**
     * Get the first user matching the given conditions
     * @param array|string  $arFields
     * @param array         $arWhere
     * @param ebiz_db       $db
     * @param int|null      $langval
     */
    public static function getUser($arWhere = array(), $arFields = "*", ebiz_db $db = null, $langval = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        // Get table
        $table = self::getDataTable($db, $langval);
        $query = $table->createQuery();
        if (is_array($arFields)) {
            foreach ($arFields as $fieldIndex => $fieldName) {
                $query->addField($fieldName);
            }
        }
        // Add conditions
        $query->addWhereConditions($arWhere);
        // Get result
        return $query->fetchOne();
    }
    
    /**
     * Get the users matching the given conditions
     * @param array|string  $arFields
     * @param array         $arWhere
     * @param ebiz_db       $db
     * @param int|null      $langval
     */
    public static function getUserList($arWhere = array(), $arFields = "*", ebiz_db $db = null, $langval = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        // Get table
        $table = self::getDataTable($db, $langval);
        $query = $table->createQuery();
        if (is_array($arFields)) {
            foreach ($arFields as $fieldIndex => $fieldName) {
                $query->addField($fieldName);
            }
        }
        // Add conditions
        $query->addWhereConditions($arWhere);
        // Get result
        return $query->fetchTable();
    }
    
    
    
    /**
     * Get datatable object for the given category
     * @param int       $categoryId
     * @param ebiz_db   $db
     * @param int       $langval
     * @return Api_DataTable
     */
    public static function getDataTable(ebiz_db $db = null, $langval = null) {
        // Default settings
        if ($db === null) {
            $db = $GLOBALS["db"];
        }
        if ($langval === null) {
            $langval = $GLOBALS["langval"];
        }
        $s_lang = "de";
        foreach ($GLOBALS['lang_list'] as $langIndex => $langCurrent) {
            if ($langCurrent["BITVAL"] == $langval) {
                $s_lang = $langCurrent["ABBR"];
            }
        }
        /*
         * Create data table
         */
        $dataTable = new Api_DataTable($db, "user", "u");
        
        /*
         * Define joins
         */
        $dataTable->addTableJoin("country", "uc", "LEFT JOIN", "u.FK_COUNTRY = uc.ID_COUNTRY");
        $dataTable->addTableJoinString("country", "uc", "string", "suc", "LEFT JOIN", $langval);
        $dataTable->addTableJoin("usergroup", "ug", "LEFT JOIN", "u.FK_USERGROUP = ug.ID_USERGROUP");
        $dataTable->addTableJoinString("usergroup", "ug", "string_usergroup", "sug", "LEFT JOIN", $langval);
        /*
         * Define fields
         */
        // Field for count queries
        $dataTable->addField(null, null, "COUNT(*)", "RESULT_COUNT");
        // Regular fields
        $dataTable->addFieldsFromDb("u");
        // Joined fields
        $dataTable->addFieldsFromDb("uc");
        $dataTable->addFieldsFromDb("ug");
        // Define multilingual fields
        $dataTable->addField("suc", "V1", NULL, "COUNTRY_NAME");
        $dataTable->addField("sug", "V1", NULL, "USERGROUP_NAME");
        // Define special fields
        // TODO
        
        /*
         * Define core conditions
         */
        $dataTable->addWhereCondition("ID_USER", "u.ID_USER=$1$");
        $dataTable->addWhereCondition("FK_USERGROUP", "u.FK_USERGROUP=$1$");
        $dataTable->addWhereCondition("NAME", "u.NAME LIKE '%$1$%'");
        // Special conditions
        $dataTable->addWhereCondition("_USERS_LIKE_FULL", "(u.EMAIL!='' AND u.EMAIL LIKE '$1$')".
            " OR (u.FIRMA!='' AND u.FIRMA LIKE '$2$')".
            " OR (u.VORNAME!='' AND u.VORNAME LIKE '$3$' AND u.NACHNAME!='' AND u.NACHNAME LIKE '$4$')".
            " OR ((u.STRASSE!='' AND u.PLZ!='' AND u.ORT!='' AND u.FK_COUNTRY!=0)".
            "    AND (u.STRASSE LIKE '$5$' AND u.PLZ LIKE '$6$' AND u.ORT LIKE '$7$' AND u.FK_COUNTRY LIKE '$8$'))");
        return $dataTable;
    }
    
}
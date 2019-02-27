<?php

switch ($_REQUEST["what"]) {
    case 'packetTimeout':
        // TODO: Escape interval (even if only accessiable by admin)
        $interval = (array_key_exists("interval", $_REQUEST) ? $_REQUEST["interval"] : "1 month");
        $arWhere = array();
        if (!empty($_REQUEST["where_user"])) {
            $userId = $db->fetch_atom("SELECT ID_USER FROM `user` WHERE NAME='".mysql_real_escape_string($_REQUEST["where_user"])."'");
            if ($userId > 0) {
                $arWhere[] = "FK_USER=".(int)$userId;
            } else {
                // ERROR!
                die(var_dump($userId));
                break;
            }
        }
        $query = "
            UPDATE `packet_order` SET 
              STAMP_START=IF(STAMP_START IS NULL, NULL, DATE_SUB(STAMP_START, interval ".$interval.")),
              STAMP_NEXT=IF(STAMP_NEXT IS NULL, NULL, DATE_SUB(STAMP_NEXT, interval ".$interval.")),
              STAMP_END=IF(STAMP_END IS NULL, NULL, DATE_SUB(STAMP_END, interval ".$interval.")),
              STAMP_CANCEL_UNTIL=IF(STAMP_CANCEL_UNTIL IS NULL, NULL, DATE_SUB(STAMP_CANCEL_UNTIL, interval ".$interval."))
            ".(!empty($arWhere) ? "WHERE ".implode(" AND ", $arWhere) : "");
        $result = $db->querynow($query);
        if ($result["rsrc"]) {
            // Success
            return;
        }
        die(var_dump($result));
        break;
    case 'packetCheck':
        $arWhere = array("TYPE='MEMBERSHIP'");
        $userId = $db->fetch_atom("SELECT ID_USER FROM `user` WHERE NAME='".mysql_real_escape_string($_REQUEST["where_user"])."'");
        if ($userId > 0) {
            $arWhere[] = "FK_USER=".(int)$userId;
        } else {
            // ERROR!
            die(var_dump($userId));
            break;
        }
        $packetCount = $db->fetch_atom($q="SELECT COUNT(*) FROM `packet_order` WHERE ".implode(" AND ", $arWhere));
        $packetCountExpected = (int)$_REQUEST["membership_count"];
        if ($packetCount != $packetCountExpected) {
            die("ERROR EXTENDING ACTIVE MEMBERSHIP! (Expected ".$packetCountExpected.", got ".$packetCount.")");
        }
        $packetId = $db->fetch_atom("SELECT MAX(ID_PACKET_ORDER) FROM `packet_order` WHERE ".implode(" AND ", $arWhere));
        $packetAdCount = $db->fetch_atom("SELECT COUNT(*) FROM `ad_master` WHERE FK_PACKET_ORDER!=".$packetId);
        $packetAdCountExpected = (int)$_REQUEST["ad_count"];
        if ($packetAdCount == $packetAdCountExpected) {
            // Success
            return;
        } else {
            die("ERROR EXTENDING ACTIVE ARTICLES! (Expected ".$packetAdCountExpected.", got ".$packetAdCount."; packet #".$packetId.")");
        }
        break;
    case 'adTimeout':
        $interval = (array_key_exists("interval", $_REQUEST) ? $_REQUEST["interval"] : "1 month");
        $arWhere = array();
        if (!empty($_REQUEST["where_user"])) {
            $userId = $db->fetch_atom("SELECT ID_USER FROM `user` WHERE NAME='".mysql_real_escape_string($_REQUEST["where_user"])."'");
            if ($userId > 0) {
                $arWhere[] = "FK_USER=".(int)$userId;
            } else {
                // ERROR!
                die(var_dump($userId));
                break;
            }
        }
        $arArticleTables = $db->fetch_col("SELECT T_NAME FROM `table_def`");
        $arArticleTables[] = "ad_master";
        foreach ($arArticleTables as $tableIndex => $tableName) {
            if ($tableName == "vendor_master") {
                continue;
            }
            $query = "
                UPDATE `".mysql_real_escape_string($tableName)."` SET 
                  STAMP_START=IF(STAMP_START IS NULL, NULL, DATE_SUB(STAMP_START, interval ".$interval.")),
                  STAMP_END=IF(STAMP_END IS NULL, NULL, DATE_SUB(STAMP_END, interval ".$interval.")),
                  STAMP_DEACTIVATE=IF(STAMP_DEACTIVATE IS NULL, NULL, DATE_SUB(STAMP_DEACTIVATE, interval ".$interval."))
                ".(!empty($arWhere) ? "WHERE ".implode(" AND ", $arWhere) : "");
            $result = $db->querynow($query);
            if (!$result["rsrc"]) {
                // Success
                die("Query failed: \n".$query);
            }
        }
        // Success
        return;
    case 'truncate':
        $ar_actions = Api_DatabaseTruncate::truncate(Api_DatabaseTruncate::$arOptions, $db, (array_key_exists("demo", $_REQUEST) ? (bool)$_REQUEST["demo"] : true));
        if (!empty($ar_actions)) {
            // Success
            return;
        }
        break;
    case 'cacheClear':
        require_once $ab_path . 'sys/lib.cache.adapter.php';
        $cacheAdapter = new CacheAdapter();
        $cacheAdapter->cacheTemplate();
        // Success
        return;
        break;
    default:
        die("TEST NOT FOUND!");
}

die("UNEXPECTED ERROR DURING TEST!");
<?php
/* ###VERSIONSBLOCKINLCUDE### */

function escape_csv_value($value) {
    $value = str_replace('"', '""', $value);
    if (preg_match('/,/', $value) ||  preg_match("/\n/", $value) || preg_match('/"/', $value)) {
        return '"'.$value.'"';
    } else {
        return $value;
    }
}
function csvEscapeString($text) {
    return str_replace(',', '\,', mysql_real_escape_string($text));
}

require_once $GLOBALS["ab_path"]."sys/lib.ad_table.php";

if (isset($_POST['DO'])) {
    switch ($_POST['DO']) {
        case 'EXPORT':
            /**
             * Export category table
             */
            $table = AdTable::getTableById($_POST['FK_TABLE']);
            $csv = array();
            $arLanguages = $GLOBALS["lang_list"];
            // Generate header line
            $line = array();
            $line[] = $table->getName().":FIELDS";
            foreach ($arLanguages as &$arLanguage) {
                $curLang = $arLanguage["BITVAL"].":".$arLanguage["ABBR"];
                $line[] = escape_csv_value($curLang.":V1");
                $line[] = escape_csv_value($curLang.":V2");
                $line[] = escape_csv_value($curLang.":T1_DESC");
                $line[] = escape_csv_value($curLang.":T1_HELP");
                $line[] = escape_csv_value($curLang.":T2_DESC");
                $line[] = escape_csv_value($curLang.":T2_HELP");
            }
            $line[] = escape_csv_value("F_NAME");
            $line[] = escape_csv_value("F_TYP");
            $line[] = escape_csv_value("B_ENABLED");
            $line[] = escape_csv_value("B_SEARCH");
            $line[] = escape_csv_value("B_NEEDED");
            $line[] = escape_csv_value("F_ORDER");
            $line[] = escape_csv_value("NO_CHANGE");
            $line[] = escape_csv_value("FK_LISTE");
            $line[] = escape_csv_value("FK_FIELD_GROUP");
            $line[] = escape_csv_value("IS_MASTER");
            $line[] = escape_csv_value("IS_SPECIAL");
            $csv[] = implode(",", $line);
            // Generate field lines
            $arFields = $table->getFields();
            foreach ($arFields as &$arField) {
                $line = array();
                // Add id
                $line[] = escape_csv_value($arField["ID_FIELD_DEF"]);
                // Add string fields
                foreach ($arLanguages as &$arLanguage) {
                    $arFieldStrings = $table->getFieldStrings($arField['ID_FIELD_DEF'], $arLanguage["BITVAL"]);
                    $line[] = escape_csv_value($arFieldStrings["V1"]);
                    $line[] = escape_csv_value($arFieldStrings["V2"]);
                    $line[] = escape_csv_value($arFieldStrings["T1_DESC"]);
                    $line[] = escape_csv_value($arFieldStrings["T1_HELP"]);
                    $line[] = escape_csv_value($arFieldStrings["T2_DESC"]);
                    $line[] = escape_csv_value($arFieldStrings["T2_HELP"]);
                }
                $line[] = escape_csv_value($arField["Field"]);
                $line[] = escape_csv_value($arField["F_TYP"]);
                $line[] = escape_csv_value($arField["B_ENABLED"]);
                $line[] = escape_csv_value($arField["B_SEARCH"]);
                $line[] = escape_csv_value($arField["B_NEEDED"]);
                $line[] = escape_csv_value($arField["F_ORDER"]);
                $line[] = escape_csv_value($arField["NO_CHANGE"]);
                $line[] = escape_csv_value($arField["FK_LISTE"]);
                $line[] = escape_csv_value($arField["FK_FIELD_GROUP"]);
                $line[] = escape_csv_value($arField["IS_MASTER"]);
                $line[] = escape_csv_value($arField["IS_SPECIAL"]);
                $csv[] = implode(",", $line);
            }
            // Output as file
            header('Content-type: application/csv');
            header('Content-Disposition: attachment; filename="kat_table_'.$table->getName().'.csv"');
            die(implode("\n", $csv));
            break;
        case 'EXPORT_KAT':
            /**
             * Export category table
             */
            $table = AdTable::getTableById($_POST['FK_TABLE']);
            $csv = array();
            $arCategories = $table->getCategories();
            $arLanguages = $GLOBALS["lang_list"];
            // Generate header line
            $line = array();
            $line[] = $table->getName().":CATEGORIES";
            foreach ($arCategories as $arCategory) {
                $line[] = $arCategory["ID_KAT"].":".$arCategory["V1"];
            }
            $csv[] = implode(",", $line);
            // Generate field lines
            $arFields = $table->getFields();
            foreach ($arFields as &$arField) {
                if ($arField["IS_SPECIAL"] > 0) {
                    continue;
                }
                $line = array();
                // Add id
                $line[] = escape_csv_value($arField["F_NAME"].":".$arField["V1"]);
                // Add string fields
                $arFieldCategories = $table->getFieldCategories($arField['ID_FIELD_DEF']);
                $arFieldCategoriesByCat = array();
                foreach ($arFieldCategories as $arField) {
                    $arFieldCategoriesByCat[ $arField['FK_KAT'] ] = $arField;
                }

                foreach ($arCategories as $arCategory) {
                    $arFieldCategory = $arFieldCategoriesByCat[ $arCategory["ID_KAT"] ];
                    $value = "";
                    if ($arFieldCategory["B_ENABLED"]) {
                        $value .= "A";  // Aktiv / Active
                        if ($arFieldCategory["B_NEEDED"]) {
                            $value .= "P";  // Pflichtfeld / required
                        }
                        if ($arFieldCategory["B_SEARCHFIELD"]) {
                            $value .= "S";  // Suchfeld / Searchfield
                        }
                    }
                    $line[] = escape_csv_value($value);
                }
                $csv[] = implode(",", $line);
            }
            // Output as file
            header('Content-type: application/csv');
            header('Content-Disposition: attachment; filename="kat_table_'.$table->getName().'.csv"');
            die(implode("\n", $csv));
            break;
        case 'IMPORT':
            /**
             * Import category table
             */
            $seperator = (isset($_POST['CSV_SEPERATOR']) ? $_POST['CSV_SEPERATOR'] : ",");
            $csvImport = false;
            $fileTarget = $ab_path."/cache/import_kat_table.csv";
            if (move_uploaded_file($_FILES['CSV']['tmp_name'], $fileTarget)) {
                $csvImport = str_replace("\r\n", "\n", file_get_contents($fileTarget));
                unlink($fileTarget);
            }
            if ($csvImport !== false) {
                $arSqlBatch = array();
                $arImport = explode("\n", $csvImport);
                // Parse header line
                $arLineHeader = str_getcsv(array_shift($arImport), $seperator);
                $arLanguages = array();
                $arLanguagesDb = $GLOBALS["lang_list"];
                list($tableName, $importType) = explode(":", $arLineHeader[0]);
                if ($importType == "FIELDS") {
                    $colLimit = count($arLineHeader)-1;
                    $colIndex = 1;
                    while (preg_match("/([0-9]{1,3})\:([a-z]{2}):(.+)/", $arLineHeader[$colIndex++], $arLanguage)) {
                        $arLanguages[ $arLanguage[2] ] = $arLanguage[1];
                    }
                    // Parse content

                    $tableId = $db->fetch_atom("SELECT ID_TABLE_DEF FROM table_def WHERE T_NAME='".mysql_real_escape_string($tableName)."'");
                    $table = AdTable::getTableById($tableId);
                    $arFields = $table->getFieldsByName();
                    foreach ($arImport as $csvLine) {
                        if (empty($csvLine)) {
                            continue;   // Skip empty lines
                        }
                        $arLine = str_getcsv($csvLine, $seperator);
                        // Initialize field variables
                        $arFieldCsv = array();
                        $arFieldSQL = array();
                        $arFieldCsv["ID_FIELD_DEF"] = $arLine[0];
                        $arFieldCsv["STRINGS"] = array();
                        $fieldLangBitfield = 0;
                        // Get string fields by language
                        $fieldIndex = 1;
                        foreach ($arLanguages as $langAbbr => $langBitval) {
                            if (is_array($arLanguagesDb[$langAbbr])) {
                                $fieldLangBitfield += $arLanguagesDb[$langAbbr]["BITVAL"];
                                $arFieldCsv["STRINGS"][$langAbbr]["V1"] = $arLine[$fieldIndex++];
                                $arFieldCsv["STRINGS"][$langAbbr]["V2"] = $arLine[$fieldIndex++];
                                $arFieldCsv["STRINGS"][$langAbbr]["T1_DESC"] = $arLine[$fieldIndex++];
                                $arFieldCsv["STRINGS"][$langAbbr]["T1_HELP"] = $arLine[$fieldIndex++];
                                $arFieldCsv["STRINGS"][$langAbbr]["T2_DESC"] = $arLine[$fieldIndex++];
                                $arFieldCsv["STRINGS"][$langAbbr]["T2_HELP"] = $arLine[$fieldIndex++];
                            } else {
                                // Unknown language, skip!
                                $fieldIndex += 6;
                            }
                        }
                        // Add fk to table
                        $arFieldSQL["FK_TABLE_DEF"] = $tableId;
                        // Add language bitfield
                        $arFieldSQL["BF_LANG_FIELD_DEF"] = $fieldLangBitfield;
                        // Add further fields
                        while ($fieldIndex < $colLimit) {
                            $fieldName = $arLineHeader[$fieldIndex];
                            $fieldValue = $arLine[$fieldIndex];
                            $arFieldCsv[$fieldName] = $fieldValue;
                            $fieldIndex++;
                            // Prepare SQL-Escaped names and values
                            $sqlFieldName = mysql_real_escape_string($fieldName);
                            $sqlFieldValue = ($fieldValue == '' ? null : $fieldValue);
                            $arFieldSQL[$sqlFieldName] = $sqlFieldValue;
                        }
                        // Compare with existing
                        if (array_key_exists($arFieldCsv["F_NAME"], $arFields)) {
                            $hasDifference = false;
                            $arFieldDb = $arFields[ $arFieldCsv["F_NAME"] ];
                            $arChangesSQL = array();
                            $arChangesSQLString = array();
                            $fieldLangBitfield = 0;
                            // Compare field properties
                            foreach ($arFieldCsv as $fieldName => $fieldValue) {
                                if ($fieldName == "ID_FIELD_DEF") {
                                    continue;   // Skip primary key
                                }
                                if ($fieldName == "STRINGS") {
                                    continue;   // Skip string array
                                }
                                if ($arFieldDb[$fieldName] === $fieldValue) {
                                    continue;   // Skip if unchanged
                                }
                                if (($arFieldDb[$fieldName] === null) && ($fieldValue == "")) {
                                    continue;
                                }
                                echo("Field change detected for '".$arFieldCsv["F_NAME"]."': ".$fieldName." from '".$arFieldDb[$fieldName]."' to '".$fieldValue."'.\n");
                                $arChangesSQL[] = "`".mysql_real_escape_string($fieldName)."`=".($fieldValue == '' ? "NULL" : "'".mysql_real_escape_string($fieldValue)."'");
                                $hasDifference = true;
                            }
                            // Compare translations
                            foreach ($arFieldCsv["STRINGS"] as $langAbbr => $arFieldStrings) {
                                if (!array_key_exists($langAbbr, $arLanguagesDb)) {
                                    continue;   // Unknown language, skip!
                                }
                                $arChangesSQLStringUpdate = array();
                                $langBitval = $arLanguagesDb[$langAbbr]["BITVAL"];
                                $fieldLangBitfield += $langBitval;
                                $arFieldStrings["T1"] = $arFieldStrings["T1_DESC"]."||".$arFieldStrings["T1_HELP"]."§§§".$arFieldStrings["T2_DESC"]."||".$arFieldStrings["T2_HELP"]."§§§".$arFieldStrings["T3_DESC"]."||".$arFieldStrings["T3_HELP"];
                                unset($arFieldStrings["T1_DESC"], $arFieldStrings["T1_HELP"], $arFieldStrings["T2_DESC"], $arFieldStrings["T2_HELP"], $arFieldStrings["T3_DESC"], $arFieldStrings["T3_HELP"]);
                                $arFieldStringsDb = $table->getFieldStrings($arFieldDb["ID_FIELD_DEF"], $langBitval);
                                $arFieldStringsDb["T1"] = $arFieldStringsDb["T1_DESC"]."||".$arFieldStringsDb["T1_HELP"]."§§§".$arFieldStringsDb["T2_DESC"]."||".$arFieldStringsDb["T2_HELP"]."§§§".$arFieldStringsDb["T3_DESC"]."||".$arFieldStringsDb["T3_HELP"];
                                unset($arFieldStringsDb["T1_DESC"], $arFieldStringsDb["T1_HELP"], $arFieldStringsDb["T2_DESC"], $arFieldStringsDb["T2_HELP"], $arFieldStringsDb["T3_DESC"], $arFieldStringsDb["T3_HELP"]);
                                foreach ($arFieldStrings as $fieldStringName => $fieldStringValue) {
                                    if ($arFieldStringsDb[$fieldStringName] != $fieldStringValue) {
                                        $arChangesSQLStringUpdate[] = "`".mysql_real_escape_string($fieldStringName)."`='".mysql_real_escape_string($fieldStringValue)."'";
                                    }
                                }
                                if (!empty($arChangesSQLStringUpdate)) {
                                    $sqlQuery = "INSERT INTO `string_field_def` (S_TABLE, FK, BF_LANG, T1, V1, V2)\n".
                                        "   VALUES ('field_def', ".$arFieldDb["ID_FIELD_DEF"].", ".$langBitval.", ".
                                        "'".mysql_real_escape_string($arFieldStrings['T1'])."', ".
                                        "'".mysql_real_escape_string($arFieldStrings['V1'])."', ".
                                        "'".mysql_real_escape_string($arFieldStrings['V2'])."')\n".
                                        "   ON DUPLICATE KEY UPDATE ".implode(", ", $arChangesSQLStringUpdate);
                                    $arChangesSQLString[] = $sqlQuery;
                                    $hasDifference = true;
                                }
                            }

                            if ($hasDifference) {
                                if (!empty($arChangesSQLString)) {
                                    echo("Updating translation strings: '".$arFieldCsv["F_NAME"]."'\n");
                                    foreach ($arChangesSQLString as $sqlQuery) {
                                        $sqlResult = $db->querynow($sqlQuery);
                                    }
                                    if ($arFieldDb["BF_LANG_FIELD_DEF"] != $fieldLangBitfield) {
                                        $arChangesSQL[] = "`BF_LANG_FIELD_DEF`=".$fieldLangBitfield;
                                    }
                                }
                                if (!empty($arChangesSQL)) {
                                    $sqlQuery = "UPDATE `field_def` SET ".implode(", ", $arChangesSQL)." WHERE ID_FIELD_DEF=".$arFieldDb["ID_FIELD_DEF"];
                                    $sqlResult = $db->querynow($sqlQuery);
                                }
                            }
                        } else {
                            // New field
                            echo("New field found: '".$arFieldCsv["F_NAME"]."'\n");
                            $hasDifference = true;
                            // Inserting the field entry
                            $arFieldSQL['SQL_FIELD'] = $arFieldSQL['F_NAME'];
                            unset($arFieldSQL['ID_FIELD_DEF']);
                            unset($arFieldOpt['F_NAME']);
                            $fieldId = $table->getTableDefObject()->saveField($arFieldSQL);
                            // Add translation strings
                            if ($fieldId > 0) {
                                // Prepare inserting the string entry(s)
                                foreach ($arFieldCsv["STRINGS"] as $langAbbr => $arFieldStrings) {
                                    $langBitval = $arLanguagesDb[$langAbbr]["BITVAL"];
                                    $textFull = $arFieldStrings["T1_DESC"]."||".$arFieldStrings["T1_HELP"]."§§§".$arFieldStrings["T2_DESC"]."||".$arFieldStrings["T2_HELP"]."§§§".$arFieldStrings["T3_DESC"]."||".$arFieldStrings["T3_HELP"];
                                    $sqlQuery = "INSERT INTO `string_field_def` (S_TABLE, FK, BF_LANG, V1, V2, T1) VALUES ".
                                        "('field_def', ".$fieldId.", ".$langBitval.", ".
                                        "'".mysql_real_escape_string($arFieldStrings["V1"])."', '".mysql_real_escape_string($arFieldStrings["V2"])."', ".
                                        "'".mysql_real_escape_string($textFull)."')";
                                    $sqlResult = $db->querynow($sqlQuery);
                                }
                                $sqlQuery = "UPDATE `field_def` SET BF_LANG_FIELD_DEF=".(int)$fieldLangBitfield." WHERE ID_FIELD_DEF=".$fieldId;
                                $db->querynow($sqlQuery);
                            } else {
                                echo("Failed to add field '".$arFieldCsv["F_NAME"]."'!\n");
                                var_dump($table->getTableDefObject()->err);
                                die(var_dump($arFieldSQL));
                            }
                        }
                        if ($hasDifference) {
                            //echo("Field changed!");
                        }
                    }
                    die("done!");
                    die(var_dump($arImport));
                }
                if ($importType == "CATEGORIES") {
                    $colLimit = count($arLineHeader)-1;
                    $colIndex = 1;
                    while (preg_match("/([0-9]+)\:(.+)/", $arLineHeader[$colIndex++], $arCategory)) {
                        $arCategories[] = $arCategory[1];
                    }
                    // Parse content
                    $tableId = $db->fetch_atom("SELECT ID_TABLE_DEF FROM table_def WHERE T_NAME='".mysql_real_escape_string($tableName)."'");
                    $table = AdTable::getTableById($tableId);
                    $arFields = $table->getFieldsByName();
                    foreach ($arImport as $csvLine) {
                        if (empty($csvLine)) {
                            continue;   // Skip empty lines
                        }
                        $arLine = str_getcsv($csvLine, $seperator);
                        $strField = array_shift($arLine);
                        list($fieldName, $fieldLabel) = explode(":", $strField);
                        $fieldId = $db->fetch_atom("SELECT ID_FIELD_DEF FROM `field_def` WHERE F_NAME='".mysql_real_escape_string($fieldName)."' AND FK_TABLE_DEF=".$tableId);
                        foreach ($arLine as $katIndex => $flags) {
                            $flags = strtoupper($flags);
                            $katId = $arCategories[$katIndex];
                            $flagEnabled = (strpos($flags, "A") !== false ? 1 : 0);
                            $flagRequired = (strpos($flags, "P") !== false ? 1 : 0);
                            $flagSearch = (strpos($flags, "S") !== false ? 1 : 0);
                            $sqlQuery = "INSERT INTO `kat2field` (FK_KAT, FK_FIELD, B_ENABLED, B_NEEDED, B_SEARCHFIELD) ".
                                "VALUES (".(int)$katId.", ".(int)$fieldId.", ".$flagEnabled.", ".$flagRequired.", ".$flagSearch.") ".
                                "ON DUPLICATE KEY UPDATE B_ENABLED=".$flagEnabled.", B_NEEDED=".$flagRequired.", B_SEARCHFIELD=".$flagSearch;
                            $res = $db->querynow($sqlQuery);
                        }
                    }
                    die("done!");
                    die(var_dump($arImport));
                }
            }
            break;
    }
}

$arTables = AdTable::getTableList(true);
foreach ($arTables as &$arTable) {
    $arTable["KAT_COUNT"] = $db->fetch_atom("SELECT count(*) FROM `kat` WHERE ROOT=1 AND KAT_TABLE='".$arTable['Name']."'");
    $arTable["FIELD_COUNT"] = count($arTable["FIELDS"]);
}

$tpl_content->addlist("liste", $arTables, "tpl/de/kat_tables_csv.row.htm");

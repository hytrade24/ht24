<?php
/* ###VERSIONSBLOCKINLCUDE### */

use Symfony\Component\Yaml\Yaml;

require_once $ab_path.'admin/sys/lib.nestedsets.php'; // Nested Sets
require_once $ab_path.'sys/lib.cache.php';
require_once $ab_path.'sys/lib.cache.adapter.php';
require_once $ab_path.'admin/sys/lib.perm_admin.php';

require_once $ab_path."lib/yaml/Yaml.php";
require_once $ab_path."lib/yaml/Parser.php";
require_once $ab_path."lib/yaml/Inline.php";
require_once $ab_path."lib/yaml/Unescaper.php";
require_once $ab_path."lib/yaml/DumperMod.php";
require_once $ab_path."lib/yaml/Escaper.php";
require_once $ab_path."lib/yaml/Exception/ExceptionInterface.php";
require_once $ab_path."lib/yaml/Exception/RuntimeException.php";
require_once $ab_path."lib/yaml/Exception/ParseException.php";
require_once $ab_path."lib/yaml/Exception/DumpException.php";

class Update {
    private static $maxExecutionTime = 5;
    private static $editorModeByExt = array(
        "htm"   =>  "htmlmixed",
        "html"  =>  "htmlmixed",
        "css"   =>  "css",
        "js"    =>  "javascript",
        "php"   =>  "php",
        "sql"   =>  "sql",
        "xml"   =>  "xml",
        "png"   =>  "image",
        "jpg"   =>  "image",
        "jpeg"  =>  "image"
    );

    /**
     * @var ebiz_db     Database class
     */
    private $db;
    private $file;
    private $logFile;
    /**
     * @var array       List of instructions to be executed
     */
    private $instructions;
    /**
     * @var int         Index of the next instruction to be executed
     */
    private $position;

    private $error;
    private $message;
    private $regexpVersionBlock;
    private $currentVersionBlock;

    function __construct(ebiz_db $db, $updateFile) {
        $this->db = $db;
        $this->file = $updateFile;
        $this->instructions = Yaml::parse( file_get_contents($this->file) );
        $this->position = (file_exists($this->file.".progress") ? (int)file_get_contents($this->file.".progress") : 0);
        $this->error = false;
        $this->message = false;
        global $ab_path;
        $this->logFile = fopen($ab_path."admin/update.log", "a+");
        $this->regexpVersionBlock = "/(".preg_quote("/**", "/")."\n".
            preg_quote(" * ebiz-trader", "/")."\n".
            preg_quote(" *", "/")."\n".
            preg_quote(" * @copyright Copyright (c) ", "/")."[0-9]+".preg_quote(" ebiz-consult e.K.", "/")."\n".
            preg_quote(" * @version ", "/").")(.+)("."\n".
            preg_quote(" */", "/").")/m";
        $this->currentVersionBlock = "/**\n".
            " * ebiz-trader\n".
            " *\n".
            " * @copyright Copyright (c) 2012 ebiz-consult e.K.\n".
            " * @version {VERSION}\n".
            " */";
    }

    function __destruct() {
        fclose($this->logFile);
    }

    private static function encodeHtmlSimple($value) {
        return addnoparse(str_replace(array('&', '<', '>', '"'), array('&amp;', '&lt;', '&gt;', '&quot;'), $value));
    }

    private function addLogMessage($text, $indent = 0, $options = array()) {
        $timestamp = date("d.m.Y H:i:s");
        $textIntent = str_repeat(" ", $indent);
        $arText = explode("\n", $text);
        $arTextFinal = array();
        foreach ($arText as $lineIndex => $lineText) {
            $arTextFinal[] = "[".$timestamp."] ".$textIntent.$lineText;
        }
        fwrite($this->logFile, implode("\n", $arTextFinal)."\n");
    }

    /**
     * @param $parameters   Array containing the settings for the new article field
     * @return bool         true if the field was added without problems
     */
    private function articleFieldAdd($parameters) {
        global $ab_path, $s_lang, $langval, $db;
        // Add log entry
        $this->addLogMessage("Artikel-Feld '".$parameters['V1']."' / '".$parameters['F_NAME']."' wird hinzugefügt...");
        // Inlcude libraries
        require_once $ab_path."admin/sys/tabledef.php";
        require_once $ab_path.'admin/sys/lib.import_export_filter.php';
        require_once $ab_path.'admin/sys/lib.import.php';
        require_once $ab_path.'sys/lib.hdb.databasestructure.php';

       // Initialize required classes
        $import = new import();
        $table = new tabledef();
        $manufacturerDatabaseStructureManagement = ManufacturerDatabaseStructureManagement::getInstance($db);
        $table->getTable($parameters['table']);
        // Process parameters
        if (($parameters['F_TYP'] == 'LIST') || ($parameters['F_TYP'] == 'VARIANT')) {
            if ($parameters['FK_LISTE'] == 'NEW' && empty($parameters['ITEMS'])) {
                $this->error = "Liste für neues Feld enthält keine Werte!";
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
            if (!$parameters['FK_LISTE']) {
                $this->error = "Keine Auswahlliste gewählt!";
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        }	// field type is list
        if (!$parameters['table']) {
            $this->error = "Keine Tabelle gewählt!";
            // Add log entry
            $this->addLogMessage($this->error, 2);
            return false;
        } else if ($parameters['table'] == "artikel_master") {
            // Set as master field if added to the master table
            $parameters['IS_MASTER'] = 1;
        }
        if (isset($parameters['SQL_FIELD']) && !preg_match("/^([_a-zA-Z0-9]+)$/", $parameters['SQL_FIELD'])) {
            $this->error = "Ungültiger SQL Feld Name! Der SQL Name darf nur aus Buchstaben, Zahlen und Unterstrichen bestehen.";
            // Add log entry
            $this->addLogMessage($this->error, 2);
            return false;
        }
        if (!is_array($parameters['V1'])) {
            $this->error = "Kein Name für das Feld gewählt!";
            // Add log entry
            $this->addLogMessage($this->error, 2);
            return false;
        }
        if (!$parameters['F_TYP']) {
            $this->error = "Kein Feldtyp gewählt!";
            // Add log entry
            $this->addLogMessage($this->error, 2);
            return false;
        }
        // Check multilingual fields
        $arLanguages = array($s_lang);
        $arLanguagesBitvals = $this->db->fetch_nar("SELECT ABBR, BITVAL FROM `lang`");
        if (is_array($parameters['V1'])) {
            foreach ($parameters['V1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
                $check = $this->db->fetch_atom("
                    SELECT
                        s.FK
                    FROM
                        string_field_def s
                    JOIN
                        field_def f on s.FK=f.ID_FIELD_DEF
                    JOIN
                        table_def t on f.FK_TABLE_DEF=t.ID_TABLE_DEF
                    WHERE
                        s.V1='".mysql_real_escape_string($content)."'
                        AND s.FK <> ".(int)$parameters['ID_FIELD_DEF']);
                if($check > 0) {
                    $this->error = "Der Name wird in dieser Tabelle bereits verwendet!";
                    // Add log entry
                    $this->addLogMessage($this->error, 2);
                    return false;
                }
            }
        }
        if (is_array($parameters['V2'])) {
            foreach ($parameters['V2'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        $arDescFields = array('T1_DESC', 'T1_HELP', 'T2_DESC', 'T2_HELP');
        foreach ($arDescFields as $i => $fieldName) {
            if (is_array($parameters[$fieldName])) {
                foreach ($parameters[$fieldName] as $language => $content) {
                    if (!in_array($language, $arLanguages)) {
                        $arLanguages[] = $language;
                    }
                }
            }
        }
        // No errors so far, add field
        $langvalOrg = $langval;
        $s_langOrg = $s_lang;
        foreach ($arLanguages as $langIndex => $language) {
            $s_lang = $language;
            $langval = $arLanguagesBitvals[$language];
            $arField = $parameters;
            if (isset($parameters["V1"][$language])) {
                $arField["V1"] = $parameters["V1"][$language];
            } else {
                unset($arField["V1"]);
            }
            if (isset($parameters["V2"][$language])) {
                $arField["V2"] = $parameters["V2"][$language];
            } else {
                unset($arField["V2"]);
            }
            if (!empty($parameters['T1_DESC'][$language]) || !empty($parameters['T1_HELP'][$language])
                || !empty($parameters['T2_DESC'][$language]) || !empty($parameters['T2_HELP'][$language])) {
                $parameters['T1'] = $parameters['T1_DESC']."||".$parameters['T1_HELP'][$language].
                    "§§§".$parameters['T2_DESC'][$language]."||".$parameters['T2_HELP'][$language].
                    "§§§".$parameters['T3_DESC'][$language]."||".$parameters['T3_HELP'][$language];
            } else if (isset($parameters["T1"][$language])) {
                $arField["T1"] = $parameters["T1"][$language];
            } else {
                unset($arField["T1"]);
            }
            unset($arField["ROLES"]);
            $id_field = $table->saveField($arField);
            if (!$id_field) {
                $this->error = "Datenbank-Fehler!\n".implode("\n", $table->err);
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            } else {
                if (empty($parameters["ID_FIELD_DEF"]) && ($id_field > 0)) {
                    $parameters["ID_FIELD_DEF"] = $id_field;
                }
            }
        }
        $langval = $langvalOrg;
        $s_lang = $s_langOrg;

        if(!empty($table->err)) {
            $err = $table->err;
        } else {
            $import->updateTableFieldChange($parameters);
            $manufacturerDatabaseStructureManagement->updateHdbTableFieldChange($parameters);
            if (!($parameters["ID_FIELD_DEF"] > 0)) {
                $ar_katlist = array_keys(
                    $this->db->fetch_nar("SELECT ID_KAT FROM `kat` WHERE KAT_TABLE='".mysql_real_escape_string($parameters['table'])."'")
                );
                $ar_kat2field = array();
                if (isset($parameters["INITIALLY_ENABLED"])) {
                    foreach ($ar_katlist as $index => $katId) {
                        $ar_kat2field[] = "(".$katId.", ".$id_field.", 1, ".$parameters['B_NEEDED'].", ".($parameters['B_SEARCH'] > 0 ? 1 : 0).")";
                    }
                } else {
                    foreach ($ar_katlist as $index => $katId) {
                        $ar_kat2field[] = "(".$katId.", ".$id_field.", 0, ".$parameters['B_NEEDED'].", ".($parameters['B_SEARCH'] > 0 ? 1 : 0).")";
                    }
                }
                $query = "INSERT IGNORE INTO `kat2field` (FK_KAT, FK_FIELD, B_ENABLED, B_NEEDED, B_SEARCHFIELD) ".
                    "VALUES \n  ".implode(",\n   ", $ar_kat2field);
                $this->db->querynow($query);
            }
        }
        // Add log entry
        $this->addLogMessage("Artikel-Feld '".$parameters['F_NAME']."' hinzugefügt!");
        return true;
    }

    /**
     * @param $parameters   Array containing the settings for the new article field
     * @return bool         true if the field was added without problems
     */
    private function articleFieldRem($parameters) {
        global $ab_path;
        $fieldName = $parameters['SQL_FIELD'];
        // Add log entry
        $this->addLogMessage("Artikel-Feld '".$fieldName."' wird gelöscht...");
        // Inlcude libraries
        require_once $ab_path."admin/sys/tabledef.php";
        require_once $ab_path.'admin/sys/lib.import_export_filter.php';
        require_once $ab_path.'admin/sys/lib.import.php';
        require_once $ab_path.'sys/lib.hdb.databasestructure.php';
        // Initialize required classes
        $import = new import();
        $table = new tabledef();
        $manufacturerDatabaseStructureManagement = ManufacturerDatabaseStructureManagement::getInstance($db);
        $table->getTable($parameters['table']);
        $table->getFields();
        // Try to delete the field
        if (!is_array($table->tables[$table->table]['FIELDS'][$fieldName])) {
            // Set error message
            $this->error = "Das Feld konnte nicht gefunden werden!\n".implode("\n", $table->err);
            return false;
        } else {
            // Delete field
            $table->deleteTableField($fieldName);
            $import->deleteTableFieldChange($fieldName, $parameters['table']);
            $manufacturerDatabaseStructureManagement->deleteTableFieldChange($fieldName, $parameters['table']);

            // Add log entry
            $this->addLogMessage("Artikel-Feld '".$parameters['SQL_FIELD']."' gelöscht!");
            return true;
        }
    }

    /**
     * @param $parameters   Array containing the settings for clearing the cache
     * @return bool         true if the cache was successfully cleared
     */
    private function cacheClear($parameters) {
        global $ab_path;
        // Add log entry
        $this->addLogMessage("Cache wird geleert...");
        // Clear cache
        $cacheAdapter = new CacheAdapter();
        switch ($parameters['type']) {
            case 'template':
                $cacheAdapter->cacheTemplate();
                break;
            case 'content':
                $cacheAdapter->cacheContent();
                break;
            case 'step_template':
            case 'step_content':
            case 'step_all':
                $realType = ($parameters['type'] == 'step_template')?'template':'all';
                $stepIndex = (int)$parameters['step'];
                $stepCount = 1;
                while ($stepIndex < $stepCount) {
                    $stepResult = $cacheAdapter->cacheStep($stepIndex, $realType);
                    if($stepResult == false) {
                        $this->error = 'Failed to clear cache!';
                        // Add log entry
                        $this->addLogMessage($this->error, 2);
                        return false;
                    } else {
                        $stepCount = $stepResult['countSteps'];
                        $stepIndex++;
                    }
                }
                break;
            default:
                $cacheAdapter->cacheAll();
        }
        return true;
    }

    /**
     * Creates a file-diff of the two given files
     * @param $fileNameA
     * @param $fileNameB
     * @return array|bool   Returns the diff output as array (each entry is a line)
     */
    private function createDiff($fileNameA, $fileNameB, $diffWidth = 130) {
        $diffWidthMid = ($diffWidth / 2) - 1;
        #$diffOutput = array();
        $diffResult = exec($cmd="/usr/bin/sdiff --diff-program=/usr/bin/diff -a -t -w ".$diffWidth." ".escapeshellarg($fileNameA)." ".escapeshellarg($fileNameB)." 2>&1", $diffOutput);
        if ($diffResult !== false) {
            foreach ($diffOutput as $diffLineIndex => $diffLine) {
                $diffLineIso = utf8_encode($diffLine);
                $utf8FixCount = 0;
                if (strlen($diffLine) < strlen($diffLineIso)) {
                    // TODO Solve this problem better
                    $utf8FixCount = (strlen($diffLineIso) - strlen($diffLine));
                    $diffIndicator = substr($diffLine, $diffWidthMid + $utf8FixCount, 1);
                    $diffLine = substr($diffLine, 0, $diffWidthMid + $utf8FixCount / 2 - 1)." ".substr($diffLine, $diffWidthMid + $utf8FixCount);
                } else {
                    $diffIndicator = substr($diffLine, $diffWidthMid, 1);
                }
                switch ($diffIndicator) {
                    default:
                    case ' ':   // Equal
                        $diffOutput[$diffLineIndex] = '<pre class="diffEqual">'.htmlentities($diffLine, ENT_IGNORE, 'UTF-8').'</pre>';
                        break;
                    case '|':   // Modified
                        $diffOutput[$diffLineIndex] = '<pre class="diffModified">'.htmlentities($diffLine, ENT_IGNORE, 'UTF-8').'</pre>';
                        break;
                    case '<':   // Removed
                        $diffOutput[$diffLineIndex] = '<pre class="diffRemoved">'.htmlentities($diffLine, ENT_IGNORE, 'UTF-8').'</pre>';
                        break;
                    case '>':   // Added
                        $diffOutput[$diffLineIndex] = '<pre class="diffAdded">'.htmlentities($diffLine, ENT_IGNORE, 'UTF-8').'</pre>';
                        break;
                }
            }
            return $diffOutput;
        } else {
            return false;
        }
    }

    /**
     * Delete the update directory
     */
    public function delete() {
        global $ab_path;
        system("rm -R ".escapeshellarg($ab_path."update"));
    }

    /**
     * @param $parameters   Array containing the settings for checking the design templates
     * @return bool         true if there are no files that have to be modified
     */
    private function designCheck($parameters) {
        global $ab_path, $s_lang;
        // Add log entry
        $this->addLogMessage("Notwendige Anpassungen am User-Design werden ermittelt...");
        // Check design changes
        $templateName = $GLOBALS['nar_systemsettings']['SITE']['TEMPLATE'];
        $dirDesign = $ab_path."design";
        $dirDesignDefault = $dirDesign."/default";
        $dirDesignUser = $dirDesign."/".$templateName;
        $dirDesignBackup = $ab_path."update/".$parameters['backup']."/design/default";
        $messages = $this->designCheckRecursive($dirDesignDefault, $dirDesignUser, $dirDesignBackup, (isset($parameters['language']) ? $parameters['language'] : 'de'));
        if (!empty($messages)) {
            $tplDesignDiff = new Template($ab_path."tpl/".$s_lang."/system-update-diff.htm");
            $tplDesignDiff->addvar("action", "designUpdate");
            $tplDesignDiff->addvar("actionRestore", "designRestore");
            $tplDesignDiff->addvar("rows", $messages);
            $this->message = $tplDesignDiff->process(true);
            return false;
        }
        return true;
    }

    private function designCheckRecursive($dirDefault, $dirUser, $dirBackup, $language = "de", $file = "", &$messages = array()) {
        global $ab_path, $s_lang;
        if (is_file($dirDefault."/default/".$file)) {
            $fileDefault = $dirDefault."/default/".$file;
            $fileUser = $dirUser."/".$language."/".$file;
            if (!file_exists($fileUser)) {
                $dirUser .= "/default";
                $fileUser = $dirUser."/".$file;
            } else {
                $dirUser .= "/".$language;
            }
            $fileBackup = $dirBackup."/".$language."/".$file;
            if (!file_exists($fileBackup)) {
                $dirBackup .= "/default";
                $fileBackup = $dirBackup."/".$file;
            } else {
                $dirBackup .= "/".$language;
            }
            if (file_exists($fileUser) && (file_exists($fileBackup) || file_exists($fileDefault))) {
                if ((filemtime($fileDefault) > filemtime($fileUser)) && (md5_file($fileDefault) != md5_file($fileUser))) {
                    $contentFileBackup = (file_exists($fileBackup) ? file_get_contents($fileBackup) : false);
                    $contentFileUser = file_get_contents($fileUser);
                    $contentFileDefault = (file_exists($fileDefault) ? file_get_contents($fileDefault) : false);
                    $versionNumber = EBIZ_TRADER_VERSION;
                    if (($contentFileDefault !== false) && preg_match($this->regexpVersionBlock, $contentFileDefault, $arMatchVersion)) {
                        $versionNumber = $arMatchVersion[2];
                    }
                    if (($contentFileBackup !== false) && preg_match($this->regexpVersionBlock, $contentFileBackup)) {
                        // Replace by current version number for easier compare
                        $contentFileBackup = preg_replace($this->regexpVersionBlock, '${1}'.$versionNumber.'${3}', $contentFileBackup);
                    }
                    if (($contentFileUser !== false) && preg_match($this->regexpVersionBlock, $contentFileUser)) {
                        // Replace by current version number for easier compare
                        $contentFileUser = preg_replace($this->regexpVersionBlock, '${1}'.$versionNumber.'${3}', $contentFileUser);
                    }
                    if ($contentFileDefault == $contentFileUser) {
                        // Only version number changed! Skip file.
                        return $messages;
                    }
                    $fileExt = strtolower(array_pop(explode('.',$fileDefault)));
                    $editorMode = false;
                    if (array_key_exists($fileExt, self::$editorModeByExt)) {
                        $editorMode = self::$editorModeByExt[$fileExt];
                    }
                    $saved = file_exists($fileUser.".update");
                    $tplDiff = new Template($ab_path."tpl/".$s_lang."/system-update-diff.design.row.htm");
                    $tplDiff->addvar("FILENAME", "/".$file);
                    $tplDiff->addvar("PATH", str_replace($ab_path, "", $dirUser));
                    $tplDiff->addvar("PATH_BACKUP", str_replace($ab_path, "", $dirBackup));
                    $tplDiff->addvar("PATH_UPDATE", str_replace($ab_path, "", $dirDefault."/default"));
                    $tplDiff->addvar("SAVED", ($saved ? 1 : 0));
                    $tplDiff->addvar("EDITOR_MODE", $editorMode);
                    $tplDiff->addvar("DIFF_HASH", md5($file));
                    if ($contentFileBackup !== false) {
                        $contentLeft = self::encodeHtmlSimple($contentFileBackup);
                        if (@json_encode($contentLeft) == "null") {
                            $this->addLogMessage($fileBackup." ist kein gültiges UTF-8!", 2);
                            return $messages;   // Invalid file encoding! Do not show this file.
                        }
                        $tplDiff->addvar("left", $contentLeft);
                    }
                    if ($saved) {
                        $contentCenter = self::encodeHtmlSimple(file_get_contents($fileUser.".update"));
                        $contentCenterJson = @json_encode($contentCenter);
                        $contentRight = self::encodeHtmlSimple($contentFileDefault);
                        $contentRightJson = @json_encode($contentRight);
                        if (($contentCenterJson === false) || ($contentCenterJson == "null")) {
                            $this->addLogMessage($fileUser.".update"." ist kein gültiges UTF-8!", 2);
                            return $messages;   // Invalid file encoding! Do not show this file.
                        }
                        if (($contentRightJson === false) || ($contentRightJson == "null")) {
                            $this->addLogMessage($fileDefault." ist kein gültiges UTF-8!", 2);
                            return $messages;   // Invalid file encoding! Do not show this file.
                        }
                        $tplDiff->addvar("center", $contentCenter);
                        $tplDiff->addvar("right", $contentRight);
                    } else {
                        $contentCenter = self::encodeHtmlSimple($contentFileDefault);
                        $contentCenterJson = @json_encode($contentCenter);
                        $contentRight = self::encodeHtmlSimple($contentFileUser);
                        $contentRightJson = @json_encode($contentRight);
                        if (($contentCenterJson === false) || ($contentCenterJson == "null")) {
                            $this->addLogMessage($fileDefault." ist kein gültiges UTF-8!", 2);
                            return $messages;   // Invalid file encoding! Do not show this file.
                        }
                        if (($contentRightJson === false) || ($contentRightJson == "null")) {
                            $this->addLogMessage($fileUser." ist kein gültiges UTF-8!", 2);
                            return $messages;   // Invalid file encoding! Do not show this file.
                        }
                        $tplDiff->addvar("center", $contentCenter);
                        $tplDiff->addvar("right", $contentRight);
                    }
                    $messages[] = $tplDiff;
                    // Add log entry
                    $this->addLogMessage($file." muss angepasst werden!", 2);
                }
            }
        } else {
            $dirBase = $dirDefault."/default/".$file;
            $dirListing = dir($dirBase);
            while (false !== ($filename = $dirListing->read())) {
                if (($filename != ".") && ($filename != "..")) {
                    $fileFull = (empty($file) ? $filename : $file."/".$filename);
                    $this->designCheckRecursive($dirDefault, $dirUser, $dirBackup, $language, $fileFull, $messages);
                }
            }
        }
        return $messages;
    }

    /**
     * @param $filename
     * @return bool
     */
    public function designUpdate($filename, $content, $finish = false) {
        global $ab_path;
        if (file_exists($ab_path.$filename)) {
            $updateFile = $ab_path.$filename.".update";
            file_put_contents($updateFile, $content);
            if ($finish) {
                // Delete current file
                unlink($ab_path.$filename);
                // Rename new file
                rename($updateFile, $ab_path.$filename);
            }
            return true;
        } else {
            $this->error = 'Datei existiert nicht: '.$filename;
            // Add log entry
            $this->addLogMessage($this->error, 2);
            return false;
        }
    }

    /**
     * @param $filename     Path to file (relative to root directory)
     * @param $path         Path to user design (relative to root directory)
     * @param $pathUpdate   Path to default design (relative to root directory)
     * @param $pathBackup   Path to backup design (relative to root directory)
     */
    public function designRestore($filename, $path, $pathUpdate, $pathBackup) {
        global $ab_path;
        $arResult = array("success" => false);
        $fileBackup = $ab_path.$pathBackup.$filename;
        $fileCur = $ab_path.$path.$filename;
        $fileUpdate = $ab_path.$pathUpdate.$filename;
        if (file_exists($fileCur) && file_exists($fileCur.".update") && file_exists($fileUpdate)) {
            // Remove changed file
            unlink($fileCur.".update");
            // Return result
            $arResult["success"] = true;
            $arResult["content"] = array(
                "center"    => file_get_contents($fileUpdate),
                "right"     => file_get_contents($fileCur)
            );
            if (file_exists($fileBackup)) {
                $arResult["content"]["left"] = file_get_contents($fileBackup);
            }
        }
        header("Content-Type: application/json");
        die(json_encode($arResult));
    }

    /**
     * @param $filename
     * @return bool
     */
    public function designSkip($filename) {
        global $ab_path;
        if (file_exists($ab_path.$filename)) {
            if (touch($ab_path.$filename)) {
                // Add log entry
                $this->addLogMessage("Design-Anpassungen übersprungen: '".$filename."'", 2);
                return true;
            } else {
                $this->error = 'Fehler beim löschen der Datei: '.$filename;
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        } else {
            $this->error = 'Datei existiert nicht: '.$filename;
            // Add log entry
            $this->addLogMessage($this->error, 2);
            return false;
        }
    }

    /**
     * @param $parameters   Array containing the settings for the file copy
     * @return bool         true if the files were copied without problems
     */
    private function filesCopy($parameters) {
        global $ab_path, $s_lang;
        // Add log entry
        $this->addLogMessage("Dateien werden kopiert...");
        // Check update files
        $dirBackup = $ab_path."update/".$parameters['backup'];
        $dirTarget = $ab_path."update/".$parameters['source'];
        $arDiffs = array();
        $arFilesNew = $this->getFilesMD5($dirTarget);
        foreach ($arFilesNew as $fileName => $fileNewMd5) {
            $fileOriginalMd5 = false;
            $fileCurMd5 = false;
            $fileCurFull = $ab_path.ltrim($fileName, '/');
            $fileBakFull = $dirBackup.$fileName;
            $fileNewFull = $dirTarget.$fileName;
            if (file_exists($fileBakFull)) {
                $fileOriginalMd5 = md5_file($fileBakFull);
            }
            if (file_exists($fileCurFull)) {
                $fileCurMd5 = md5_file($fileCurFull);
            }
            if ($fileCurMd5 !== false) {
                if (($fileCurMd5 != $fileOriginalMd5) && ($fileCurMd5 != $fileNewMd5)) {
                    $contentFileBackup = (file_exists($fileBakFull) ? file_get_contents($fileBakFull) : false);
                    $contentFileUser = file_get_contents($fileCurFull);
                    $contentFileDefault = file_get_contents($fileNewFull);
                    $versionNumber = EBIZ_TRADER_VERSION;
                    if (($contentFileDefault !== false) && preg_match($this->regexpVersionBlock, $contentFileDefault, $arMatchVersion)) {
                        $versionNumber = $arMatchVersion[2];
                    }
                    if (($contentFileBackup !== false) && preg_match($this->regexpVersionBlock, $contentFileBackup)) {
                        // Replace by current version number for easier compare
                        $contentFileBackup = preg_replace($this->regexpVersionBlock, '${1}'.$versionNumber.'${3}', $contentFileBackup);
                    }
                    if ($contentFileUser !== false) {
                        if (preg_match($this->regexpVersionBlock, $contentFileUser)) {
                            // Replace by current version number for easier compare
                            $contentFileUser = preg_replace($this->regexpVersionBlock, '${1}'.$versionNumber.'${3}', $contentFileUser);
                        } else {
                            $contentFileUser = str_replace("/* ###VERSIONSBLOCKINLCUDE### */", str_replace("{VERSION}", $versionNumber, $this->currentVersionBlock), $contentFileUser);
                        }
                    }
                    if (($contentFileBackup == $contentFileUser) || ($contentFileDefault == $contentFileUser)) {
                        // Only version number changed! Skip file.
                        continue;
                    }                    
                    $saved = false;
                    if (file_exists($fileCurFull.".update")) {
                        // File was already partially merged!
                        $fileCurFull = $fileCurFull.".update";
                        $saved = true;
                    }
                    $fileExt = strtolower(array_pop(explode('.', $fileName)));
                    $editorMode = false;
                    if (array_key_exists($fileExt, self::$editorModeByExt)) {
                        $editorMode = self::$editorModeByExt[$fileExt];
                    }
                    if ($editorMode == "image") {
                        // Diff images
                        $tplDiffFile = new Template($ab_path."tpl/".$s_lang."/system-update-diff.images.row.htm");
                        $tplDiffFile->addvar("FILENAME", str_replace($ab_path, "", $fileName));
                        $tplDiffFile->addvar("PATH_UPDATE", str_replace($ab_path, "", $dirTarget));
                        $tplDiffFile->addvar("PATH_BACKUP", str_replace($ab_path, "", $dirBackup));
                        $tplDiffFile->addvar("SAVED", ($saved ? 1 : 0));
                        $tplDiffFile->addvar("EDITOR_MODE", $editorMode);
                        $tplDiffFile->addvar("DIFF_HASH", md5($fileName));
                        if ($contentFileBackup !== false) {
                            $tplDiffFile->addvar("left", $fileBakFull);
                        }
                        $tplDiffFile->addvar("center", $fileNewFull);
                        $tplDiffFile->addvar("right", $fileCurFull);
                        $arDiffs[] = $tplDiffFile;
                    } else {
                        if (json_encode($contentFileBackup) === false) {
                            $this->error = 'Datei enthält kein gültiges UTF-8: '.$fileBakFull;
                            return false;
                        }
                        if (json_encode($contentFileUser) === false) {
                            $this->error = 'Datei enthält kein gültiges UTF-8: '.$fileCurFull;
                            return false;
                        }
                        if (json_encode($contentFileDefault) === false) {
                            $this->error = 'Datei enthält kein gültiges UTF-8: '.$fileNewFull;
                            return false;
                        }
                        // Diff editor
                        $tplDiffFile = new Template($ab_path."tpl/".$s_lang."/system-update-diff.files.row.htm");
                        $tplDiffFile->addvar("FILENAME", str_replace($ab_path, "", $fileName));
                        $tplDiffFile->addvar("PATH_UPDATE", str_replace($ab_path, "", $dirTarget));
                        $tplDiffFile->addvar("PATH_BACKUP", str_replace($ab_path, "", $dirBackup));
                        $tplDiffFile->addvar("SAVED", ($saved ? 1 : 0));
                        $tplDiffFile->addvar("EDITOR_MODE", $editorMode);
                        $tplDiffFile->addvar("DIFF_HASH", md5($fileName));
                        if ($contentFileBackup !== false) {
                            $tplDiffFile->addvar("left", self::encodeHtmlSimple($contentFileBackup));
                        }
                        $tplDiffFile->addvar("center", self::encodeHtmlSimple($contentFileDefault));
                        $tplDiffFile->addvar("right", self::encodeHtmlSimple($contentFileUser));
                        $arDiffs[] = $tplDiffFile;
                    }
                    // Add log entry
                    $this->addLogMessage("Datei wurde modifiziert und muss zusammengeführt werden: '".$fileName."'", 2);
                }
            }
        }
        if (!empty($arDiffs)) {
            $tplDiff = new Template($ab_path."tpl/".$s_lang."/system-update-diff.htm");
            $tplDiff->addvar("action", "filesUpdate");
            $tplDiff->addvar("actionRestore", "filesRestore");
            $tplDiff->addvar("rows", $arDiffs);
            $this->message = $tplDiff->process(true);
            return false;
        }
        foreach ($arFilesNew as $fileName => $fileNewMd5) {
            $filePath = dirname($ab_path.ltrim($fileName, '/'));
            if (!is_dir($filePath)) {
                mkdir($filePath, 0777, true);
            }
            if (!copy($dirTarget.$fileName, $ab_path.ltrim($fileName, '/'))) {
                $this->error = "Kopiervorgang fehlgeschlagen: ".$fileName;
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            } else {
                // Add log entry
                $this->addLogMessage("Datei kopiert: ".$fileName, 2);
            }
        }
        return true;
    }

    /**
     * @param $source       Source directory within update directory
     * @param $filename     Filename with leading slash
     * @return bool         true if the file was successfully removed from update. (Ignore single file update)
     */
    public function filesSkip($source, $filename) {
        global $ab_path;
        if (file_exists($ab_path."update/".$source.$filename)) {
            if (unlink($ab_path."update/".$source.$filename)) {
                // Add log entry
                $this->addLogMessage("Datei übersprungen: '".$filename."'", 2);
                return true;
            } else {
                $this->error = 'Fehler beim löschen der Datei: '."update/".$source.$filename;
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        } else {
            $this->error = 'Datei existiert nicht: '."update/".$source.$filename;
            // Add log entry
            $this->addLogMessage($this->error, 2);
            return false;
        }
    }

    /**
     * @param $source       Source directory within update directory
     * @param $filename     Filename with leading slash
     * @return bool         true if the file was successfully removed from update. (Ignore single file update)
     */
    public function filesReplace($source, $filename) {
        global $ab_path;
        $ab_file = $ab_path.ltrim($filename, "/");
        if (file_exists($ab_file)) {
            if (copy($ab_path."update/".$source.$filename, $ab_file)) {
                // Add log entry
                $this->addLogMessage("Datei überschrieben: '".$filename."'", 2);
                return true;
            } else {
                $this->error = 'Fehler beim kopieren der Datei: "update/'.$source.$filename.'" nach "'.$filename.'"';
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        } else {
            $this->error = 'Datei existiert nicht: '.$filename;
            // Add log entry
            $this->addLogMessage($this->error, 2);
            return false;
        }
    }

    /**
     * @param $filename
     * @return bool
     */
    public function filesUpdate($filename, $content, $pathUpdate = false, $pathBackup = false, $finish = false) {
        global $ab_path;
        if (file_exists($ab_path.$filename)) {
            $updateFile = $ab_path.$filename.".update";
            file_put_contents($updateFile, $content);
            if (($pathUpdate !== false) && $finish) {
                // Delete current file
                unlink($ab_path.$filename);
                // Rename new file
                rename($updateFile, $ab_path.$filename);
                // Delete update file
                unlink($ab_path.$pathUpdate.$filename);
            }
            return true;
        } else {
            $this->error = 'Datei existiert nicht: '.$filename;
            // Add log entry
            $this->addLogMessage($this->error, 2);
            return false;
        }
    }

    /**
     * @param $filename     Path to file (relative to root directory)
     * @param $pathUpdate   Path to update files (relative to root directory)
     * @param $pathBackup   Path to backup files (relative to root directory)
     */
    public function filesRestore($filename, $pathUpdate = false, $pathBackup = false) {
        global $ab_path;
        $arResult = array("success" => false);
        $fileBackup = $ab_path.$pathBackup.$filename;
        $fileCur = $ab_path.$filename;
        $fileUpdate = $ab_path.$pathUpdate.$filename;
        if (file_exists($fileCur) && file_exists($fileCur.".update") && file_exists($fileUpdate)) {
            // Remove changed file
            unlink($fileCur.".update");
            // Return result
            $arResult["success"] = true;
            $arResult["content"] = array(
                "center"    => file_get_contents($fileUpdate),
                "right"     => file_get_contents($fileCur)
            );
            if (file_exists($fileBackup)) {
                $arResult["content"]["left"] = file_get_contents($fileBackup);
            }
        }
        header("Content-Type: application/json");
        die(json_encode($arResult));
    }

    /**
     * @param $parameters   Array containing the settings for the navigation node
     * @return bool         true if the node was successfully created.
     */
    private function navAdd($parameters, $optional = false) {
        global $ab_path, $s_lang, $langval;
        // Add log entry
        $this->addLogMessage("Navigationspunkt wird hinzugefügt: '".$parameters["V1"]."' / '".$parameters["IDENT"]."' ...");
        $navRoot = (int)$parameters['ROOT'];
        $nest = new nestedsets('nav', $navRoot, true);
        // Check nested set
        if (!$nest->validate()) {
            $this->error = 'Navigationsstruktur ungültig! Bitte Backup wiederherstellen oder den Support kontaktieren.';
            // Add log entry
            $this->addLogMessage($this->error, 2);
            return false;
        }
        // Process parameters
        if (trim($parameters['ALIAS']) == "") {
            $_POST['ALIAS'] = NULL;
        }
        if(!preg_match('/[a-z0-9_-]*/', $_POST['IDENT'])) {
            $this->error = 'Ungültiger Bezeichner für Navigationspunkt.';
            // Add log entry
            $this->addLogMessage($this->error, 2);
            return false;
        }
        if (!empty($parameters['IDENT'])) {
            $checkDuplicate = $this->db->fetch_atom("SELECT count(*) FROM `nav` WHERE IDENT = '".mysql_real_escape_string($parameters['IDENT'])."' and ROOT=".$navRoot);
            if ($checkDuplicate > 0) {
                if ($optional) {
                    return true;
                }
                $this->error = 'Doppelter Bezeichner für Navigationspunkt.';
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        } else {
            $parameters['IDENT'] = "";
        }
        if (isset($parameters['INFOSEITE'])) {
            $infoName = $parameters['INFOSEITE'];
            $infoId = $this->db->fetch_atom("SELECT FK FROM `string_info` WHERE V1='".mysql_real_escape_string($infoName)."'");
            unset($parameters['INFOSEITE']);
            if ($infoId > 0) {
                $parameters['FK_INFOSEITE'] = $infoId;
            } else {
                $this->error = 'Infoseite nicht gefunden.';
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        }
        $arLanguages = array($s_lang);
        $arLanguagesBitvals = $this->db->fetch_nar("SELECT ABBR, BITVAL FROM `lang`");
        if (is_array($parameters['V1'])) {
            foreach ($parameters['V1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        if (is_array($parameters['V2'])) {
            foreach ($parameters['V2'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        if (is_array($parameters['T1'])) {
            foreach ($parameters['T1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        } else {
            $parameters['T1'][$s_lang] = @file_get_contents($ab_path."cache/meta_def_".$s_lang.".txt");
        }
        // Insert node
        $arTargetNode = false;
        foreach ($parameters['TARGET'] as $searchMode => $searchValue) {
            switch ($searchMode) {
                case 'BY_IDENT':
                    $arTargetNode = $this->db->fetch1("SELECT * FROM `nav` WHERE IDENT = '".mysql_real_escape_string($searchValue)."' and ROOT=".$navRoot);
                    break;
                case 'BY_LABEL':
                    $arTargetNode = $this->db->fetch1("SELECT n.* FROM `nav` n JOIN `string` s ON n.ID_NAV=s.FK
                        WHERE s.S_TABLE='nav' AND s.V1 = '".mysql_real_escape_string($searchValue)."' AND n.ROOT=".$navRoot);
                    break;
            }
        }
        if (!is_array($arTargetNode)) {
            $this->error = 'Elternelement nicht gefunden.';
            // Add log entry
            $this->addLogMessage($this->error, 2);
            return false;
        }
        switch ($parameters['POSITION']) {
            case 'appendChild':
                $parameters['ID_NAV'] = $nest->nestInsert($arTargetNode['ID_NAV']);
                break;
            case 'appendAfter':
                $parameters['ID_NAV'] = $nest->nestInsertAfter($arTargetNode['ID_NAV']);
                break;
        }
        if (!empty($parameters['INFOSEITE'])) {
            $id_infoseite = $this->db->fetch_atom("SELECT FK FROM `string_info` WHERE S_TABLE='infoseite' AND BF_LANG=".$langval." AND V1='".mysql_real_escape_string($parameters['INFOSEITE'])."'");
            if ($id_infoseite > 0) {
                $parameters['FK_INFOSEITE'] = $id_infoseite;
            }
        }
        $langvalOrg = $langval;
        $s_langOrg = $s_lang;
        foreach ($arLanguages as $langIndex => $language) {
            $s_lang = $language;
            $langval = $arLanguagesBitvals[$language];
            $arNav = $parameters;
            if (isset($parameters["V1"][$language])) {
                $arNav["V1"] = $parameters["V1"][$language];
            } else {
                unset($arNav["V1"]);
            }
            if (isset($parameters["V2"][$language])) {
                $arNav["V2"] = $parameters["V2"][$language];
            } else {
                unset($arNav["V2"]);
            }
            if (isset($parameters["T1"][$language])) {
                $arNav["T1"] = $parameters["T1"][$language];
            } else {
                unset($arNav["T1"]);
            }
            unset($arNav["ROLES"]);
            $result = $this->db->update('nav', $arNav);
            if (!$result) {
                $this->error = 'Datenbank-Fehler!';
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        }
        $langval = $langvalOrg;
        $s_lang = $s_langOrg;
        // Apply new role permissions
        if (is_array($parameters["ROLES"])) {
            $sRoleDir = ($navRoot == 2 ? 'admin/' : '');
            $ident = $sRoleDir.$parameters["IDENT"];
            $arRoles = $this->db->fetch_table("select ID_ROLE, LABEL from role order by ID_ROLE");
            $arRoleDeny = array_keys(
                $this->db->fetch_nar("SELECT r.LABEL FROM pageperm2role pr JOIN role r ON r.ID_ROLE=pr.FK_ROLE WHERE pr.IDENT='".mysql_real_escape_string($ident)."'")
            );
            foreach ($arRoles as $index => $arRole) {
                $roleAllowed = in_array($arRole["LABEL"], $parameters["ROLES"]);
                $roleAllowedSaved = !in_array($arRole["LABEL"], $arRoleDeny);
                if ($roleAllowed != $roleAllowedSaved) {
                    if ($roleAllowed) {
                        $this->db->querynow($q = "DELETE FROM pageperm2role
                            WHERE FK_ROLE=".$arRole["ID_ROLE"]." AND IDENT='".mysql_real_escape_string($ident)."'");
                    } else {
                        $this->db->querynow($q = "INSERT INTO pageperm2role (FK_ROLE, IDENT)
                            VALUES (".$arRole["ID_ROLE"].", '".mysql_real_escape_string($ident)."')");
                    }
                }
                $arRoles[$index]["ALLOWED"] = (in_array($arRole["LABEL"], $arRoleDeny) ? 0 : 1);
            }
        }
        pageperm2role_rewrite();
        cache_nav_all($navRoot);
        $_SESSION['navedit'.$navRoot.$s_lang] = "";
        // Add log entry
        $this->addLogMessage("Navigationspunkt hinzugefügt: '".$parameters["V1"]."' / '".$parameters["IDENT"]."'");
        return true;
    }

    /**
     * @param $parameters   Array containing the settings for the navigation node
     * @return bool         true if the node was successfully updated.
     */
    private function navEdit($parameters) {
        global $ab_path, $s_lang, $langval;
        // Add log entry
        $this->addLogMessage("Navigationspunkt wird bearbeitet: '".$parameters["V1"]."' / '".$parameters["IDENT"]."' ...");
        $navRoot = (int)$parameters['ROOT'];
        $nest = new nestedsets('nav', $navRoot, true);
        // Check nested set
        if (!$nest->validate()) {
            $this->error = 'Navigationsstruktur ungültig! Bitte Backup wiederherstellen oder den Support kontaktieren.';
            return false;
        }
        // Process parameters
        if (trim($parameters['ALIAS']) == "") {
            $_POST['ALIAS'] = NULL;
        }
        if(!preg_match('/[a-z0-9_-]*/', $parameters['IDENT'])) {
            $this->error = 'Ungültiger Bezeichner für Navigationspunkt.';
            // Add log entry
            $this->addLogMessage($this->error, 2);
            return false;
        }
        $id = $this->db->fetch_atom("SELECT ID_NAV FROM `nav` WHERE IDENT = '".mysql_real_escape_string($parameters['IDENT'])."' and ROOT=".$navRoot);
        if (!$id) {
            $this->error = 'Navigationspunkt nicht gefunden.';
            // Add log entry
            $this->addLogMessage($this->error, 2);
            return false;
        }
        if (isset($parameters['INFOSEITE'])) {
            $infoName = $parameters['INFOSEITE'];
            $infoId = $this->db->fetch_atom("SELECT FK FROM `string_info` WHERE V1='".mysql_real_escape_string($infoName)."'");
            unset($parameters['INFOSEITE']);
            if ($infoId > 0) {
                $parameters['FK_INFOSEITE'] = $infoId;
            } else {
                $this->error = 'Infoseite nicht gefunden.';
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        }
        $arLanguages = array($s_lang);
        $arLanguagesBitvals = $this->db->fetch_nar("SELECT ABBR, BITVAL FROM `lang`");
        if (is_array($parameters['V1'])) {
            foreach ($parameters['V1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        if (is_array($parameters['V2'])) {
            foreach ($parameters['V2'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        if (is_array($parameters['T1'])) {
            foreach ($parameters['T1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        } else {
            $parameters['T1'][$s_lang] = @file_get_contents($ab_path."cache/meta_def_".$s_lang.".txt");
        }
        // Move node
        if (isset($parameters['TARGET'])) {
            $arTargetNode = false;
            foreach ($parameters['TARGET'] as $searchMode => $searchValue) {
                switch ($searchMode) {
                    case 'BY_IDENT':
                        $arTargetNode = $this->db->fetch1("SELECT * FROM `nav` WHERE IDENT = '".mysql_real_escape_string($searchValue)."' and ROOT=".$navRoot);
                        break;
                    case 'BY_LABEL':
                        $arTargetNode = $this->db->fetch1("SELECT n.* FROM `nav` n JOIN `string` s ON n.ID_NAV=s.FK
                        WHERE s.S_TABLE='nav' AND s.V1 = '".mysql_real_escape_string($searchValue)."' AND n.ROOT=".$navRoot);
                        break;
                }
            }
            if (!is_array($arTargetNode)) {
                $this->error = 'Elternelement nicht gefunden.';
                return false;
            }
            switch ($parameters['POSITION']) {
                case 'appendChild':
                    $nest->nestMoveInto($id, $arTargetNode['ID_NAV']);
                    break;
                case 'appendAfter':
                    $nest->nestMoveAfter($id, $arTargetNode['ID_NAV']);
                    break;
            }
        }
        $langvalOrg = $langval;
        $s_langOrg = $s_lang;
        foreach ($arLanguages as $langIndex => $language) {
            $s_lang = $language;
            $langval = $arLanguagesBitvals[$language];
            $arString = $this->db->fetch1("SELECT * FROM `string` WHERE S_TABLE='nav' AND FK=".$id." AND BF_LANG=".$langval);
            $arNav = $parameters;
            $arNav["ID_NAV"] = $id;
            if (isset($parameters["V1"][$language])) {
                $arNav["V1"] = $parameters["V1"][$language];
            } else {
                $arNav["V1"] = $arString["V1"];
            }
            if (isset($parameters["V2"][$language])) {
                $arNav["V2"] = $parameters["V2"][$language];
            } else {
                $arNav["V2"] = $arString["V2"];
            }
            if (isset($parameters["T1"][$language])) {
                $arNav["T1"] = $parameters["T1"][$language];
            } else {
                $arNav["T1"] = $arString["T1"];
            }
            unset($arNav["ROLES"]);
            $result = $this->db->update('nav', $arNav);
            if (!$result) {
                $this->error = 'Datenbank-Fehler!';
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        }
        $langval = $langvalOrg;
        $s_lang = $s_langOrg;
        // Apply new role permissions
        if (is_array($parameters["ROLES"])) {
            $sRoleDir = ($navRoot == 2 ? 'admin/' : '');
            $ident = $sRoleDir.$parameters["IDENT"];
            $arRoles = $this->db->fetch_table("select ID_ROLE, LABEL from role order by ID_ROLE");
            $arRoleDeny = array_keys(
                $this->db->fetch_nar("SELECT r.LABEL FROM pageperm2role pr JOIN role r ON r.ID_ROLE=pr.FK_ROLE WHERE pr.IDENT='".mysql_real_escape_string($ident)."'")
            );
            foreach ($arRoles as $index => $arRole) {
                $roleAllowed = in_array($arRole["LABEL"], $parameters["ROLES"]);
                $roleAllowedSaved = !in_array($arRole["LABEL"], $arRoleDeny);
                if ($roleAllowed != $roleAllowedSaved) {
                    if ($roleAllowed) {
                        $this->db->querynow($q = "DELETE FROM pageperm2role
                            WHERE FK_ROLE=".$arRole["ID_ROLE"]." AND IDENT='".mysql_real_escape_string($ident)."'");
                    } else {
                        $this->db->querynow($q = "INSERT INTO pageperm2role (FK_ROLE, IDENT)
                            VALUES (".$arRole["ID_ROLE"].", '".mysql_real_escape_string($ident)."')");
                    }
                }
                $arRoles[$index]["ALLOWED"] = (in_array($arRole["LABEL"], $arRoleDeny) ? 0 : 1);
            }
        }
        pageperm2role_rewrite();
        cache_nav_all($navRoot);
        $_SESSION['navedit'.$navRoot.$s_lang] = "";
        // Add log entry
        $this->addLogMessage("Navigationspunkt verändert: '".$parameters["V1"]."' / '".$parameters["IDENT"]."'");
        return true;
    }

    /**
     * @param $parameters   Array containing the settings for the navigation node
     * @return bool         true if the node was successfully deleted.
     */
    private function navDel($parameters) {
        global $ab_path, $s_lang, $langval;
        $navRoot = (int)$parameters['ROOT'];
        $nest = new nestedsets('nav', $navRoot, true);
        // Check nested set
        if (!$nest->validate()) {
            $this->error = 'Navigationsstruktur ungültig! Bitte Backup wiederherstellen oder den Support kontaktieren.';
            return false;
        }
        // Get target node
        if (isset($parameters['TARGET'])) {
            $arTargetNode = false;
            foreach ($parameters['TARGET'] as $searchMode => $searchValue) {
                switch ($searchMode) {
                    case 'BY_IDENT':
                        $arTargetNode = $this->db->fetch1("SELECT * FROM `nav` WHERE IDENT = '".mysql_real_escape_string($searchValue)."' and ROOT=".$navRoot);
                        break;
                    case 'BY_LABEL':
                        $arTargetNode = $this->db->fetch1("SELECT n.* FROM `nav` n JOIN `string` s ON n.ID_NAV=s.FK
                        WHERE s.S_TABLE='nav' AND s.V1 = '".mysql_real_escape_string($searchValue)."' AND n.ROOT=".$navRoot);
                        break;
                }
            }
            if (!is_array($arTargetNode)) {
                $this->error = 'Elternelement nicht gefunden.';
                return false;
            }
        }
        // Add log entry
        $this->addLogMessage("Navigationspunkt wird gelöscht: '".$arTargetNode["IDENT"]."' ...");
        // Delete entry
        $nest->nestDel($arTargetNode["ID_NAV"]);
        // Recache nav
        pageperm2role_rewrite();
        cache_nav_all($navRoot);
        $_SESSION['navedit'.$navRoot.$s_lang] = "";
        // Add log entry
        $this->addLogMessage("Navigationspunkt gelöscht: '".$arTargetNode["IDENT"]."'");
        return true;
    }

    /**
     * @param $parameters   Array containing the settings for the message
     * @return bool         true if the message was successfully added.
     */
    private function messageAdd($parameters) {
        global $ab_path, $s_lang, $langval;
        // Add log entry
        $this->addLogMessage("Meldung wird hinzugefügt: '".$parameters["V1"]."' / '".$parameters["IDENT"]."' ...");
        // Check valid
        $err = array();
        if(empty($parameters['FKT'])) {
            $this->error = "Bitte eine Funktion angeben";
            return false;
        }
        if(empty($parameters['V1'])) {
            $this->error = "Bitte geben Sie einen Text ein!";
            return false;
        }
        $arLanguages = array($s_lang);
        $arLanguagesBitvals = $this->db->fetch_nar("SELECT ABBR, BITVAL FROM `lang`");
        if (is_array($parameters['V1'])) {
            foreach ($parameters['V1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        if (is_array($parameters['V2'])) {
            foreach ($parameters['V2'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        if (is_array($parameters['T1'])) {
            foreach ($parameters['T1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        } else {
            $parameters['T1'][$s_lang] = @file_get_contents($ab_path."cache/meta_def_".$s_lang.".txt");
        }
        $langvalOrg = $langval;
        $s_langOrg = $s_lang;
        foreach ($arLanguages as $langIndex => $language) {
            $s_lang = $language;
            $langval = $arLanguagesBitvals[$language];
            $arMessage = $parameters;
            if (isset($parameters["V1"][$language])) {
                $arMessage["V1"] = $parameters["V1"][$language];
            } else {
                unset($arMessage["V1"]);
            }
            if (isset($parameters["V2"][$language])) {
                $arMessage["V2"] = $parameters["V2"][$language];
            } else {
                unset($arMessage["V2"]);
            }
            if (isset($parameters["T1"][$language])) {
                $arMessage["T1"] = $parameters["T1"][$language];
            } else {
                unset($arMessage["T1"]);
            }
            unset($arMessage["ROLES"]);
            $result = $this->db->update("message", $arMessage);
            if (!$result) {
                $this->error = 'Datenbank-Fehler!';
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        }
        $langval = $langvalOrg;
        $s_lang = $s_langOrg;
        return true;
    }

    /**
     * @param $parameters   Array containing the settings for the lookup
     * @return bool         true if the lookup was successfully added.
     */
    private function lookupAdd($parameters) {
        global $ab_path, $s_lang, $langval;
        // Add log entry
        $this->addLogMessage("Lookup wird hinzugefügt: '".$parameters["V1"]."' / '".$parameters["art"]."' / '".$parameters["VALUE"]."' ...");
        // Check valid
        $err = array();
        if(empty($parameters['art'])) {
            $this->error = "Bitte eine Art angeben";
            return false;
        }
        if(empty($parameters['VALUE'])) {
            $this->error = "Bitte einen Wert angeben";
            return false;
        }
        if(empty($parameters['V1'])) {
            $this->error = "Bitte geben Sie einen Text ein!";
            return false;
        }
        $arLanguages = array($s_lang);
        $arLanguagesBitvals = $this->db->fetch_nar("SELECT ABBR, BITVAL FROM `lang`");
        if (is_array($parameters['V1'])) {
            foreach ($parameters['V1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        $langvalOrg = $langval;
        $s_langOrg = $s_lang;
        foreach ($arLanguages as $langIndex => $language) {
            $s_lang = $language;
            $langval = $arLanguagesBitvals[$language];
            $arLookup = $parameters;
            if (isset($parameters["V1"][$language])) {
                $arLookup["V1"] = $parameters["V1"][$language];
            } else {
                unset($arLookup["V1"]);
            }
            $result = $this->db->update("lookup", $arLookup);
            if (!$result) {
                $this->error = 'Datenbank-Fehler!';
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        }
        $langval = $langvalOrg;
        $s_lang = $s_langOrg;
        return true;
    }

    /**
     * @param $parameters   Array containing the settings for the message
     * @return bool         true if the message was successfully added.
     */
    private function messageEdit($parameters) {
        global $ab_path, $s_lang, $langval;
        // Add log entry
        $this->addLogMessage("Meldung wird aktualisiert: '".$parameters["IDENT"]."' ...");
        // Check valid
        if(empty($parameters['FKT'])) {
            $this->error = "Bitte eine Funktion angeben ('FKT')";
            return false;
        }
        if(empty($parameters['ERR'])) {
            $this->error = "Bitte einen Ident angeben ('ERR')";
            return false;
        }
        $arLanguages = array($s_lang);
        $arLanguagesBitvals = $this->db->fetch_nar("SELECT ABBR, BITVAL FROM `lang`");
        if (is_array($parameters['V1'])) {
            foreach ($parameters['V1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        if (is_array($parameters['V2'])) {
            foreach ($parameters['V2'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        if (is_array($parameters['T1'])) {
            foreach ($parameters['T1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        } else {
            $parameters['T1'][$s_lang] = @file_get_contents($ab_path."cache/meta_def_".$s_lang.".txt");
        }
        $langvalOrg = $langval;
        $s_langOrg = $s_lang;
        foreach ($arLanguages as $langIndex => $language) {
            $s_lang = $language;
            $langval = $arLanguagesBitvals[$language];
            $query = "
                SELECT t.ID_MESSAGE,s.V1
                FROM `message` t
                LEFT JOIN string_app s ON s.S_TABLE='message' and s.FK=t.ID_MESSAGE and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
                WHERE t.FKT='".mysql_real_escape_string($parameters['FKT'])."' AND t.ERR='".mysql_real_escape_string($parameters['ERR'])."' 
                ";
            $arMessage = $this->db->fetch1($query);
            if (!is_array($arMessage)) {
                $this->error = "Es wurde keine Meldung mit diesem Ident gefunden! (".$parameters['FKT']." / ".$parameters['ERR'].")";
                return false;
            }
            $arMessage = array_merge($arMessage, $parameters);
            if (isset($parameters["V1"][$language])) {
                $arMessage["V1"] = $parameters["V1"][$language];
            } else {
                unset($arMessage["V1"]);
            }
            if (isset($parameters["V2"][$language])) {
                $arMessage["V2"] = $parameters["V2"][$language];
            } else {
                unset($arMessage["V2"]);
            }
            if (isset($parameters["T1"][$language])) {
                $arMessage["T1"] = $parameters["T1"][$language];
            } else {
                unset($arMessage["T1"]);
            }
            unset($arMessage["ROLES"]);
            $result = $this->db->update("message", $arMessage);
            if (!$result) {
                $this->error = 'Datenbank-Fehler!';
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        }
        $langval = $langvalOrg;
        $s_lang = $s_langOrg;
        return true;
    }

    /**
     * @param $parameters   Array containing the settings for the navigation node
     * @return bool         true if the node was successfully updated.
     */
    private function infoAdd($parameters) {
        global $ab_path, $s_lang, $langval;
        // Add log entry
        $this->addLogMessage("Infobereich wird hinzugefügt: '".$parameters["SYS_NAME"]."' / '".$parameters["BESCHREIBUNG"]."' ...");
        // TODO: Prüfen ob Infobereich bereits existiert
        $id = false;
        $arLanguages = array($s_lang);
        $arLanguagesBitvals = $this->db->fetch_nar("SELECT ABBR, BITVAL FROM `lang`");
        if (is_array($parameters['V1'])) {
            foreach ($parameters['V1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        if (is_array($parameters['V2'])) {
            foreach ($parameters['V2'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        if (is_array($parameters['T1'])) {
            foreach ($parameters['T1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        // Aktuelle Sprache sichern
        $langvalOrg = $langval;
        $s_langOrg = $s_lang;
        foreach ($arLanguages as $langIndex => $language) {
            $s_lang = $language;
            $langval = $arLanguagesBitvals[$language];
            $arInfoseite = $parameters;
            $arInfoseite["ID_INFOSEITE"] = $id;
            if (isset($parameters["V1"][$language])) {
                $arInfoseite["V1"] = $parameters["V1"][$language];
            } else {
                $arInfoseite["V1"] = "";
            }
            if (isset($parameters["V2"][$language])) {
                $arInfoseite["V2"] = $parameters["V2"][$language];
            } else {
                $arInfoseite["V2"] = "";
            }
            if (isset($parameters["T1"][$language])) {
                $arInfoseite["T1"] = $parameters["T1"][$language];
            } else {
                $arInfoseite["T1"] = "";
            }
            // Datenbank-Eintrag erstellen
            $result = $this->db->update('infoseite', $arInfoseite);
            if (!$result) {
                $this->error = 'Datenbank-Fehler!';
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            } else if ($result > 0) {
                $id = (int)$result;
            }
        }
        $langval = $langvalOrg;
        $s_lang = $s_langOrg;
        // Add log entry
        $this->addLogMessage("Infobereich hinzugefügt: '".$parameters["SYS_NAME"]."' / '".$parameters["V2"]."'");
        return true;
    }

    /**
     * @param $parameters   Array containing the settings for the navigation node
     * @return bool         true if the node was successfully updated.
     */
    private function mailAdd($parameters) {
        global $ab_path, $s_lang, $langval;
        // Add log entry
        $this->addLogMessage("E-Mail-Template wird hinzugefügt: '".$parameters["SYS_NAME"]."' / '".$parameters["BESCHREIBUNG"]."' ...");
        $id = $this->db->fetch_atom("SELECT ID_MAILVORLAGE FROM `mailvorlage` WHERE SYS_NAME = '".mysql_real_escape_string($parameters['SYS_NAME'])."'");
        if ($id > 0) {
            $this->error = 'Mailvorlage existiert bereits!';
            // Add log entry
            $this->addLogMessage($this->error, 2);
            return false;
        }
        $arLanguages = array($s_lang);
        $arLanguagesBitvals = $this->db->fetch_nar("SELECT ABBR, BITVAL FROM `lang`");
        if (is_array($parameters['V1'])) {
            foreach ($parameters['V1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        if (is_array($parameters['V2'])) {
            foreach ($parameters['V2'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        if (is_array($parameters['T1'])) {
            foreach ($parameters['T1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        // Aktuelle Sprache sichern
        $langvalOrg = $langval;
        $s_langOrg = $s_lang;
        foreach ($arLanguages as $langIndex => $language) {
            $s_lang = $language;
            $langval = $arLanguagesBitvals[$language];
            $arMailvorlage = $parameters;
            $arMailvorlage["ID_MAILVORLAGE"] = $id;
            if (isset($parameters["V1"][$language])) {
                $arMailvorlage["V1"] = $parameters["V1"][$language];
            } else {
                $arMailvorlage["V1"] = "";
            }
            if (isset($parameters["V2"][$language])) {
                $arMailvorlage["V2"] = $parameters["V2"][$language];
            } else {
                $arMailvorlage["V2"] = "";
            }
            if (isset($parameters["T1"][$language])) {
                $arMailvorlage["T1"] = $parameters["T1"][$language];
            } else {
                $arMailvorlage["T1"] = "";
            }
            // Datenbank-Eintrag erstellen
            $result = $this->db->update('mailvorlage', $arMailvorlage);
            if (!$result) {
                $this->error = 'Datenbank-Fehler!';
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            } else if ($result > 0) {
                $id = (int)$result;
            }
        }
        $langval = $langvalOrg;
        $s_lang = $s_langOrg;
        // Add log entry
        $this->addLogMessage("E-Mail-Template hinzugefügt: '".$parameters["SYS_NAME"]."' / '".$parameters["V2"]."'");
        return true;
    }

    /**
     * @param $parameters   Array containing the settings for the navigation node
     * @return bool         true if the node was successfully updated.
     */
    private function mailEdit($parameters) {
        global $ab_path, $s_lang, $langval;
        // Add log entry
        $this->addLogMessage("E-Mail-Template wird aktualisiert: '".$parameters["SYS_NAME"]."' / '".$parameters["BESCHREIBUNG"]."' ...");
        $id = $this->db->fetch_atom("SELECT ID_MAILVORLAGE FROM `mailvorlage` WHERE SYS_NAME = '".mysql_real_escape_string($parameters['SYS_NAME'])."'");
        if (!$id) {
            $this->error = 'Mailvorlage nicht gefunden.';
            // Add log entry
            $this->addLogMessage($this->error, 2);
            return false;
        }
        $arLanguages = array($s_lang);
        $arLanguagesBitvals = $this->db->fetch_nar("SELECT ABBR, BITVAL FROM `lang`");
        if (is_array($parameters['V1'])) {
            foreach ($parameters['V1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        if (is_array($parameters['V2'])) {
            foreach ($parameters['V2'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        if (is_array($parameters['T1'])) {
            foreach ($parameters['T1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        // Backup der ursprünglichen E-Mail Templates laden
        $arMailBackup = Yaml::parse( file_get_contents($ab_path."update/".$parameters["BACKUP"]) );
        // Aktuelle Sprache sichern
        $langvalOrg = $langval;
        $s_langOrg = $s_lang;
        foreach ($arLanguages as $langIndex => $language) {
            $s_lang = $language;
            $langval = $arLanguagesBitvals[$language];
            $arString = $this->db->fetch1("SELECT * FROM `string_mail` WHERE S_TABLE='mailvorlage' AND FK=".$id." AND BF_LANG=".$langval);
            // Alte eingaben in Log-Datei sichern
            $this->addLogMessage("Ursprünglicher Mail-Betreff:", 2);
            $this->addLogMessage($arString["V1"], 4);
            $this->addLogMessage("Ursprünglicher E-Mailtext:", 2);
            $this->addLogMessage($arString["T1"], 4);
            $arMailvorlage = $parameters;
            $arMailvorlage["ID_MAILVORLAGE"] = $id;
            if (isset($parameters["V1"][$language])) {
                $arMailvorlage["V1"] = $parameters["V1"][$language];
            } else {
                $arMailvorlage["V1"] = $arString["V1"];
            }
            if (isset($parameters["V2"][$language])) {
                $arMailvorlage["V2"] = $parameters["V2"][$language];
            } else {
                $arMailvorlage["V2"] = $arString["V2"];
            }
            if (isset($parameters["T1"][$language])) {
                $arMailvorlage["T1"] = $parameters["T1"][$language];
            } else {
                $arMailvorlage["T1"] = $arString["T1"];
            }
            // Prüfen ob das E-Mail Template verändert wurde
            $backupTitleTrim = trim(str_replace("\r\n", "\n", $arMailBackup["V1"][$language]));
            $backupContentTrim = trim(str_replace("\r\n", "\n", $arMailBackup["T1"][$language]));
            $newTitleTrim = trim(str_replace("\r\n", "\n", $arMailvorlage["V1"]));
            $newContentTrim = trim(str_replace("\r\n", "\n", $arMailvorlage["T1"]));
            $userTitleTrim = trim(str_replace("\r\n", "\n", $arString["V1"]));
            $userContentTrim = trim(str_replace("\r\n", "\n", $arString["T1"]));
            if (($backupTitleTrim != $userTitleTrim) || ($backupContentTrim != $userContentTrim)) {
                $btnSkip = '<a class=\'btn updateSkip\' onclick=\'UpdateSkipStep()\'>Update-Änderungen ignorieren</a>';
                $this->error = "Unerwartete Änderung an E-Mail Template '".$parameters['SYS_NAME']."' (".$language.")<br />\n".
                    "<ul>\n".
                    "<li>".$btnSkip."</li>\n".
                    "</ul>\n".
                    "<div style='color:black;'>".
                    "<p>Neuer Betreff: <span style='font-weight: normal;'>".htmlspecialchars($newTitleTrim)."</span></p>".
                    "<p style='font-weight: bold;'>Neuer Inhalt:</p><pre style='font-weight: normal;'>".htmlspecialchars($newContentTrim)."</pre>".
                    "</div>".
                    "<script type='application/javascript'>showHelp('help-merge-mails');</script>";
                return false;
            }
            $result = $this->db->update('mailvorlage', $arMailvorlage);
            if (!$result) {
                $this->error = 'Datenbank-Fehler!';
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        }
        $langval = $langvalOrg;
        $s_lang = $s_langOrg;
        // Add log entry
        $this->addLogMessage("E-Mail-Template aktualisiert: '".$parameters["SYS_NAME"]."' / '".$parameters["V2"]."'");
        return true;
    }

    private function manualStep($parameters) {
        $this->message = "
            <div class='error'>
              <h3>Manuelle Änderung notwendig!</h3>
              ".$parameters['description']."
              <br />
              <button onclick='UpdateSkipStep();'>Ich habe diesen Schritt erledigt</button>
            </div>";
        return false;
    }

    /**
     * @param $parameters   Array containing the settings for the navigation node
     * @return bool         true if SQL-Script was successfully executed
     */
    private function sqlRun($parameters) {
        // Add log entry
        $this->addLogMessage("SQL-Anweisungen werden ausgeführt: 'update/".$parameters['file']."'");
        $sqlQueries = array();
        if (isset($parameters['file'])) {
            global $ab_path;
            $filename = $ab_path."update/".$parameters['file'];
            if (file_exists($filename)) {
                $sqlQueries = $this->sqlRead($filename);
            } else {
                $this->error = 'File not found: '.$filename;
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        }
        foreach ($sqlQueries as $queryIndex => $queryCurrent) {
            $result = $this->db->querynow($queryCurrent);
            if ($result["rsrc"] === false) {
                $this->error = $result["str_error"];
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        }
        // Add log entry
        $this->addLogMessage("SQL-Anweisungen ausgeführt: 'update/".$parameters['file']."'");
        return true;
    }
    
    private function stringAdd($parameters) {
        global $ab_path, $s_lang, $langval;
        // Add log entry
        $this->addLogMessage("Datenbank-String wird hinzugefügt: '".$parameters["TABLE"]."' / '".$parameters["V1"]."' ...");
        $table = $parameters["TABLE"];
        $idField = "ID_".strtoupper($table);
        if (array_key_exists($idField, $parameters)) {
            $id = $this->db->fetch_atom("
              SELECT `".mysql_real_escape_string($idField)."` FROM `".mysql_real_escape_string($table)."` 
              WHERE `".mysql_real_escape_string($idField)."` = '".mysql_real_escape_string($parameters[$idField])."'");
            if ($id > 0) {
                $this->error = 'Datenbank-String existiert bereits!';
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        }
        $arLanguages = array($s_lang);
        $arLanguagesBitvals = $this->db->fetch_nar("SELECT ABBR, BITVAL FROM `lang`");
        if (is_array($parameters['V1'])) {
            foreach ($parameters['V1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        if (is_array($parameters['V2'])) {
            foreach ($parameters['V2'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        if (is_array($parameters['T1'])) {
            foreach ($parameters['T1'] as $language => $content) {
                if (!in_array($language, $arLanguages)) {
                    $arLanguages[] = $language;
                }
            }
        }
        // Aktuelle Sprache sichern
        $langvalOrg = $langval;
        $s_langOrg = $s_lang;
        foreach ($arLanguages as $langIndex => $language) {
            $s_lang = $language;
            $langval = $arLanguagesBitvals[$language];
            $arString = $parameters;
            if (isset($parameters["V1"][$language])) {
                $arString["V1"] = $parameters["V1"][$language];
            } else {
                $arString["V1"] = "";
            }
            if (isset($parameters["V2"][$language])) {
                $arString["V2"] = $parameters["V2"][$language];
            } else {
                $arString["V2"] = "";
            }
            if (isset($parameters["T1"][$language])) {
                $arString["T1"] = $parameters["T1"][$language];
            } else {
                $arString["T1"] = "";
            }
            // Datenbank-Eintrag erstellen
            $id = $this->db->update($table, $arString);
            if (!$id) {
                $this->error = 'Datenbank-Fehler!';
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
            $parameters[$idField] = $id;
        }
        $langval = $langvalOrg;
        $s_lang = $s_langOrg;
        // Add log entry
        $this->addLogMessage("Datenbank-String hinzugefügt: '".$parameters["SYS_NAME"]."' / '".$parameters["V2"]."'");
        return true;
    }

    /**
     * @param string $filename      File to read the SQL-queries from
     * @param string $delimiter     Delimiter for the sql queries
     * @return array                Array containing single sql queries
     */
    private function sqlRead($filename, $delimiter = ';') {
        $arQueries = array();
        if (is_file($filename)) {
            $filename = fopen($filename, 'r');
            if (is_resource($filename)) {
                $query = array();
                while (feof($filename) === false) {
                    $query[] = fgets($filename);
                    if (preg_match('~' . preg_quote($delimiter, '~') . '\s*$~iS', end($query)) === 1) {
                        $query = trim(implode('', $query));
                        $arQueries[] = $query;
                    }

                    if (is_string($query)) {
                        $query = array();
                    }
                }
                fclose($filename);
            }
        }
        return $arQueries;
    }

    /**
     * @param $parameters   Array containing the settings for the navigation node
     * @return bool         true if code could be executed
     */
    private function execCode($parameters) {

        if (isset($parameters['file'])) {
            global $ab_path;
            $filename = $ab_path."update/".$parameters['file'];
            if (file_exists($filename)) {
                include $filename;
            } else {
                $this->error = 'File not found: '.$filename;
                // Add log entry
                $this->addLogMessage($this->error, 2);
                return false;
            }
        }

        return true;
    }

    /**
     * Skip the current step
     */
    public function stepSkip() {
        file_put_contents($this->file.".progress", ++$this->position);
        return true;
    }

    private function getFilesMD5($dir, $skip = array(), $subDir = '', &$result = array()) {
        $dirFull = $dir.(!empty($subDir) ? '/'.$subDir : '');
        $contents = scandir($dirFull);
        // Remove . and ..
        array_shift($contents);
        array_shift($contents);
        // Check files and subdirectories
        foreach ($contents as $index => $filename) {
            $filenameShort = $subDir.'/'.$filename;
            if (!in_array($filenameShort, $skip)) {
                $filenameFull = $dirFull.'/'.$filename;
                if (is_dir($filenameFull)) {
                    $this->getFilesMD5($dir, $skip, $subDir.'/'.$filename, $result);
                } else {
                    $result[$filenameShort] = md5_file($filenameFull);
                }
            }
        }
        return $result;
    }

    /**
     * @return string  Get the next instruction.
     */
    public function getCurrentInstruction() {
        $arResult = $this->instructions[$this->position];
        $arResult["index"] = $this->position;
        return $arResult;
    }

    public function getCount() {
        return count($this->instructions);
    }

    /**
     * @return bool|string  Error message or false if no error occured.
     */
    public function getLastError() {
        return $this->error;
    }

    /**
     * @return bool|string  Information message or false if no message was stored.
     */
    public function getLastMessage() {
        return $this->message;
    }

    /**
     * Run the update instructions
     * @return bool     True if the update is complete, false if an error or timeout occured.
     */
    public function run() {
        $positionEnd = (count($this->instructions) - 1);
        $timeStart = time();
        do {
            if ($this->position > $positionEnd) {
                if (file_exists($this->file.".progress")) {
                    unlink($this->file.".progress");
                }
                return true;
            }
            if (!$this->runInstruction()) {
                file_put_contents($this->file.".progress", $this->position);
                return false;
            }
            file_put_contents($this->file.".progress", ++$this->position);
        } while ((time() - $timeStart) < self::$maxExecutionTime);
        return false;
    }

    /**
     * Execute next instruction
     */
    public function runInstruction() {
        $instruction = $this->instructions[$this->position];
        // Run instruction
        $instructionOptional = (array_key_exists("optional", $instruction) ? (bool)$instruction["optional"] : false);
        switch ($instruction["action"]) {
            case 'articleFieldAdd':
                if (!$this->articleFieldAdd($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'articleFieldRem':
                if (!$this->articleFieldRem($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'navAdd':
                if (!$this->navAdd($instruction["parameters"], $instructionOptional)) {
                    return false;
                }
                break;
            case 'navEdit':
                if (!$this->navEdit($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'navDel':
                if (!$this->navDel($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'cacheClear':
                if (!$this->cacheClear($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'designCheck':
                if (!$this->designCheck($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'lookupAdd':
                if (!$this->lookupAdd($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'messageAdd':
                if (!$this->messageAdd($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'messageEdit':
                if (!$this->messageEdit($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'infoAdd':
                if (!$this->infoAdd($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'mailAdd':
                if (!$this->mailAdd($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'mailEdit':
                if (!$this->mailEdit($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'stringAdd':
                if (!$this->stringAdd($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'manualStep':
                if (!$this->manualStep($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'sqlRun':
                if (!$this->sqlRun($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'filesCopy':
                if (!$this->filesCopy($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'execCode':
                if (!$this->execCode($instruction["parameters"])) {
                    return false;
                }
                break;
            case 'systemCheckPhp':
                $checkPhpVersion = (version_compare(PHP_VERSION, $instruction["parameters"]["version"]) >= 0);
                if (!$checkPhpVersion) {
                    $this->error = 'PHP-Version ungenügend! Dieses Update erfordert PHP Version '.$instruction["parameters"]["version"];
                    return false;
                }
                break;
            default:
                return false;
        }
        // Return success
        return true;
    }

} 
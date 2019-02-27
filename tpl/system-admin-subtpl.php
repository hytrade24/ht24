<?php
/* ###VERSIONSBLOCKINLCUDE### */

switch ($ar_params[2]) {
    case 'config':
        // Update subtpl configuration for a template
        $success = false;
        $templateCache = $ab_path.ltrim($_POST["PARENT"], "/");
        $subtplName = $_POST["NAME"];
        $subtplIndex = $_POST["INDEX"];
        $templateSource = CacheTemplate::getSourceFile($templateCache);
        if ($templateSource == $templateCache) {
            $templateRelative = str_replace($ab_path, "", $templateSource);
            if (preg_match("/^cache\/info\/([^\.]+)\.([0-9]+)\.htm$/", $templateRelative, $arMatches)) {
                // Infobereich / Info area
                $infoLang = $arMatches[1];
                $infoId = (int)$arMatches[2];
                if (array_key_exists($infoLang, $lang_list)) {
                    $infoLangVal = (int)$lang_list[$infoLang]["BITVAL"];
                    $arInfo = $db->fetch1("
                        SELECT s.*
                        FROM `infoseite` t
                        LEFT JOIN `string_info` s
                            ON s.S_TABLE='infoseite' AND s.FK=t.ID_INFOSEITE
                            AND s.BF_LANG=if(t.BF_LANG_INFO & ".$infoLangVal.", ".$infoLangVal.", 1 << floor(log(t.BF_LANG_INFO+0.5)/log(2)))
                        WHERE ID_INFOSEITE=".$infoId);
                    $success = Template_Modules_SubTemplates::updateSubTpl($arInfo["T1"], $subtplName, $subtplIndex, $_POST["OPTIONS"], $_POST["FALLBACK"]);
                    if ($success) {
                        $db->querynow("
                            UPDATE `string_info`
                            SET T1='".mysql_real_escape_string($arInfo["T1"])."'
                            WHERE S_TABLE='infoseite' AND FK=".$infoId." AND BF_LANG=".$arInfo["BF_LANG"]);
                        file_put_contents($templateSource, $arInfo["T1"]);
                    }
                } else {
                    die("Unknown language: ".$infoLang);
                }
            } else {
                die("Not resolvable: ".$templateRelative);
            }
        } else if (file_exists($templateSource)) {
            $templateRelativeDesignTemp = explode("/", str_replace($ab_path, "", $templateSource));
            array_splice($templateRelativeDesignTemp, 0, 3);
            array_splice($templateRelativeDesignTemp, 1, 0, $s_lang);
            $templateRelativeDesign = implode("/", $templateRelativeDesignTemp);
            $code = file_get_contents($templateSource);
            $success = Template_Modules_SubTemplates::updateSubTpl($code, $subtplName, $subtplIndex, $_POST["OPTIONS"], $_POST["FALLBACK"]);
            if ($success) {
                file_put_contents($templateSource, $code);
                $cacheTemplate = new CacheTemplate();
                $cacheTemplate->cacheFile($templateRelativeDesign);
            }
        }
        header("Content-Type: application/json");
        die(json_encode(array( "success" => $success )));
    default:
        break;
}
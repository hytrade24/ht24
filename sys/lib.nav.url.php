<?php
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 6.5.3
 */

class NavUrlManagement {
    private static $instance = null;

    /**
     * Singleton
     *
     * @param ebiz_db $db
     * @return NavUrlManagement
     */
    public static function getInstance(ebiz_db $db) {
        if (self::$instance === null) {
            self::$instance = new self($db);
        }

        return self::$instance;
    }

    private $db;

    public function __construct(ebiz_db $db) {
        $this->db = $db;
    }

    public function deleteById($idNavUrl) {
        $result = $this->db->querynow("DELETE FROM `nav_url` WHERE ID_NAV_URL=".(int)$idNavUrl);
        if ($result["rsrc"]) {
            $this->updateCache();
            return true;
        } else {
            return false;
        }
    }

    public function fetchAllPages($idLang = null) {
        if ($idLang === null) {
            $idLang = $GLOBALS['lang_list'][ $GLOBALS['s_lang'] ]['ID_LANG'];
        }
        require_once 'sys/lib.nestedsets.php'; // Nested Sets
        $nest = new nestedsets('nav', 1, true);
        $subselectsUrl = "
            (SELECT urlPri.URL_PATTERN
                FROM `nav_url` urlPri
                WHERE urlPri.FK_NAV=t.ID_NAV AND urlPri.FK_LANG=".(int)$idLang."
                ORDER BY urlPri.PRIORITY DESC
                LIMIT 1) as URL_PATTERN,
            (SELECT count(*)
                FROM `nav_url` urlPri
                WHERE urlPri.FK_NAV=t.ID_NAV AND urlPri.FK_LANG=".(int)$idLang.") as URL_COUNT";
        $query = $nest->nestQuery(' AND t.IDENT!=""', '', $subselectsUrl.", ", true);
        $query = str_replace("order by LFT", "ORDER BY t.URL_PRIORITY DESC, t.LFT ASC", $query);
        return $this->db->fetch_table($query);
    }

    public function fetchUrlsByNav($idNav, $idLang = null) {
        if ($idLang === null) {
            $idLang = $GLOBALS['lang_list'][ $GLOBALS['s_lang'] ]['ID_LANG'];
        }
        return $this->db->fetch_table("SELECT * FROM `nav_url` WHERE FK_NAV=".(int)$idNav." AND FK_LANG=".(int)$idLang." ORDER BY PRIORITY DESC");
    }

    public function fetchUrlPatternsByNav($idNav, $idLang = null) {
        if ($idLang === null) {
            $idLang = $GLOBALS['lang_list'][ $GLOBALS['s_lang'] ]['ID_LANG'];
        }
        return array_keys($this->db->fetch_nar("SELECT URL_PATTERN FROM `nav_url` WHERE FK_NAV=".(int)$idNav." AND FK_LANG=".(int)$idLang." ORDER BY PRIORITY DESC"));
    }

    public function generateRegexp(&$arNavUrl) {
        $regexp = preg_quote($arNavUrl["URL_PATTERN"], "/");
        $arMapping = array();
        if (preg_match_all('/\\\\{([\\\\!\$\#]*)([0-9A-Za-z-_]+)\\\\}/', $regexp, $arMatches)) {
            foreach ($arMatches[0] as $matchIndex => $matchFull) {
                $matchType = "*";
                $matchPrefixes = $arMatches[1][$matchIndex];
                if (strpos($matchPrefixes, "!") !== false) {
                    $matchType = "+";
                }
                $replaceWith = "(.".$matchType.")";
                if (strpos($matchPrefixes, "$") !== false) {
                    $replaceWith = "([^\/]".$matchType.")";
                } else if (strpos($matchPrefixes, "#") !== false) {
                    $replaceWith = "([0-9-]".$matchType.")";
                }
                $matchValue = $arMatches[2][$matchIndex];
                $arMapping[$matchValue] = "$".($matchIndex+1);
                $regexp = preg_replace("/".preg_quote($matchFull, "/")."/", $replaceWith, $regexp, 1);
            }
        }
        $arNavUrl["URL_REGEXP"] = "/^".$regexp."$/";
        $arNavUrl["URL_MAPPING"] = serialize($arMapping);
    }

    public function generateUrlByNav($id_nav, $uriParameters, $uriParametersOptional, Template $tpl = null, $idLang = null, $onlyPerfectMatches = true) {
        $arUrlList = (array_key_exists($id_nav, $GLOBALS['ar_nav_urls_by_id']) ? $GLOBALS['ar_nav_urls_by_id'][$id_nav] : array());
        if (($idLang !== null) && ($idLang !== $GLOBALS['lang_list'][ $GLOBALS['s_lang'] ]['ID_LANG'])) {
            // Not the current language!!
            if (file_exists($GLOBALS['ab_path'].'cache/nav.url.'.$idLang.'.php')) {
                include $GLOBALS['ab_path'].'cache/nav.url.'.$idLang.'.php';
                $arUrlList = (array_key_exists($id_nav, $ar_nav_urls_by_id) ? $ar_nav_urls_by_id[$id_nav] : array());
            } else {
                $arUrlList = array();
            }
        }
        if (empty($arUrlList)) {
            return false;
        }
        foreach ($uriParametersOptional as $indexOptional => $valueOptional) {
            if ($valueOptional === null) {
                unset($uriParametersOptional[$indexOptional]);
            }
        }
        foreach ($arUrlList as $urlIndex => $urlDetails) {
            $arUrlMapping = unserialize($urlDetails["URL_MAPPING"]);
            $countParameters = 0;
            $countParametersOptional = count($arUrlMapping);
            for ($i = 1; $i <= count($arUrlMapping); $i++) {
                if (array_key_exists($i, $arUrlMapping)) {
                    $countParameters++;
                    $countParametersOptional--;
                } else {
                    break;
                }
            }
            if (($countParameters == count($uriParameters)) && 
                    ($onlyPerfectMatches ? $countParametersOptional == count($uriParametersOptional) : $countParametersOptional <= count($uriParametersOptional))) {
                $skip = false;
                $href = $urlDetails["URL_PATTERN"];
                $matchesAvailable = preg_match_all('/\\{([\\!\$\#]*)([0-9A-Za-z-_]+)\\}/', $href, $arMatches);
                foreach ($arUrlMapping as $parameterIndex => $parameterValueMapped) {
                    if (preg_match('/^\\$([0-9]+)$/', $parameterValueMapped, $arMatchParameter)) {
                        if ($matchesAvailable) {
                            $matchIndex = $arMatchParameter[1];
                            $matchText = $arMatches[0][$matchIndex-1];
                            $parameterValue = "";
                            if (preg_match('/^[0-9]+$/', $parameterIndex)) {
                                $parameterValue = $uriParameters[$parameterIndex - 1];
                            } else if (array_key_exists($parameterIndex, $uriParametersOptional)) {
                                $parameterValue = $uriParametersOptional[$parameterIndex];
                                if ($tpl !== null) {
                                    $parameterValue = $tpl->parseTemplateString($parameterValue);
                                }
                            }
                            $href = preg_replace('/'.preg_quote($matchText, '/').'/', $parameterValue, $href, 1);
                        } else {
                            $skip = true;
                            break;
                        }
                    } else {
                        $parameterValueTemplate = $tpl->parseTemplateString($uriParameters[$parameterIndex-1]);
                        if ($parameterValueTemplate != $parameterValueMapped) {
                            // Fest definierter Parameter stimmt nicht überein
                            $skip = true;
                        }
                    }
                }
                if (!$skip) {
                    return $href;
                }
            }
        }
        if ($onlyPerfectMatches) {
            // Try again allowing less accurate matches
            return $this->generateUrlByNav($id_nav, $uriParameters, $uriParametersOptional, $tpl, $idLang, false);
        } else {
            return false;
        }
    }

    public function parseUrl($urlRaw, &$urlPage, &$urlParameters, &$urlParametersOptional, $idLang = null) {
        if ($idLang === null) {
            $idLang = $GLOBALS['lang_list'][ $GLOBALS['s_lang'] ]['ID_LANG'];
        }
        if (empty($GLOBALS['ar_nav_urls'])) {
            return false;
        } else {
            foreach ($GLOBALS['ar_nav_urls'] as $navUrlIndex => $navUrlDetails) {
                if (preg_match($navUrlDetails["URL_REGEXP"], $urlRaw, $arMatch)) {
                    $urlPage = $navUrlDetails["IDENT"];
                    $urlParameters = array($urlPage);
                    $urlParametersOptional = array();
                    $arMapping = unserialize($navUrlDetails["URL_MAPPING"]);
                    foreach ($arMapping as $paramIndex => $paramValue) {
                        if (preg_match("/^[0-9]+$/", $paramIndex)) {
                            // Default / numeric parameter
                            if (preg_match("/^\\$([0-9]+)$/", $paramValue, $arMatchParam)) {
                                $matchIndex = (int)$arMatchParam[1];
                                $urlParameters[$paramIndex] = $arMatch[$matchIndex];
                            } else {
                                $urlParameters[$paramIndex] = $paramValue;
                            }
                        } else {
                            // Optional / named parameter
                            if (preg_match("/^\\$([0-9]+)$/", $paramValue, $arMatchParam)) {
                                $matchIndex = (int)$arMatchParam[1];
                                $urlParametersOptional[$paramIndex] = $arMatch[$matchIndex];
                            } else {
                                $urlParametersOptional[$paramIndex] = $paramValue;
                            }
                        }
                    }
                    ksort($urlParameters);
                    return true;
                }
            }
        }
        return false;
    }

    public function updateCache($idLang = null) {
        if ($idLang === null) {
            $idLang = $GLOBALS['lang_list'][ $GLOBALS['s_lang'] ]['ID_LANG'];
        }
        $arUrlsByNav = array();
        $arListUrls = $this->db->fetch_table("
            SELECT n.IDENT, u.*
            FROM `nav_url` u
            JOIN `nav` n ON n.ID_NAV = u.FK_NAV
            WHERE u.FK_LANG=".(int)$idLang."
            ORDER BY n.URL_PRIORITY DESC, u.PRIORITY DESC");
        foreach ($arListUrls as $rowIndex => $row) {
            $idNav = $row["FK_NAV"];
            if (!array_key_exists($idNav, $arUrlsByNav)) {
                $arUrlsByNav[$idNav] = array();
            }
            $arUrlsByNav[$idNav][] = $row;
        }

        file_put_contents($GLOBALS['ab_path']."cache/nav.url.".$idLang.".php", "<?php\n".
            "\n\$ar_nav_urls = ".var_export($arListUrls, true).";".
            "\n\$ar_nav_urls_by_id = ".var_export($arUrlsByNav, true).";");
    }

}
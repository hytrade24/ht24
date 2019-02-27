<?php
/**
 * Created by Forsaken
 * Date: 21.04.16
 * Time: 17:41
 */

class Template_Helper_ResourceLoader {

    private static $javascriptSources = array();

    public static function getJavascriptSources() {
        $arResult = array();
        foreach (self::$javascriptSources as $jsIndex => $jsDetails) {
            $strAttributes = 'type="text/javascript"';
            if (is_array($jsDetails["attribs"])) {
                $arAttributes = array();
                if (!array_key_exists("type", $jsDetails["attribs"])) {
                    $jsDetails["attribs"]["type"] = "text/javascript";
                }
                foreach ($jsDetails["attribs"] as $attributeName => $attributeValue) {
                    if (empty($attributeValue)) {
                        $arAttributes[] = $attributeName;
                    } else {
                        $arAttributes[] = $attributeName.'="'.htmlentities($attributeValue).'"';
                    }
                }
                $strAttributes = implode(" ", $arAttributes);
            } else if (!empty($jsDetails["attribs"])) {
                $strAttributes .= " ".$jsDetails["attribs"];
            }
            $arResult[] = '<script '.$strAttributes.' src="'.$jsDetails["source"].'"></script>';
        }
        return $arResult;
    }

    public static function isJavascriptRequired($javascriptSource) {
        $javascriptIdent = "source_".sha1($javascriptSource);
        return self::isJavascriptIdentRequired($javascriptIdent);
    }

    public static function isJavascriptIdentRequired($javascriptIdent) {
        foreach (self::$javascriptSources as $jsIndex => $jsDetails) {
            if ($jsDetails["ident"] == $javascriptIdent) {
                return true;
            }
        }
        return false;
    }

    public static function requireJavascript($javascriptSource, $htmlAttributes = array(), $javascriptIdent = null, $replaceExisting = false) {
        if ($javascriptIdent === null) {
            $javascriptIdent = "source_".sha1($javascriptSource);
        }
        if ($replaceExisting) {
            self::removeJavascriptIdent($javascriptIdent);
        }
        if (!self::isJavascriptIdentRequired($javascriptIdent)) {
            self::$javascriptSources[] = array(
                "ident"     => $javascriptIdent,
                "attribs"   => $htmlAttributes,
                "source"    => $javascriptSource
            );
        }
        return true;
    }
    
    public static function removeJavascript($javascriptSource) {
        $javascriptIdent = "source_".sha1($javascriptSource);
        return self::isJavascriptIdentRequired($javascriptIdent);
    }
    
    public static function removeJavascriptIdent($javascriptIdent) {
        foreach (self::$javascriptSources as $jsIndex => $jsDetails) {
            if ($jsDetails["ident"] == $javascriptIdent) {
                array_splice(self::$javascriptSources, $jsIndex, 1);
                return true;
            }
        }
        return false;
    }

    /*
     * Library specific functions
     */
    
    public static function requireGoogleMaps($arLibrarys) {
        // Add libraries
        $arLibsUsed = (array_key_exists("_js_google_maps_libs", $GLOBALS) ? $GLOBALS["_js_google_maps_libs"] : array());
        $arLibsAvailable = array("drawing", "geometry", "places", "visualization", "adsense");
        foreach ($arLibrarys as $libIndex => $libName) {
            if (in_array($libName, $arLibsAvailable) && !in_array($libName, $arLibsUsed)) {
                $arLibsUsed[] = $libName;
            }
        }
        // Update libraries cache
        $GLOBALS["_js_google_maps_libs"] = $arLibsUsed;
        // Generate url
        $mapsApi = Geolocation_GoogleMaps::$googleApiKeyJavascript;
        $mapsUrl = "https://maps.googleapis.com/maps/api/js";
        $mapsParams = array();
        if (!empty($mapsApi)) {
            $mapsParams["key"] = $mapsApi;
        }
        if (!empty($arLibsUsed)) {
            $mapsParams["libraries"] = implode(",", $arLibsUsed);
        }
        if (!empty($mapsParams)) {
            $mapsUrl .= "?".http_build_query($mapsParams);
        }
        // Add to resources
        Template_Helper_ResourceLoader::requireJavascript($mapsUrl, "async defer", "googleMapsApi", true);
        return true;
    }
    
} 
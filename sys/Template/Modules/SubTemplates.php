<?php
/**
 * Created by Forsaken
 * Date: 13.03.15
 * Time: 23:25
 */

class Template_Modules_SubTemplates {

    private static $parentStackNames = array();
    private static $parentStackTemplates = array();

    /**
     * Create a configuration object for a sub template
     * @param Template  $template       The content template of the sub template
     * @param string    $name           The name of the sub template
     * @param string    $description    The description as show in the admin configuration
     * @return Template_Modules_SubTemplates
     */
    public static function create($template, $name, $description) {
        return new Template_Modules_SubTemplates($template, $name, $description);
    }

    /**
     * Push a new parent template to the stack that will be used for future loaded sub template
     * @param $template Filename of the current parent template
     * @return bool
     */
    public static function pushParent($template) {
        array_unshift(self::$parentStackNames, $template);
        array_unshift(self::$parentStackTemplates, array());
        return true;
    }

    /**
     *
     * @param $template
     * @return bool|mixed
     * @throws Exception
     */
    public static function popParent($template) {
        $topParent = array_shift(self::$parentStackNames);
        $topParentTemplates = array_shift(self::$parentStackTemplates);
        if ($topParent != $template) {
            throw new Exception("[SubTemplates] Unexpected entry on parent stash! '".$topParent."' found, '".$template."' expected.");
            return false;
        } else {
            return $topParentTemplates;
        }
    }

    private $template;
    private $name;
    private $index;
    private $description;
    private $options;

    public function __construct($template, $name, $description) {
        $this->template = $template;
        $this->name = $name;
        $this->description = $description;
        $this->options = array();
        // Get index of this sub template
        $this->index = 0;
        if (!empty(self::$parentStackTemplates)) {
            $arCounters = self::$parentStackTemplates[0];
            if (array_key_exists($name, $arCounters)) {
                // Not the first sub template of this kind, update index and increase counter
                $this->index = $arCounters[$name]++;
            } else {
                // First of this kind, add counter
                $arCounters[$name] = 1;
            }
            self::$parentStackTemplates[0] = $arCounters;
        }
    }

    /**
     * Add a string option to this sub template
     * @param string    $name       The name of the option as added as subtpl parameter
     * @param string    $label      Label of this option to be displayed within the admin settings
     * @param bool      $required   Wether this option is required or not (Alwayse false if default given)
     * @param string    $default    The default value for this option
     * @return string   The value of this option
     */
    public function addOptionHidden($name, $label, $required = true, $default = null) {
        if ($default !== null) {
            $required = false;
        }
        $value = (array_key_exists($name, $this->template->vars) ? $this->template->vars[$name] : null);
        $this->options[] = array(
            "NAME"      => $name,
            "TYPE"      => "HIDDEN",
            "LABEL"     => $label,
            "REQUIRED"  => $required,
            "DEFAULT"   => $default,
            "VALUE"     => $value,
            "VALUE_RAW" => $this->template->vars[$name]
        );
        $result = ($value !== null ? $value : $default);
        return ($result !== null ? $this->template->parseTemplateString($result) : null);
    }

    /**
     * Add a string option to this sub template
     * @param string    $name       The name of the option as added as subtpl parameter
     * @param string    $label      Label of this option to be displayed within the admin settings
     * @param string    $lookupType The type of lookup to be used ("art")
     * @param bool      $required   Wether this option is required or not (Alwayse false if default given)
     * @param string    $default    The default value for this option
     * @return string   The value of this option
     */
    public function addOptionLookup($name, $label, $lookupType, $required = true, $default = null) {
        if ($default !== null) {
            $required = false;
        }
        $value = (array_key_exists($name, $this->template->vars) ? $this->template->vars[$name] : null);
        $this->options[] = array(
            "NAME"      => $name,
            "TYPE"      => "LOOKUP",
            "LABEL"     => $label,
            "LOOKUP"    => $lookupType,
            "REQUIRED"  => $required,
            "DEFAULT"   => $default,
            "VALUE"     => $value,
            "VALUE_RAW" => $this->template->vars[$name]
        );
        $lookups = Api_LookupManagement::getInstance($GLOBALS["db"]);
        $lookupId = ($value !== null ? $value : $default);
        if ($lookupId !== null) {
            $lookupId = ($lookupId !== null ? $this->template->parseTemplateString($lookupId) : null);
        }
        if ($lookupId > 0) {
            return $lookups->readValueById($lookupId);
        } else {
            $arLookups = array_values($lookups->readByArt($lookupType));
            if (!empty($arLookups)) {
                return $arLookups[0]["VALUE"];
            } else {
                return null;
            }
        }
    }

    /**
     * Add a string option to this sub template
     * @param string    $name       The name of the option as added as subtpl parameter
     * @param string    $label      Label of this option to be displayed within the admin settings
     * @param bool      $required   Wether this option is required or not (Alwayse false if default given)
     * @param string    $default    The default value for this option
     * @return string   The value of this option
     */
    public function addOptionText($name, $label, $required = true, $default = null) {
        if ($default !== null) {
            $required = false;
        }
        $value = (array_key_exists($name, $this->template->vars) ? $this->template->vars[$name] : null);
        $this->options[] = array(
            "NAME"      => $name,
            "TYPE"      => "TEXT",
            "LABEL"     => $label,
            "REQUIRED"  => $required,
            "DEFAULT"   => $default,
            "VALUE"     => $value,
            "VALUE_RAW" => $this->template->subtplParams[$name]
        );
        $result = ($value !== null ? $value : $default);
        return ($result !== null ? $this->template->parseTemplateString($result) : null);
    }

    /**
     * Add a checkbox option to this sub template
     * @param string    $name       The name of the option as added as subtpl parameter
     * @param string    $label      Label of this option to be displayed within the admin settings
     * @param string    $default    The default value for this option
     * @return string   The value of this option
     */
    public function addOptionCheckbox($name, $label, $default = null) {
        $value = (array_key_exists($name, $this->template->vars) ? $this->template->vars[$name] : null);
        $this->options[] = array(
            "NAME"      => $name,
            "TYPE"      => "CHECKBOX",
            "LABEL"     => $label,
            "REQUIRED"  => false,
            "DEFAULT"   => $default,
            "VALUE"     => $value,
            "VALUE_RAW" => $this->template->subtplParams[$name]
        );
        $result = ($value !== null ? $value : $default);
        return ($result !== null ? $this->template->parseTemplateString($result) : null);
    }

    /**
     * Add a checkbox option to this sub template
     * @param string    $name           The name of the option as added as subtpl parameter
     * @param string    $label          Label of this option to be displayed within the admin settings
     * @param string    $default        The default value for this option
     * @param array     $arListItems    Items to be available as checkboxes (index = bit value, value = label, will return sum of bit values)
     * @return string   The value of this option
     */
    public function addOptionCheckboxList($name, $label, $default = null, $arListItems = array()) {
        $value = (array_key_exists($name, $this->template->vars) ? $this->template->vars[$name] : $default);
        $this->options[] = array(
            "NAME"      => $name,
            "TYPE"      => "CHECKBOX_LIST",
            "LABEL"     => $label,
            "REQUIRED"  => false,
            "DEFAULT"   => $default,
            "VALUE"     => $value,
            "VALUE_RAW" => $this->template->subtplParams[$name],
            "ITEMS"     => $arListItems
        );
        $result = ($value !== null ? $value : $default);
        return ($result !== null ? $this->template->parseTemplateString($result) : null);
    }

    /**
     * Add a select option to this sub template
     * @param string    $name           The name of the option as added as subtpl parameter
     * @param string    $label          Label of this option to be displayed within the admin settings
     * @param string    $default        The default value for this option
     * @param array     $arListItems    Items to be available as checkboxes (index = bit value, value = label, will return sum of bit values)
     * @return string   The value of this option
     */
    public function addOptionSelectList($name, $label, $default = null, $arListItems = array()) {
        $value = (array_key_exists($name, $this->template->vars) ? $this->template->vars[$name] : null);
        $this->options[] = array(
            "NAME"      => $name,
            "TYPE"      => "SELECT_LIST",
            "LABEL"     => $label,
            "REQUIRED"  => false,
            "DEFAULT"   => $default,
            "VALUE"     => $value,
            "VALUE_RAW" => $this->template->subtplParams[$name],
            "ITEMS"     => $arListItems
        );
        $result = ($value !== null ? $value : $default);
        return ($result !== null ? $this->template->parseTemplateString($result) : null);
    }

    /**
     * Add a integer option to this sub template
     * @param string    $name       The name of the option as added as subtpl parameter
     * @param string    $label      Label of this option to be displayed within the admin settings
     * @param bool      $required   Wether this option is required or not (Alwayse false if default given)
     * @param string    $default    The default value for this option
     * @return string   The value of this option
     */
    public function addOptionInt($name, $label, $required = true, $default = null) {
        return $this->addOptionIntRange($name, $label, $required, $default);
    }

    /**
     * Add a integer option with a fixed range to this sub template
     * @param string    $name       The name of the option as added as subtpl parameter
     * @param string    $label      Label of this option to be displayed within the admin settings
     * @param bool      $required   Wether this option is required or not (Alwayse false if default given)
     * @param string    $default    The default value for this option
     * @param string    $minValue   The minimum value for this option
     * @param string    $maxValue   The maximum value for this option
     * @return string   The value of this option
     */
    public function addOptionIntRange($name, $label, $required = true, $default = null, $minValue = null, $maxValue = null) {
        if ($default !== null) {
            $required = false;
        }
        $value = (array_key_exists($name, $this->template->vars) ? $this->template->vars[$name] : null);
        $this->options[] = array(
            "NAME"      => $name,
            "TYPE"      => "INT",
            "LABEL"     => $label,
            "REQUIRED"  => $required,
            "DEFAULT"   => $default,
            "VALUE"     => $value,
            "VALUE_MIN" => $minValue,
            "VALUE_MAX" => $maxValue,
            "VALUE_RAW" => $this->template->subtplParams[$name]
        );
        $result = ($value !== null ? $value : $default);
        return ($result !== null ? $this->template->parseTemplateString($result) : null);
    }

    /**
     * Finish setting up the sub templates options and render the admin configuration if the current user has admin rights.
     */
    public function finishOptions() {
        $this->template->isTemplateRecursiveParsable = true;
        $this->template->isTemplateCached = TRUE;
        if ($_SESSION['USER_IS_ADMIN']) {
            $parentTemplate = "";
            if (!empty(self::$parentStackNames)) {
                $parentTemplate = self::$parentStackNames[0];
            }
            $tplConfiguration = new Template("tpl/".$GLOBALS['s_lang']."/subtpl_configuration.htm");
            $tplConfiguration->addvar("SUBTPL_PARENT", $parentTemplate);
            $tplConfiguration->addvar("SUBTPL_FILE", $this->template->filename);
            $tplConfiguration->addvar("SUBTPL_NAME", $this->name);
            $tplConfiguration->addvar("SUBTPL_INDEX", $this->index);
            $tplConfiguration->addvar("SUBTPL_DESC", $this->description);
            $arOptions = array();
            foreach ($this->options as $arOption) {
                $tplOption = new Template("tpl/".$GLOBALS['s_lang']."/subtpl_configuration.option_".strtolower($arOption["TYPE"]).".htm");
                switch ($arOption["TYPE"]) {
                    default:
                        break;
                    case "CHECKBOX_LIST":
                        $arOptionsAssoc = array();
                        foreach ($arOption["ITEMS"] as $itemValue => $itemLabel) {
                            $arOptionsAssoc[] = array("LABEL" => $itemLabel, "VALUE" => $itemValue, "CHECKED" => ($itemValue & $arOption["VALUE"]) == $itemValue);
                        }
                        $tplOption->addlist("OPTIONS", $arOptionsAssoc, "tpl/".$GLOBALS['s_lang']."/subtpl_configuration.option_checkbox_list.item.htm");
                        unset($arOption["ITEMS"]);
                        break;
                    case "SELECT_LIST":
                        $arOptionsAssoc = array();
                        foreach ($arOption["ITEMS"] as $itemValue => $itemLabel) {
                            $arOptionsAssoc[] = array("LABEL" => $itemLabel, "VALUE" => $itemValue, "SELECTED" => ($arOption["VALUE"] == $itemValue));
                        }
                        $tplOption->addlist("OPTIONS", $arOptionsAssoc, "tpl/".$GLOBALS['s_lang']."/subtpl_configuration.option_select_list.item.htm");
                        unset($arOption["ITEMS"]);
                        break;
                    case "LOOKUP":
                        $arLookups = Api_LookupManagement::getInstance($GLOBALS["db"])->readByArt($arOption["LOOKUP"]);
                        $tplOption->addvar("ID_LOOKUP_SELECTED", $arOption["VALUE"]);
                        $tplOption->addlist("OPTIONS", $arLookups, "tpl/".$GLOBALS['s_lang']."/subtpl_configuration.option_lookup.item.htm");
                        break;
                }
                $tplOption->addvars($arOption);
                $arOptions[] = $tplOption;
            }
            $tplConfiguration->addvar("OPTIONS", $arOptions);
            $this->template->addvar("SUBTPL_ADMIN", $tplConfiguration->process(false));
        }
    }

    /**
     * Updates the parameters for the given subtpl call
     * @param string    $code           Source code containing the subtpl call
     * @param string    $subtplName     Name of the subtpl (e.g. "kat_left_2")
     * @param int       $subtplIndex    Index of the call (0 = first, 1 = second; only used for multiple calls to the same subtpl!)
     * @param array     $subtplOptions  New parameters to the call
     * @return bool     True on success.
     */
    public static function updateSubTpl(&$code, $subtplName, $subtplIndex, $subtplOptions, $subtplOptionsFallback = array()) {
        $searchOffset = 0;
        $subtplPositions = array();
        $subtplPreg = "/".preg_quote("{subtpl(", "/")."(tpl\/[^\/]+\/".preg_quote($subtplName.".htm", "/").")/";
        $index = 0;
        while (preg_match($subtplPreg, $code, $arMatches, 0, $searchOffset)) {
            $subtplCurrent = $arMatches[0];
            $searchOffset = strpos($code, $subtplCurrent, $searchOffset);
            $searchOffsetNext = $searchOffset;
            $stackOpen = substr_count($subtplCurrent, "{");
            $stackClose = substr_count($subtplCurrent, "}");
            while ($stackOpen != $stackClose) {
                $searchOffsetNext = strpos($code, "}", $searchOffsetNext);
                if ($searchOffsetNext === false) {
                    break;
                }
                $searchOffsetNext++;
                $subtplCurrent = substr($code, $searchOffset, $searchOffsetNext - $searchOffset);
                $stackOpen = substr_count($subtplCurrent, "{");
                $stackClose = substr_count($subtplCurrent, "}");
            }
            $arMatches[0] = $subtplCurrent;
            $subtplPositions[$index++] = array($arMatches, $searchOffset);
            $searchOffset++;
        }
        $subtplIndex = count($subtplPositions) - $subtplIndex - 1;
        if ($subtplIndex >= 0) {
            list($arMatches, $searchOffset) = $subtplPositions[$subtplIndex];
            $searchOffset = strpos($code, $arMatches[0], $searchOffset);
            $arParameters = array();
            foreach ($subtplOptions as $optionName => $optionValue) {
                if (is_array($optionValue)) {
                    $optionValueSum = 0;
                    foreach ($optionValue as $optionValueItem) {
                        $optionValueSum += $optionValueItem;
                    }
                    $optionValue = $optionValueSum;
                }
                if ($optionValue == "{".$optionName."}") {
                    $arParameters[] = $optionName;
                } if ($optionValue != "") {
                    $arParameters[] = $optionName."=".$optionValue;
                } else if (array_key_exists($optionName, $subtplOptionsFallback)) {
                    $arParameters[] = $optionName."=".$subtplOptionsFallback[$optionName];
                }
            }
            $codeParameters = implode(",", $arParameters);
            $codeTarget = "{subtpl(".$arMatches[1].(!empty($codeParameters) ? ",".$codeParameters : "").")}";
            $code = substr_replace($code, $codeTarget, $searchOffset, strlen($arMatches[0]));
            return true;
        }
        return false;
    }
} 
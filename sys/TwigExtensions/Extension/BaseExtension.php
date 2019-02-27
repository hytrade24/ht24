<?php

class TwigExtensions_Extension_BaseExtension extends \Twig_Extension
{    
    private static $dateIntervalUnits = [
        "year" => 31536000,     // 365 days
        "month" => 2592000,     // 30 days
        "week" => 604800,       // 7 days
        "day" => 86400,         // 24 hours
        "hour" => 3600,         // 60 minutes
        "minute" => 60,         // 60 seconds
        "second" => 1           // 1 second
    ];

    public function __construct()
    {
    }

    /**
     * @return array
     */
    public function getFunctions()
    {
        return [
            'codeAppendContent' => new \Twig_Function_Method($this, 'codeAppendContent'),
            'generateIndexForIdent' => new \Twig_Function_Method($this, 'generateIndexForIdent')
        ];
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return [
            'dateInterval' => new \Twig_Filter_Method($this, 'dateIntervalFilter'),
            'dateIntervalSimple' => new \Twig_Filter_Method($this, 'dateIntervalSimpleFilter'),
        ];
    }

    public function getTokenParsers()
    {
        return [
            new TwigExtensions_TokenParser_CodeAppend()
        ];
    }

    public function getNodeVisitors()
    {
        return [
            #new TwigExtensions_NodeVisitor()
        ];
    }
    
    /**
     * @param $name
     * @return string
     */
    public function codeAppendContent($name)
    {
        TwigExtensions_ScriptAppend::addBlock($name, "__INIT__", "");
        return "{# --- BLOCK ".$name." --- #}";
        /*
        if (array_key_exists('CodeAppend', $GLOBALS) && array_key_exists($name, $GLOBALS['CodeAppend'])) {
            return implode("\n", array_values($GLOBALS['CodeAppend'][$name]));
        } else {
            return "";
        }
        */
    }
    public function dateIntervalString($date, $units = ["day"], $dateNow = null, $transVariant = "simple") {
        if (!is_integer($date)) {
            $date = strtotime($date);
        }
        if ($dateNow === null) {
            $dateNow = time();
        } else if (!is_integer($dateNow)) {
            $dateNow = strtotime($dateNow);
        }
        $intervalSeconds = abs($date - $dateNow);
        $intervalText = [];
        foreach (self::$dateIntervalUnits as $unitName => $unitFactor) {
            if (in_array($unitName, $units) && ($intervalSeconds > $unitFactor)) {
                // Get the amount of the current unit
                $invervalAmount = (int)floor($intervalSeconds / $unitFactor);
                // Remove that amount from the interval in seconds
                $intervalSeconds -= $invervalAmount * $unitFactor;
                // Generate the translation ident for the current unit
                $intervalUnitTrans = "general.date.interval." . $unitName . (!empty($transVariant) ? ".".$transVariant : "");
                $intervalUnitTransValue = trans_choice($intervalUnitTrans, $invervalAmount, ["amount" => $invervalAmount]);
                if ($intervalUnitTransValue == $intervalUnitTrans) {
                    // No translation defined! Use fallback
                    if ($invervalAmount != 1) {
                        $intervalUnitTransValue = $unitName . "s";
                    } else {
                        $intervalUnitTransValue = $unitName;
                    }
                }
                $intervalText[] = Twig::createTemplate($intervalUnitTransValue)->render(["amount" => $invervalAmount]);
            }
        }
        return implode(", ", $intervalText);
    }
    
    public function dateIntervalFilter($date, $units = ["day"], $dateNow = null) {
        $intervalPositive = ($date > $dateNow);
        $intervalText = $this->dateIntervalString($date, $units, $dateNow, "");
        if (empty($intervalText) && !empty($units)) {
            // Interval is below the smallest unit
            $unitName = array_pop($units);
            $intervalUnitTrans = "general.date.interval." . $unitName;
            $intervalUnitTransValue = trans_choice($intervalUnitTrans, 1, ["amount" => 1]);
            if ($intervalUnitTransValue == $intervalUnitTrans) {
                // No translation defined! Use fallback
                $intervalUnitTransValue = $unitName;
            }
            $intervalText = $intervalUnitTransValue;
            // Generate the translation ident for the interval
            $intervalTrans = "general.date.interval.".($intervalPositive ? "positive" : "negative").".less";
            $intervalTransValue = trans($intervalTrans, [ "interval" => $intervalText ]);
            if ($intervalTransValue == $intervalTrans) {
                // No translation defined! Use fallback
                if ($intervalPositive) {
                    $intervalTransValue = "In less than {{ interval }}";
                } else {
                    $intervalTransValue = "Less than {{ interval }} ago";
                }
            }
            return Twig::createTemplate($intervalTransValue)->render([ "interval" => $intervalText ]);
        }
        // Generate the translation ident for the interval
        $intervalTrans = "general.date.interval.".($intervalPositive ? "positive" : "negative");
        $intervalTransValue = trans($intervalTrans, [ "interval" => $intervalText ]);
        if ($intervalTransValue == $intervalTrans) {
            // No translation defined! Use fallback
            if ($intervalPositive) {
                $intervalTransValue = "In {{ interval }}";
            } else {
                $intervalTransValue = "{{ interval }} ago";
            }
        }
        return Twig::createTemplate($intervalTransValue)->render([ "interval" => $intervalText ]);
    }
    
    public function dateIntervalSimpleFilter($date, $units = ["day"], $dateNow = null, $transVariant = "simple") {
        $intervalText = $this->dateIntervalString($date, $units, $dateNow, $transVariant);
        if (empty($intervalText)) {
            // Interval is below the smallest unit
            $unitName = array_pop($units);
            $intervalUnitTrans = "general.date.interval." . $unitName;
            $intervalUnitTransValue = trans_choice($intervalUnitTrans, 0, ["amount" => 0]);
            if ($intervalUnitTransValue == $intervalUnitTrans) {
                // No translation defined! Use fallback
                $intervalUnitTransValue = $unitName;
            }
            return Twig::createTemplate($intervalUnitTransValue)->render(["amount" => 0]);
        }
        return $intervalText;
    }
    
    public function generateIndexForIdent($ident) {
        if (!array_key_exists("twigGenerateIndexList", $GLOBALS)) {
            $GLOBALS["twigGenerateIndexList"] = array(); 
        }
        if (!array_key_exists($ident, $GLOBALS["twigGenerateIndexList"])) {
            $GLOBALS["twigGenerateIndexList"][$ident] = 0;
        } else {
            $GLOBALS["twigGenerateIndexList"][$ident]++;
        }
        return $GLOBALS["twigGenerateIndexList"][$ident];
    }
    
    /**
     * @return string
     */
    public function getName()
    {
        return 'base_extension';
    }
}

<?php
/* ###VERSIONSBLOCKINLCUDE### */

if (!$_SESSION["USER_IS_ADMIN"]) {
    die(forward($tpl_content->tpl_uri_action("404")));
}

switch ($ar_params[2]) {
    case 'preview':
    case 'preview_live':
        // Disable admin menu for this login
        $tpl_main->LoadText("skin/".$s_lang."/index.htm");
        $tpl_content->LoadText("tpl/".$s_lang."/design_preview.htm");
        // Add example ads
        $arExampleAds = array(
            array("HERSTELLER" => "Beispielhersteller", "PRODUKTNAME" => "Beispielprodukt", "B_TOP" => 0, "RUNTIME_DAYS" => 3, 
                "BF_CONSTRAINTS_B2B" => 0, "PREIS" => 0.99, "PSEUDOPREIS" => 1.50, "VERKAUFSOPTIONEN" => 1, "B_PSEUDOPREIS_DISCOUNT" => 1),
            array("HERSTELLER" => "Beispielhersteller", "PRODUKTNAME" => "Top-Anzeige", "B_TOP" => 15, "RUNTIME_DAYS" => 3, 
                "BF_CONSTRAINTS_B2B" => 0, "PREIS" => 0.99, "PSEUDOPREIS" => 1.50, "VERKAUFSOPTIONEN" => 1, "B_PSEUDOPREIS_DISCOUNT" => 1),
            array("HERSTELLER" => "Beispielhersteller", "PRODUKTNAME" => "Beispielprodukt", "B_TOP" => 0, "RUNTIME_DAYS" => 3, 
                "BF_CONSTRAINTS_B2B" => 0, "PREIS" => 0.99, "PSEUDOPREIS" => 1.50, "VERKAUFSOPTIONEN" => 1, "B_PSEUDOPREIS_DISCOUNT" => 1)            
        );
        $tpl_content->isTemplateRecursiveParsable = true;
        $tpl_content->isTemplateCached = true;
        $tpl_content->addlist("example_ads", $arExampleAds, "tpl/".$s_lang."/marktplatz.row_box.htm");
        $tpl_content->addvar("cssDirectory", ($ar_params[2] == "preview" ? "css-preview" : "css"));
        $tpl_content->addvar("cssRefresh", array_key_exists("nocache", $_REQUEST));
        break;
    case 'writeCss':
        $arResult = array("success" => false);
        $designLang = (array_key_exists("lang", $_POST) ? $_POST["lang"] : $s_lang);
        if (preg_match("/^[^\/]+$/", $_POST["directory"]) && !empty($_POST["content"]) && is_array($_POST["variables"])) {
            $templateName = $GLOBALS['nar_systemsettings']['SITE']['TEMPLATE'];
            $cacheFileCss = $GLOBALS['ab_path']."cache/design/resources/".$designLang."/".$_POST["directory"]."/design.css";
            $designPath = $GLOBALS['ab_path'].'design/';
            $designPathUser = $designPath.$templateName."/".$designLang."/resources/".$_POST["directory"]."/";
            $designFileUser = $designPathUser."design.css";
            // Save CSS
            file_put_contents($cacheFileCss, $_POST["content"]);
            // Copy to active design (for updating template cache without compiling less again)
            if (!is_dir($designPathUser)) {
                mkdir($designPathUser, 0777, true);
            }
            copy($cacheFileCss, $designFileUser);
            // Save variables
            $arVariables = array(
                "NVP"   =>  array(),    // Name => Wert
                "EXT"   =>  array()     // Name => Array("TYPE" => "Foo", "VALUE" => "Bar")
            );
            foreach ($_POST["variables"] as $variableName => $variableValue) {
                $variableNameRaw = $variableName;
                $variableName = ltrim($variableName, "@");
                $arVariables["NVP"][$variableName] = $variableValue;
            }
            file_put_contents($ab_path."cache/design/resources/".$designLang."/".$_POST["directory"]."/design.vars.php", "<?php \$arLessVariables = ".php_dump($arVariables).";");
            // Update result
            $arResult["success"] = true;
        }
        header("Content-Type: application/json");
        die(json_encode($arResult));
    default:
        break;
}
<?php
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 7.2.1
 */

global $nar_systemsettings;

include_once $ab_path."sys/lib.shop_kategorien.php";

$tpl_content->addvar("USE_HERSTELLER", $nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB']);
$tpl_content->addvar("SHOW_RATING", $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_RATED']);

$kat = new TreeCategories("kat", 1);
$id_kat_root = $kat->tree_get_parent();

// Einstellungen auslesen
$subtplConfig = Template_Modules_SubTemplates::create($tpl_content, "share_social", "Auf Sozialen Netzwerken teilen");
$socialSites = $subtplConfig->addOptionCheckboxList("SOCIAL_SITES", "Netzwerke", 15, array(
    1 => "Facebook",
    2 => "Twitter",
    4 => "Google+",
    8 => "Whatsapp (Nur Mobil)"
));
$shareUrl = $subtplConfig->addOptionText("URL", "Ziel-URL", false, "");
$text = $subtplConfig->addOptionText("TEXT", "Text zum Teilen", false, "");
$template = $subtplConfig->addOptionSelectList("TEMPLATE", "Darstellung", 'default', array(
    'default'			=> "Standard-Darstellung",
    'inline'			=> "Inline-Darstellung",
    'inline_small'		=> "Inline-Darstellung (Klein)",
    'popover'		    => "Popover-Darstellung"
));
$align = $subtplConfig->addOptionSelectList("ALIGN", "Ausrichtung", 'left', array(
    'left'		    	=> "Links",
    'right'		    	=> "Rechts",
    'top'	        	=> "Oben",
    'bottom'		    => "Unten",
    'center'		    => "Mitte"
));
$subtplConfig->finishOptions();

// Alignment
$alignInline = $align;
$alignPopover = $align;
switch ($align) {
    case "center":
        $alignPopover = "bottom";
        break;
    case "top":
    case "bottom":
        $alignInline = "center";
        break;
}
$tpl_content->addvar("ALIGN_INLINE", $alignInline);
$tpl_content->addvar("ALIGN_POPOVER", $alignPopover);

if (empty($shareUrl)) {
    $shareUrl = (!empty($_SERVER['HTTPS']) ? "https://" : "http://").$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
}
$textParsed = ($this instanceof Template ? $this->parseTemplateString($text) : $tpl_content->parseTemplateString($text));

$arSettings = array(
    "LANG"                      => $s_lang,
    "SOCIAL_SITES" 		        => $socialSites,
    "TEMPLATE"                  => $template,
    "TEXT"                      => $text,
    "TEXT_RAW"                  => $textParsed,
    "TEXT_ENCODED"              => urlencode($textParsed),
    "URL_RAW"                   => $shareUrl,
    "URL_ENCODED"               => urlencode($shareUrl)
);

$tpl_content->LoadText("tpl/".$s_lang."/share_social.".$template.".htm");
$tpl_content->addvars($arSettings);

?>

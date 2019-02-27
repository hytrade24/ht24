<?php

$tpl_content->addvars($_POST);

// Einstellungen auslesen
$subtplConfig = Template_Modules_SubTemplates::create($tpl_content, "comment_ratings", "Bewertungen");
$subjectTable = $subtplConfig->addOptionText("TABLE", "Tabelle für Bewertungen");
$subjectId = (int)$subtplConfig->addOptionText("FK", "ID des Artikels/... (z.B. Artikel-Nr)", false, "{FK}");
$subjectIdStr = $subtplConfig->addOptionText("FK_STR", "Ident des Artikels/... (z.B. EAN-Nr)", false, "{FK_STR}");
$cacheLifetime = $subtplConfig->addOptionLookup("CACHE_LIFETIME", "Cache gültig für", "CACHE_LIFE", false);

$template = $subtplConfig->addOptionSelectList("TEMPLATE", "Darstellung", 'big', array(
    'big'   => "Groß",
    'small' => "Kompakt",
    'small.hint' => "Kompakt (mit Hinweis)",
    'mini'  => "Minimal"
));
$show_empty_bars = $subtplConfig->addOptionCheckbox("SHOW_EMPTY_BARS", "Leere Balken darstellen", 'false');

$subtplConfig->finishOptions();

$arSettings = array(
    "LANG"            => $s_lang,
    "TABLE" 	      => $subjectTable,
    "ID"	          => $subjectId,
    "ID_STR"	      => $subjectIdStr,
    "TEMPLATE"	      => $template,
    "SHOW_EMPTY_BARS" => $show_empty_bars
);

$cacheHash = sha1("comments|".__FILE__."|".serialize($arSettings));
$cacheStorage = Api_DatabaseCacheStorage::getInstance();

if ($cacheStorage->checkContentValidByHash($cacheHash) && !array_key_exists("recache", $_REQUEST) && !$_SESSION["USER_IS_ADMIN"]) {
    // Cache available!
    $cacheContent = $cacheStorage->getContentByHash($cacheHash);
} else {
    // Read data live
    $templateFilename = "tpl/".$s_lang."/comment_ratings.".$template.".htm";
    if (file_exists($ab_path."cache/design/".$templateFilename)) {
        $tpl_content->LoadText($templateFilename);
    }
    $tpl_content->isTemplateRecursiveParsable = TRUE;
    $tpl_content->isTemplateCached = FALSE;
    require_once $ab_path."sys/lib.comment.php";
    $commentManagement = CommentManagement::getInstance($db, $subjectTable);
    $arSubjectRatings = false;
    if ($subjectId > 0) {
        $arSubjectRatings = $commentManagement->getCommentStats($subjectId);
    } else {
        $arSubjectRatings = $commentManagement->getCommentStatsStr($subjectIdStr);
    }
    if (($arSubjectRatings !== false) && ($arSubjectRatings !== null)) {
        $tpl_content->addvars($arSubjectRatings);
    }
    // Write cache
    $cacheContent = $tpl_content->process(true);
    $arCacheRelations = array("COMMENT_RATING" => 1);
    if ($subjectId > 0) {
        $arCacheRelations[$subjectTable] = $subjectId;
    } else {
        $arCacheRelations[$subjectTable."_".$subjectIdStr] = 1;
    }
    $cacheStorage->addContent($cacheHash, $cacheContent, time() + $cacheLifetime*60, $arCacheRelations);
}

$tpl_content->tpl_text = $cacheContent;
$tpl_content->isTemplateCached = TRUE;
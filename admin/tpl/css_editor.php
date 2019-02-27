<?php

//require_once $ab_path . 'sys/lib.template.design.php';

//$templateDesigns = TemplateDesignManagement::getInstance($db);
//$tpl_content->addvars($_REQUEST);

$ar_lang = $db->fetch_table("select BITVAL, ABBR, !STRCMP(ABBR,'".$sel_lang."') AS IS_ACTIVE from lang");
$tpl_content->addlist('selectlang', $ar_lang, 'tpl/de/template_edit.lang_selection_row.htm');

$code = CacheTemplate::getSourceFile("resources/".$s_lang."/css/user.css");

if ( isset($_REQUEST['TPLCODE']) ) {

    $fp = @fopen($code, "w");
    $err = null;
    $w =null;

    if ( !$fp ) {
        $err[] = "Template kann ncht geschrieben werden!";
    }
    else {
        $w = @fwrite($fp, $_REQUEST['TPLCODE']);
        if ( !$w ) {
            $err[] = "Template kann ncht geschrieben werden!";
        }
        else {
            $tpl_content->addvar("OK", "Template wurde aktualisiert!");

            $cacheTemplate = new CacheTemplate();
            $cacheTemplate->cacheFile( $code );
        }
    }
}

$tpl_content->addvar("curpage", "css_editor");
$tpl_content->addvar("action", "save_css_file");

$tpl_code = @file_get_contents( $code );
$tpl_content->addvar("TPLCODE", $tpl_code);

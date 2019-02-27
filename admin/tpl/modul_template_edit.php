<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path . 'sys/lib.template.design.php';

$templateDesigns = TemplateDesignManagement::getInstance($db);
$tpl_content->addvars($_REQUEST);

$path = (1 == $root ? '../design/' : '');

$design = $nar_systemsettings['SITE']['TEMPLATE'];

if($design == 'default') {
    $tpl_content->addvar('err_design_default', 1);
}

if ((!isset($_REQUEST['sel_lang']) || $_REQUEST['sel_lang'] == '')) {
	$sel_lang = $s_lang;
 	$_REQUEST['sel_lang'] = $s_lang;
} else {
    $sel_lang = $_REQUEST['sel_lang'];
}

if($_REQUEST['action'] == 'recover') {
    $filename = $ab_path . 'design/'. $design . '/' . $sel_lang . '/module/' . $_REQUEST['tpl'];
    if(file_exists($filename)) {
        unlink($filename);
    }

    $cacheTemplate = new CacheTemplate();
    $cacheTemplate->cacheFile('module/tpl/' . (($sel_lang == 'default')?'de':$sel_lang) . '/' . $_REQUEST['tpl']);
}


if (count($_POST)) {
    $filename = $ab_path . "design/".$design."/".$sel_lang."/module/".$_REQUEST['tpl'];

    if(!is_dir($ab_path . 'design/'. $design)) { mkdir($ab_path . 'design/'. $design); chmod($ab_path . 'design/'. $design, 0777); }
    if(!is_dir($ab_path . 'design/'. $design . '/' . $sel_lang)) { mkdir($ab_path . 'design/'. $design . '/' . $sel_lang); chmod($ab_path . 'design/'. $design . '/' . $sel_lang, 0777); }
    if(!is_dir($ab_path . 'design/'. $design . '/' . $sel_lang . '/module')) { mkdir($ab_path . 'design/'. $design . '/' . $sel_lang . '/module'); chmod($ab_path . 'design/'. $design . '/' . $sel_lang . '/module', 0777); }



    $fp = @fopen("../design/".$design."/".$sel_lang."/module/".$_REQUEST['tpl'], "w");
    if(!$fp)
        $err[] = "Template kann ncht geschrieben werden!";
    else
    {
        $w=@fwrite($fp, $_REQUEST['TPLCODE']);
     if(!$w)
        $err[] = "Template kann ncht geschrieben werden!";
     else
        $tpl_content->addvar("OK", "Template wurde aktualisiert!");
    }

    $cacheTemplate = new CacheTemplate();
    $cacheTemplate->cacheFile('module/tpl/' . (($sel_lang == 'default')?'de':$sel_lang) . '/' . $_REQUEST['tpl']);

    if(strstr($_REQUEST['page'], "faq")) {
        include "sys/lib.cache.php";
         cache_faq();
    }
}


$ar_lang = $db->fetch_table("select BITVAL, ABBR, !STRCMP(ABBR,'".$sel_lang."') AS IS_ACTIVE from lang");
$tpl_content->addlist('selectlang', $ar_lang, 'tpl/de/template_edit.lang_selection_row.htm');

$tpl_content->addvars($templateDesigns->getDesignInformation($design), 'DESIGN_');

$filename = $ab_path . "design/".$design."/".$sel_lang."/module/".$_REQUEST['tpl'];

$code = @file_get_contents($ab_path."design/".$design."/".$sel_lang."/module/".$_REQUEST['tpl']);

$tpl_content->addvar('filename', $filename);
if ($code == "" || $code == false) {

    CacheTemplate::getHeadFile('module/tpl/' . (($sel_lang == 'default')?'de':$sel_lang) . '/' . $_REQUEST['tpl']);
    $code = @file_get_contents(CacheTemplate::getHeadFile('module/tpl/' . (($sel_lang == 'default')?'de':$sel_lang) . '/' . $_REQUEST['tpl']));
}

if(!$code)
    $err[] = "Template- Quellcode konnte nicht eingelesen werden!";

$_REQUEST['TPLCODE'] = $code;

if(count($err))
    $tpl_content->addvar("err", implode("<br />", $err));


//$_REQUEST['TPLCODE'] = addnoparse($_REQUEST['TPLCODE']);
$tpl_content->addvars($_REQUEST);

if(count($err))
    $tpl_content->addvar("err", implode("<br />", $err));
//$_REQUEST['TPLCODE'] = addnoparse($_REQUEST['TPLCODE']);
$tpl_content->addvars($_REQUEST);
$tpl_content->addvars($_REQUEST);


?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once 'sys/lib.nestedsets.php'; // Nested Sets // nur fuer function root
require_once $ab_path . 'sys/lib.template.design.php';

$templateDesigns = TemplateDesignManagement::getInstance($db);

$tpl_content->addvar('ROOT', $root = root('nav'));

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
    if ($root == 1) {
        $filename = $ab_path . 'design/'. $design . '/' . $sel_lang . '/tpl/' . $_REQUEST['tpl'] . '.htm';
        if(file_exists($filename)) {
            unlink($filename);
        }

        $cacheTemplate = new CacheTemplate();
        $cacheTemplate->cacheFile('tpl/' . (($sel_lang == 'default')?'de':$sel_lang) . '/' . $_REQUEST['tpl'] . '.htm');
    }
}

if (count($_POST)) {
    require_once ("sys/lib.search.php");
    $search = new do_search($s_lang, false);
    $id = $db->fetch_atom("select ID_NAV from nav where IDENT='" . $_REQUEST['tpl'] . "' and ROOT=1");
    $search->add_new_text($_POST['templatecode'], $id, 'nav');

    if ($root == 1) {
        $filename = $ab_path . 'design/'. $design . '/' . $sel_lang . '/tpl/' . $_REQUEST['tpl'] . '.htm';

        if(!is_dir($ab_path . 'design/'. $design)) { mkdir($ab_path . 'design/'. $design); chmod($ab_path . 'design/'. $design, 0777); }
        if(!is_dir($ab_path . 'design/'. $design . '/' . $sel_lang)) { mkdir($ab_path . 'design/'. $design . '/' . $sel_lang); chmod($ab_path . 'design/'. $design . '/' . $sel_lang, 0777); }
        if(!is_dir($ab_path . 'design/'. $design . '/' . $sel_lang . '/tpl')) { mkdir($ab_path . 'design/'. $design . '/' . $sel_lang . '/tpl'); chmod($ab_path . 'design/'. $design . '/' . $sel_lang . '/tpl', 0777); }


    } else {
        $filename = $path . 'tpl/' . $sel_lang . '/' . $_REQUEST['tpl'] . '.htm';
    }

    if(!($_POST['templatecode'] == "" && !file_exists($filename))) {
        file_put_contents($filename, $_POST['templatecode']);

        $cacheTemplate = new CacheTemplate();
        $cacheTemplate->cacheFile('tpl/' . (($sel_lang == 'default')?'de':$sel_lang) . '/' . $_REQUEST['tpl'] . '.htm');
    }
}


$ar_lang = $db->fetch_table("select BITVAL, ABBR, !STRCMP(ABBR,'".$sel_lang."') AS IS_ACTIVE from lang");
$tpl_content->addlist('selectlang', $ar_lang, 'tpl/de/template_edit.lang_selection_row.htm');

$tpl_content->addvars($templateDesigns->getDesignInformation($design), 'DESIGN_');


if($root == 1) {
    $filename = $path . $design . '/'. $sel_lang .'/tpl/' . $_REQUEST['tpl'] . '.htm';
} else {
    $filename = $path . 'tpl/' . $sel_lang . '/' . $_REQUEST['tpl'] . '.htm';
}

$file = @file_get_contents($filename);
$tpl_content->addvar('filename', $filename);


if ($file == "" || $file == false) {
    $file = @file_get_contents(CacheTemplate::getHeadFile('tpl/' . (($sel_lang == 'default')?'de':$sel_lang) . '/' . $_REQUEST['tpl'] . '.htm'));
}

if ((strpos($file, "{") !== FALSE)) {
    $tpl_content->addvar("editor", 0);
} else {
    $tpl_content->addvar("editor", 1);
}
$tpl_content->addvar('templatecode', $file);




$tpl_content->addvar('ID_NAV', $db->fetch_atom("select ID_NAV from nav where IDENT='" . $_REQUEST['tpl'] . "' and ROOT=1"));
$tpl_content->addvars($_REQUEST);

?>

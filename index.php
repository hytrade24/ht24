<?php
/* ###VERSIONSBLOCKINLCUDE### */


if (preg_match("/gzip/i", getenv("HTTP_ACCEPT_ENCODING"))) {
    //ob_start("ob_gzhandler");
}

/*
2008-03-05        BB        handuch-hack eingebaut
2006-05-04  BB  Druckt den Contentbereich aus.  Als Template wird skin/index_print.htm verwendet.
*/

#print_r($_REQUEST);



$path_parts = pathinfo($_SERVER['REQUEST_URI']);

$t_start = microtime();

$n_navroot = 1;
$s_cachepath = 'cache/';

if (file_exists(dirname(__FILE__).'/inc.server.php') && file_exists(dirname(__FILE__).'/install-tool')) {
    system("rm -R ".dirname(__FILE__).'/install-tool');
    if (file_exists(dirname(__FILE__).'/install-tool')) {
        die("Bitte entfernen Sie aus Sicherheitsgr&uuml;nden das Verzeichnis /install-tool/ bevor Sie den ebiz-trader verwenden.");
    }
} elseif(!file_exists(dirname(__FILE__).'/inc.server.php') && file_exists(dirname(__FILE__).'/install-tool')) {
    header("Location: ./install-tool/");
}



require_once 'inc.app.php';
require_once 'sys/lib.kernel.php';
require_once 'inc.error.php';
require_once 'inc.all.php';
require_once 'cache/lang.de.php';

// Register autoloader
registerAutoloader();

if (array_key_exists('ebizRecordLoadtime', $_COOKIE)) {
    Tools_LoadtimeStatistic::getInstance();
}

// ===============================================================
$db = new ebiz_db($db_name, $db_host, $db_user, $db_pass);
unset($db_user); unset($db_pass);

/**
 * Load plugins
 * @var Api_TraderApiHandler $apiHandler
 */
$apiHandler = Api_TraderApiHandler::getInstance($db);
$apiHandler->loadPlugins();

nocache();
$uid = session_init();
maqic_unquote_gpc();
#require_once 'cache/lang.php';

#require_once 'cache/lang.de.php';

if ($uid && $s = $_SESSION['login_referer'])
    $_SERVER['HTTP_REFERER'] = $s;


if(empty($_REQUEST['page']))
    $_REQUEST['page'] = 'index';
$printme=$_REQUEST['page'];

if ($do_print)
{

    $ar_params = explode(',', $what);
    if ($ar_params[0]=='')
        $ar_params[0]='index';

    $_REQUEST['page'] = $ar_params[0];



    if (count($_POST))
        $_POST['page'] = $_REQUEST['page'];


}
else
{
    $ar_params = explode(',', $_REQUEST['page']);
    if (count($ar_params)>1)
    {
        $_REQUEST['page'] = $ar_params[0];
        if (count($_POST))
            $_POST['page'] = $_REQUEST['page'];
    }
}

$ar_params_opt = array();

// URL fuer eventuelles Forward-Formular zusammen bauen
if ($nar_systemsettings['SITE']['MOD_REWRITE'])
{
    $s_selfurl = (preg_match('%/login(/|,|\.|$)%', $_SERVER['REQUEST_URI'])
        ? '/'
        : $_SERVER['REQUEST_URI']
    );
}
else
{
    $b_login_explicit = 'login'==$_REQUEST['page'];
    $s_uri = $_SERVER['REQUEST_URI'];
    $ar_tmp = array ();
    foreach($_REQUEST as $k=>$v) {
        if (preg_match('/^(id|fk|page$|ofs$|s_|do$)/i', $k)) {
            $ar_tmp[] = $k . '=' . rawurlencode($v);
        }
    }
    $s_selfurl = 'index.php?'. implode('&', $ar_tmp);
}

/*handbuch
if (preg_match("/handbuch/i", $s_selfurl)) {
  if( !preg_match("/contributed-php-notes/i", $s_selfurl)) {
        if ($_REQUEST['page']!='handbuch' and $ar_params[1]!='np') {
                 $handbuchpage=$_REQUEST['page'];
                         $_REQUEST['page']='handbuch';
                  $s_page='handbuch';
                  }
        }
}
 handbuch ende*/

if ($tmp = $nar_systemsettings['SITE']['disabled']) // Markt abgeschaltet?
{
    $_SESSION['msg'] = (($s_date = $nar_systemsettings['SITE']['date_enable'])
        ? str_replace('%1', iso2date($s_date, 1), '
      Dieser Markt ist vor&uuml;bergehend deaktiviert
      und voraussichtlich wieder erreichbar ab %1 Uhr.')
        : 'Dieser Markt ist auf unbestimmte Zeit deaktiviert.'
    );
    $b_noerror = $b_nomail = true;
    include 'error.php';
    #echo "hier???";
    die();
}

#echo $_SERVER['QUERY_STRING'], '<br>'
;
$eventIndexUser = false;
if (array_key_exists('ebizRecordLoadtime', $_COOKIE)) {
    $eventIndexUser = Tools_LoadtimeStatistic::getInstance()->createEvent("System", "Init user");
}

$n_rootid = $db->fetch_atom("select ID_NAV from nav where ROOT=". $n_navroot. " and LFT=1");

$user = get_user($uid);

if ($eventIndexUser !== false) {
    $eventIndexUser->finish();
}

$eventIndexNav = false;
if (array_key_exists('ebizRecordLoadtime', $_COOKIE)) {
    $eventIndexNav = Tools_LoadtimeStatistic::getInstance()->createEvent("System", "URL/Page");
}

// Create url object
$urlCurrentRequest = Api_Entities_URL::createFromURL($_SERVER['HTTP_HOST'], $_SERVER["REQUEST_URI"], !empty($_SERVER['HTTPS']), NULL, $_REQUEST['page'], false, $s_page_alias, $ar_params, $ar_params_opt);

// 3. Sprache ermitteln ========================================================

// Detect language by url
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::URL_PROCESS_LANGUAGE, $urlCurrentRequest);
$s_lang = $urlCurrentRequest->getLanguageAbbr();
$langval = $urlCurrentRequest->getLanguageBitval();

if (file_exists('cache/lang.'.$s_lang.'.php')) {
    include 'cache/lang.'.$s_lang.'.php';
}

$originalSystemSettings = $nar_systemsettings;

// Language settings
$language = $lang_list[$s_lang];
if ($language['DOMAIN'] != '') {
    $GLOBALS['nar_systemsettings']['SITE']['SITEURL'] = $language['DOMAIN'];
}
if ($language['BASE_URL'] != '') {
    $GLOBALS['nar_systemsettings']['SITE']['BASE_URL'] = $language['BASE_URL'];
}
$idLang = $GLOBALS['lang_list'][ $GLOBALS['s_lang'] ]['ID_LANG'];

// Navigations Array
global $ar_nav_urls, $ar_nav_urls_by_id;

if (file_exists($s_cachepath. 'nav.url.'.$idLang.'.php')) {
    include $s_cachepath. 'nav.url.'.$idLang.'.php';    // Siehe sys/lib.nav.url.php -> updateCache()
} else {
    $ar_nav_urls = array();
    $ar_nav_urls_by_id = array();
}

include $s_cachepath. 'nav'. $n_navroot. '.'. $s_lang. '.php'; // Struktur: $ar_nav
include $s_cachepath. 'nav'. $n_navroot. '.php'; // Zuordnung Ident/Alias => ID_NAV: $nar_ident2nav

// read pageperms
#echo ht(dump($s_page));
$nar_pageallow = pageperm_read();
$nar_pageallow_all = $nar_pageallow;
foreach($nar_pageallow as $k=>$v) {
    if (preg_match('/^admin\//', $k)) {
        unset ($nar_pageallow[$k]);
    }
}

$navLoadParam = new Api_Entities_EventParamContainer(array(
    "user" => $user,
    "root" => $n_navroot,
    "ar_nav" => $ar_nav,
    "nar_ident2nav" => $nar_ident2nav,
    "nar_pageallow" => $nar_pageallow,
    "id_nav_next" => max(array_keys($ar_nav)) + 1
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::NAV_LOAD, $navLoadParam);
if ($navLoadParam->isDirty()) {
    $ar_nav = $navLoadParam->getParam("ar_nav");
    $nar_ident2nav = $navLoadParam->getParam("nar_ident2nav");
    $nar_pageallow = $navLoadParam->getParam("nar_pageallow");
}

// Infoseiten array
@include_once "cache/info.".$s_lang.".php";

// Plugin ajax
if (array_key_exists('pluginAjax', $_REQUEST)) {
    $ajaxPluginName = $_REQUEST['pluginAjax'];
    $ajaxEventParam = new Api_Entities_EventParamContainer(array(
        "isAdmin" => false,
        "action" => $_REQUEST['pluginAjaxAction']
    ));
    Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::AJAX_PLUGIN, $ajaxEventParam, $ajaxPluginName);
    header("Content-Type: application/json");
    die(json_encode(array("success" => false, "error" => "Unknown action!")));
}

// 4. Alias und Ident ermitteln ================================================

Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::URL_PROCESS_PAGE, $urlCurrentRequest);
$s_page = $urlCurrentRequest->getPageIdent();
$s_page_alias = $urlCurrentRequest->getPageAlias();
$id_nav = $nar_ident2nav[$s_page];
$ar_params = $urlCurrentRequest->getPageParameters();
$ar_params_opt = $urlCurrentRequest->getPageParametersOptional();
$baseurl = $nar_systemsettings['SITE']['BASE_URL'];
global $ab_baseurl;
$ab_baseurl = $baseurl;

if ($id_nav) {
    if (!file_exists(CacheTemplate::getHeadFile('tpl/' . $s_lang . '/' . $s_page . '.htm'))) {
        $s_page = $ar_nav[$id_nav]['IDENT'];
    }
    $s_page_alias = (($tmp = $ar_nav[$id_nav]['ALIAS']) ? $tmp : $s_page);
} else {
    $sql_page = "'". mysql_real_escape_string($s_page). "'";
    $sql_alias = "'". mysql_escape_string($s_page_alias). "'";
    $sql_where = "ROOT=".$n_navroot." AND ".($s_page_alias == "" ? "IDENT=".$sql_page : "(ALIAS=". $sql_alias. " OR IDENT=". $sql_page. ")");
    $id_nav = $db->fetch_atom("select ID_NAV from nav where ".$sql_where." order by if(ALIAS=". $sql_alias. ", 0, 1), ID_NAV");
}

if (!$nar_pageallow[$s_page] && !$nar_pageallow[$s_page_alias])
    if ($id_nav) {
        #die(var_dump($s_page, $id_nav));
        $s_page = ($uid ? '403' : 'login');
    }
    else
    {
        $nar_tplglobals['fnfpage'] = $s_fnfpage = $s_page;
        $s_page = '404';
    }


if (!$uid)
    $nar_pageallow['login'] = 1;

if (!$id_nav)
    $id_nav = $nar_ident2nav[$s_page];

#echo dump($s_page);
$nar_tplglobals['CURDATE'] = date('Y-m-d H:i:s');
$nar_tplglobals['curnav'] = $id_nav;
$nav_current = $ar_nav[$id_nav];

if (!($s_page_alias = $nav_current['ALIAS']))
    $s_page_alias = $nav_current['IDENT'];

if ($s_page && !$nar_pageallow[$s_page])
{
    $s_fnfpage = $s_page;
    $s_page = ($id_nav ? ($uid ? '403' : 'login') : '404');
    if (!$id_nav)
        $id_nav = $nar_ident2nav[$s_page];
}
else
    $nav_current=$ar_nav[$id_nav];



$str_frame = $_REQUEST['frame'];
if (!$layout = $_REQUEST['layout'])
    $layout = $_SESSION['layout'];

$s_curref = 'index.php?';
$ar_tmp = $nar_navvars = array ();#'nav', 'frame', 'page',
foreach($_REQUEST as $k=>$v) if (preg_match('/^((nav|frame|page|ID|id)$|(ID|id)_)/', $k))
{
    if (!is_array($v)) {
        $nar_navvars[$k] = $v;
        $s_curref .= $k. '='. rawurlencode($v). '&';
    }
}
$nar_tplglobals['curref'] = $s_curref;
$nar_tplglobals['curpageref'] = 'index.php?page='. rawurlencode($s_page_alias);
$nar_tplglobals['USER_IS_ADMIN'] = $_SESSION['USER_IS_ADMIN'];

// navpath
$ar_navpath = $ar_ident_path = $ar_label_path = array ();

$bak = $id_nav;
while ($id_nav)
{
    array_unshift($ar_navpath, $id_nav);
    $tmp = $ar_nav[$id_nav];
    if (!$tmp)
    {
        $fl_404 = true;
        break;
    }
    $id_nav = (int)$tmp['PARENT'];
    array_unshift($ar_ident_path, $tmp['IDENT']);
    array_unshift($ar_label_path, $tmp['V1']);
}
$id_nav = $bak;
$curnav = &$ar_nav[(int)$id_nav];

$frameTplParams = new Api_Entities_EventParamContainer(array(
    "name"      => "index",
    "language"  => $s_lang,
    "layout"    => ($_REQUEST['frame'] ? '_'.$_REQUEST['frame'] : $curnav['S_LAYOUT']),
    "frame"     => $str_frame,
    "variables" => array()
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::TEMPLATE_SETUP_FRAME, $frameTplParams);

$tpl_main = new FrameTemplate(
    'skin/'.$frameTplParams->getParam("language").'/'.$frameTplParams->getParam("name").$frameTplParams->getParam("layout"),
    $frameTplParams->getParam("frame")
);
$tpl_main->addvars($frameTplParams->getParam("variables"));

$tpl_main->addvars($GLOBALS['lang_list'][ $GLOBALS['s_lang'] ], "LANGUAGE_");

##############################################################
## 27.02.2012 - NEU: Korrektur bei falsch verlinkten Seiten ##
##############################################################

// Keine Post-Requests prüfen
$url_request = urldecode($urlCurrentRequest->getPath());

setAccessControlAllowOriginHeader();

if (empty($_POST) && (strpos($url_request, "index.php") === false) && !$urlCurrentRequest->isPageCustom()) {
    $ident_path = (!empty($ar_nav[$id_nav]["ident_path"]) ? explode("/", $ar_nav[$id_nav]["ident_path"]) : array());
    $url_path = (!empty($ident_path) ? $ident_path[0]."/" : "");

    if($ar_nav[$id_nav]["ALIAS"]) {
        $url_ident = $ar_nav[$id_nav]["ALIAS"];
        $url_compare_ident =  $ar_nav[$id_nav]["IDENT"];
    } else {
        $url_ident = $ar_nav[$id_nav]["IDENT"];
        $url_compare_ident = NULL;
    }
    $str_params_opt = "";
    if (!empty($ar_params_opt)) {
        $ar_params_opt_pairs = array();
        foreach ($ar_params_opt as $paramName => $paramValue) {
            $tpl_main->addvar("URL_PARAM_".$paramName, $paramValue);
            $ar_params_opt_pairs[] = $paramName."={URL_PARAM_".$paramName."}";
        }
        $str_params_opt = "|".implode(",", $ar_params_opt_pairs);
    }
    $url_full = $tpl_main->tpl_uri_action(implode(",", $ar_params).$str_params_opt);

    if (($url_request !== "/") && (substr($url_request, 0, strlen($url_full)) != $url_full)) {
        $url_correction = $url_full;
        // Check get parameters
        $arGetParameters = $_GET;
        unset($arGetParameters["page"]);
        unset($arGetParameters["lang"]);
        if (!empty($arGetParameters)) {
            $url_correction .= "?". http_build_query($arGetParameters);
        }

        $details = 	"Aufgerufene URL: ".$url_request."\n".
                      "Korrigierte URL: ".$url_correction."\n".
                      "Referer: ".$_SERVER['HTTP_REFERER']."\n".
                      "";
        if (($url_ident != 'index') && LOG_REDIRECTS) {
            eventlog("warning", "Falsche URL aufgerufen! (" . $url_ident . ")", $details);
        }
        header("HTTP/1.1 301 Moved Permanently");

        // TODO: Bessere Lösung finden (forward ohne Modifikation der URL?)
        $url_correction = preg_replace("/".preg_quote($baseurl, "/")."/", "/", $url_correction, 1);
        die(forward( $url_correction ));
    }
}

if ($eventIndexNav !== false) {
    $eventIndexNav->finish();
}

// init Translation
/*require_once $ab_path.'sys/php-gettext/gettext.inc';

$currentLocale = $language['LOCALE']?$language['LOCALE']:'de_DE';

T_setlocale(LC_MESSAGES, $currentLocale);

$domain = 'messages';
T_bindtextdomain($domain, CacheTemplate::getHeadFile("resources/".$s_lang."/locale/"));
T_bind_textdomain_codeset($domain, "UTF-8");
T_textdomain($domain);*/


$eventIndexTemplate = false;
if (array_key_exists('ebizRecordLoadtime', $_COOKIE)) {
    $eventIndexTemplate = Tools_LoadtimeStatistic::getInstance()->createEvent("System", "Init main template", array("frame" => $str_frame));
}

// 5. init templates ===========================================================
$tpl_main->addvar('page', $s_page);
#$tpl_main->addvar('pagepath', implode('-',$ar_ident_path));
$tpl_main->addvar('frame', $str_frame);
$tpl_main->addvars($user);
$_SESSION['layout'] = $layout;

// DLH (styleguide) only: berni raus 21.02.06
//$nar_tplglobals['nav1'] = $tpl_main->tpl_nav('1,1,0');

if (false!==(strpos($tpl_main->tpl_text, '{moreparams}')))
{
    $ar_tmp = array ();
    foreach($_REQUEST as $k=>$v) {
        if (preg_match('^/(fk|id|do)(\_|$)|^(path|nav|page)$/i', $k)) {
            $ar_tmp[] = urlencode($k) . '=' . rawurlencode($v);
        }
    }
    $tpl_main->addvar('moreparams', implode('&', $ar_tmp));
}

$nar_subtpl = array ();
if ($fl_404/** / || !file_exists($fn = findfile($ar_dirs, "$s_page.htm")) /**/)
{
    if (!count($ar_navpath))
    {
        $ar_navpath = array ($tmp = $group['FK_NAV']);
        while ($tmp)
        {
            array_unshift($ar_navpath, $tmp);
            $tmp = $ar_nav[$tmp]['PARENT'];
        }
    }
    $str_fnfpage =  $s_page;
    $s_page = 404;
    $str_title = $err_pagenotfound;
}
else
{
    if (preg_match_all('/{content_(\w+)}/', $tpl_main->tpl_text, $ar_tmp))
    {
        #echo(ht(dump($ar_tmp)));
        #$k=0;
        $ar_rev = array_reverse($ar_ident_path);
        $ar_tmp = array_unique($ar_tmp[1]);
        if($nar_systemsettings['SITE']['cache_subtpl'])
            foreach ($ar_tmpa as $s_varname)
                $nar_subtpl[$s_varname] = new Template('tpl/'. $s_lang. '/'. $curnav['subtpl'][$s_varname]. '.htm');
        else
            foreach ($ar_tmp as $s_varname)
                foreach ($ar_rev as $s_ppage)
                    #{$k++;
                    if (file_exists("tpl/$s_lang/$s_ppage.$s_varname.htm"))
                    {
                        $nar_subtpl[$s_varname] = new Template("tpl/$s_lang/$s_ppage.$s_varname.htm");
                        break;
                    }
        #}echo '<h3>', $k, '</h3>';
    }
    /*/
      if (file_exists("tpl/$s_lang/$s_page.links.htm"))
        $tpl_content_links = new FrameTemplate("tpl/$s_lang/$s_page.links", $str_frame);
      if (file_exists("tpl/$s_lang/$s_page.rechts.htm"))
        $tpl_content_rechts = new FrameTemplate("tpl/$s_lang/$s_page.rechts", $str_frame);
    /**/
}

// standard vars
$nar_tplglobals['lang'] = $s_lang;
$nar_tplglobals['curnav'] = $id_nav;
$nar_tplglobals['curpage'] = $s_page;
$nar_tplglobals['curpage_'.$s_page] = 1;
$nar_tplglobals['curpagename'] = $ar_nav[$id_nav]['V1'];
$nar_tplglobals['parentnav'] = $ar_nav[$id_nav]['PARENT'];
$nar_tplglobals['parentpage'] = $ar_nav[$ar_nav[$id_nav]['PARENT']]['IDENT'];
$nar_tplglobals['parentpagename'] = $ar_nav[$ar_nav[$id_nav]['PARENT']]['V1'];
$nar_tplglobals['ident_path'] = str_replace("/", "", ($ar_nav[$id_nav]['ident_path'] ? $ar_nav[$id_nav]['ident_path'] : $ar_nav[$id_nav]['IDENT']));
$nar_tplglobals['curpagealias'] =  ($s_page_alias ? $s_page_alias : $s_page);
$nar_tplglobals['curframe'] = $str_frame;
$nar_tplglobals['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
$nar_tplglobals['pagetitle'] = $curnav['V2'];
$nar_tplglobals['site_name'] = $nar_systemsettings['SITE']['SITENAME'];
$nar_tplglobals['metatags'] = $curnav['T1'];
$nar_tplglobals['path_parts'] = $printme; // wird f�r die Druckfunktion ben�tigt
$nar_tplglobals['siteurl'] = $nar_systemsettings['SITE']['SITEURL'];
$nar_tplglobals['useronlines'] = useronline($user['NAME'],$uid,$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);

$nar_tplglobals['SYS_TPL_LANG'] = $s_lang;
$nar_tplglobals['SYS_MAPS_LANG'] = $s_lang;

// register Translation Tool Shutdown Callback
if ($nar_systemsettings['SITE']['TEMPLATE_TRANSLATION_TOOL'] && array_key_exists('ebizTranslationTool', $_COOKIE)) {
    require_once $ab_path . 'sys/lib.translation_tool.php';
    register_shutdown_function("callback_translation_tool_shutdown");
}

#die(ht(dump($nar_tplglobals)));
if (false!==(strpos($tpl_main->tpl_text, '{content}')))
    /**/
{

    ##handbuch
    /*
    if ($handbuchpage)
    {
      $tpl_content = new FrameTemplate('files/handbuch/'. $s_lang. '/'. $handbuchpage, $str_frame);
      $nar_tplglobals['PHPCODE']=$handbuchpage;
            }
    else
    */
    ##ende handbuch

    $contentTplParams = new Api_Entities_EventParamContainer(array(
        "name"          => $s_page,
        "language"      => $s_lang,
        "frame"         => $str_frame,
        "params"        => $ar_params,
        "params_opt"    => $ar_params_opt,
        "variables"     => array(),
        "replaceContent"=> false
    ));
    Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::TEMPLATE_SETUP_CONTENT, $contentTplParams);

    $tpl_content = new FrameTemplate('tpl/'.$contentTplParams->getParam("language").'/'.$contentTplParams->getParam("name"), $contentTplParams->getParam("frame"));
    if ($contentTplParams->isDirty()) {
        // Some plugin changed the content templates setup, apply changes.
        $ar_params = $contentTplParams->getParam("params");
        $ar_params_opt = $contentTplParams->getParam("params_opt");
        $tpl_content->addvars($contentTplParams->getParam("variables"));
    }
    if (is_array($nar_tplvars))
        $tpl_content->addvars($nar_tplvars);
    $tpl_content->addvars($user, "CURUSER_");
    #  $tpl_main->addvar('pagepath', implode('-',$ar_ident_path));
    #  $tpl_content->addvar('pagepath', implode('-',$ar_ident_path));
    $tpl_content->str_href = 'index.php?'
                             . ($str_frame ? 'frame='.stdHtmlentities($str_frame). '&' : '')
                             . ($s_page_alias ? 'page='. $s_page_alias : ($s_page ? 'page='. $s_page : 'nav='. $id_nav));
    #if ($tpl_content_rechts)
    #  $tpl_content_rechts->addvars($nar_tplglobals);
    #if ($tpl_content_links)
    #  $tpl_content_links->addvars($nar_tplglobals);
} else {
    $tpl_content = '';
}

if (!$str_frame)
    $tpl_main->addvar('get', $_SERVER['QUERY_STRING']);
$tpl_main->addvar('curpage', $s_page);
$tpl_main->addvars($nar_tplglobals);

// NEU VON JENS - 14.02.2012
// Globale Einstellungen laden
$currency = $nar_systemsettings['MARKTPLATZ']['CURRENCY'];
$tpl_main->addvar('CURRENCY_DEFAULT', $currency);

if ($eventIndexTemplate !== false) {
    $eventIndexTemplate->finish();
}

// 6. run ======================================================================
if (is_object($tpl_content))
{
    $tpl_content->addvar('CURRENCY_DEFAULT', $currency);


    #  $tpl_content->addvars($nar_tplglobals);
    if ($_SESSION['msg'])
        #{
        $tpl_content->addvar('msg', $_SESSION['msg']);
    #die(ht(dump($_SESSION)));
    #}
    unset($_SESSION['msg']);
    #  $tpl_content->addvar('msg', $msg = $_SESSION['msg']);
    #echo "SKRIPT $page: $fn<br />". dump($msg).'<hr />';

    //if ($s_page<>'login') //hinzugefuegt berni 04.03.06   if login
    #### Entfernt von Schmalle am 9.3.2006

    /*
     * Hook File Override
     * ZUR�CKGESTELLT
     *
     * @date 2012-01-28
     * @author Danny Rosifka   *
     */

    // Override Hook
    /*if(file_exists('tpl_override/'. $s_page. '.php')) {
        include 'tpl_override/' . $s_page . '.php';
    } else if (file_exists($fn = 'tpl/' . $s_page . '.php')) {
        // Prepend Hook
        if (file_exists('tpl_override/' . $s_page . '.prepend.php')) {
            include 'tpl_override/' . $s_page . '.prepend.php';
        }

        include $fn;

        // Prepend Hook
        if (file_exists('tpl_override/' . $s_page . '.append.php')) {
          include 'tpl_override/' . $s_page . '.append.php';
        }
    }*/
    if ($contentTplParams->getParam("replaceContent") !== false) {
        $tpl_content->tpl_text = $contentTplParams->getParam("replaceContent");
    } else if (file_exists($fn = 'tpl/' . $s_page . '.php')) {
        $eventScript = false;
        if (array_key_exists('ebizRecordLoadtimeTemplate', $_COOKIE)) {
            $eventScript = Tools_LoadtimeStatistic::getInstance()->createEvent("PHP", $fn, array("is_subtpl" => true, "parameters" => $ar_params));
        }
        include $fn;
        if($eventScript !== false) {
            $eventScript->finish();
        }
    }


    while ($s_forward_page)
    {
        $tpl_content->tpl_text = implode('', file(findfile($ar_dirs, "$s_forward_page.htm")));
        @include(findfile($ar_dirs, "$s_forward_page.php"));
    }
}

// canonical
$tpl_main->vars['metatags'] .= $tpl_main->vars['canonical'];

$tpl_main->addvar('forward', $s_selfurl);
// page title
#$tpl_main->addvar('pagetitle', $str_title);
if(is_object($tpl_content))
    if ('user'!=$tpl_content->table)
        $tpl_content->addvars($user);
#echo ht(dump($tpl_content->tpl_text));echo ht(dump($tpl_content->vars));

// log in / out subtpl
$tpl_log = new Template('tpl/'. $s_lang. '/'. ($user['ID_USER'] ? 'logout':'login').'.htm', 'user');


// nav_links
//$tpl_main->addvar('nav_links', $tpl_main->tpl_nav('1,1,0'));  raus berni 04.03.2006

// Mehrsprachigkeit ins Index-Template ...
if (1<count($lang_list))
{
    $lang_list[$s_lang]['is_current'] = true;
    foreach($lang_list as $abbr=>$row)
        $lang_list[$abbr]['langval'] = $langval;
    $tpl_main->addlist('languages', array_values($lang_list), 'skin/'.$s_lang.'/index.langrow.htm');
}

/*
// ADMIN ONLY ==========================
if ($_SESSION['perm_update'])
{
  if ('setcheck'==$_REQUEST['do'])
  {
    $db->querynow("update perm2user set BF_CHECK=(BF_INHERIT | BF_GRANT) &~ BF_REVOKE");
    $db->querynow("delete from perm2user where BF_CHECK=0 and BF_INHERIT=0 and BF_GRANT=0 and BF_REVOKE=0");
    $db->querynow("delete from perm2role where BF_ALLOW=0");
    $_SESSION['perm_update'] = false;
    forward($tpl_main->tpl_curref());
  }
  else
    $tpl_main->addvar('content_links', $t = new Template('tpl/de/perm2user.setcheck.htm'));
#echo ht(dump($t));
}
// END ADMIN ONLY ======================
*/


// 7. parse ====================================================================
// mingle templates
foreach($nar_subtpl as $s_name=>$tpl)
{
    if (file_exists($s_scriptfn = preg_replace('%^tpl/\w+/(.*)\.htm$%', 'tpl/$1.php', $tpl->filename)))
    {
        include $s_scriptfn;
    }
    $tpl_main->addvar('content_'. $s_name, $nar_subtpl[$s_name]);
}
$tpl_main->addvar('content', $tpl_content);

//berni  brauch man das?
$tpl_main->addvar('log', $tpl_log);
if(is_object($tpl_content))
    $tpl_content->addvar('log', $tpl_log);

// SEARCH
$tpl_search = new Template("tpl/".$s_lang."/search.htm");
if(is_object($tpl_main))
    $tpl_main->addvar("search_form", $tpl_search);

$t_end = microtime();$t = explode(' ', $t_start);$t_start = $t[0]+$t[1];$t = explode(' ', $t_end);$t_end = $t[0]+$t[1];$t = $t_end - $t_start;$t_run = $t;
$t_sql = 0; foreach($ar_query_log as $row) $t_sql += $row['flt_runtime'];$t_run -= $t_sql;
$t_start = microtime();

if ($_SESSION['USER_IS_ADMIN']) {
    $eventAdmin = false;
    if (array_key_exists('ebizRecordLoadtime', $_COOKIE)) {
        $eventAdmin = Tools_LoadtimeStatistic::getInstance()->createEvent("System", "Admin-Tool");
    }
    // Zusätzlich Javascript-Befehle für temporär eingeschaltete funktionen 
    $jsAdmin = "";
    // Ladezeit analyse
    if (array_key_exists('ebizRecordLoadtimeTemplate', $_COOKIE) || array_key_exists('ebizRecordLoadtimeDatabase', $_COOKIE)) {
        $jsAdmin .= "adminAnalyseLoadtimeShow(); ";
    }
    // Translation Tool
    $tpl_main->vars['ADMIN_TRANSLATION_TOOL'] = $nar_systemsettings['SITE']['TEMPLATE_TRANSLATION_TOOL'];
    if ($nar_systemsettings['SITE']['TEMPLATE_TRANSLATION_TOOL'] && array_key_exists('ebizTranslationTool', $_COOKIE)) {
        $jsAdmin .= "adminTranslationToolShow(); ";
        $tpl_main->process();

        if ($_SERVER["HTTP_X_REQUESTED_WITH"] != "XMLHttpRequest") {
            require_once $ab_path . 'sys/lib.translation_tool.php';
            $translationTool = new TranslationTool();
            $translationTool->initPageRequest();

            $tpl_main->vars['SECTION_BODY_END'] = $translationTool->renderTranslationTool();
        }
    }

    //....Vars for Front end edit tools ....
    $tpl_main->vars["CURR_SKIN_NAME"] = $tpl_main->filename;
    $tpl_main->vars["CURR_PAGE_NAME"] = $tpl_content->filename;
    //........
    // HTML-Overlay für Admin-Tools hinzufügen
    if (array_key_exists('ADMIN_TOOLS_JS', $tpl_main->vars)) {
        $tpl_main->vars['ADMIN_TOOLS_JS'] .= $jsAdmin;
    } else {
        $tpl_main->vars['ADMIN_TOOLS_JS'] = $jsAdmin;
    }
    $htmlAdmin = $tpl_main->tpl_subtpl("tpl/".$s_lang."/admin_overlay.htm,*");
    if (array_key_exists('SECTION_BODY_END', $tpl_main->vars)) {
        $tpl_main->vars['SECTION_BODY_END'] .= $htmlAdmin;
    } else {
        $tpl_main->vars['SECTION_BODY_END'] = $htmlAdmin;
    }
    if ($eventAdmin !== false) {
        $eventAdmin->finish();
    }
}

$text = $tpl_main->process();

if (!$SILENCE && 'ajax'!=$_REQUEST['frame'])
{
    $t_end = microtime();$t = explode(' ', $t_start);$t_start = $t[0]+$t[1];$t = explode(' ', $t_end);$t_end = $t[0]+$t[1];$t_parse = $t_end - $t_start;
    printf ('run time: <b>%.3f</b> + mysql time: <b>%.3f</b> + parse time: <b>%.3f</b> (sum: <b>%.3f</b> seconds)', $t_run, $t_sql, $t_parse, $t_run+$t_sql+$t_parse);
    echo '<br>user agent: ', $_SERVER['HTTP_USER_AGENT'];
}
elseif($_REQUEST['frame'] == 'ajax')
{
    include "sys/ajax/config.ajax.php";
}

$arJavascripts = Template_Helper_ResourceLoader::getJavascriptSources();
if (!Tools_UserStatistic::getInstance()->has_client_details()) {
    $tplUserStatsAjax = new Template("tpl/".$s_lang."/system-user-stats.htm");
    $arJavascripts[] = $tplUserStatsAjax->process(true);
}
$arTemplateBlocks = array(
    "script" => implode("\n", $arJavascripts)
);
echo Template_Extend_TemplateBlockExtender::extendTemplateCode($text, $arTemplateBlocks);

#echo ht(dump($_COOKIE));
#echo ht(dump($GLOBALS['ar_query_log']));
/*
$sqlList = array();
foreach($GLOBALS['ar_query_log'] as $key => $value) {
	$sqlList[(string)($value['flt_runtime']*10000)] = $value;
}

krsort($sqlList);
echo ht(dump($sqlList));
*/
?>
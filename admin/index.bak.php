<?php
/* ###VERSIONSBLOCKINLCUDE### */


$n_navroot = 2;
$s_cachepath = '../cache/';
require_once '../inc.app.php';
require_once 'sys/lib.kernel.php';
nocache ();
$uid = session_init ();

require_once 'inc.all.php';
maqic_unquote_gpc ();

#preg_match('%^(.*p://)?([^/]*\.)?([\w-]+\.\w+)(/|$)%', $nar_systemsettings['SITE']['SITEURL'], $match);echo ht(dump($match));


// 2. db connect ===============================================================
$db = new ebiz_db ( $db_name, $db_host, $db_user, $db_pass );
unset ( $db_user );
unset ( $db_pass );

$n_rootid = $db->fetch_atom ( "select ID_NAV from nav where ROOT=" . $n_navroot . " and LFT=1" );

$user = get_user ( $uid );

// read pageperms
$nar_pageallow = pageperm_read ();
#echo ht(dump($nar_pageallow));
#echo ht(dump($nar_pageallow));
if (! $nar_pageallow ['admin/' . $s_page])
	$s_page = ($uid ? '403' : 'login');
if (! $uid)
	$nar_pageallow ['admin/login'] = 1;

	// 3. Sprache ermitteln ========================================================
list ( $s_lang, $langval ) = get_language ();

// Navigations Array
include $s_cachepath . 'nav' . $n_navroot . '.' . $s_lang . '.php'; // Struktur: $ar_nav
include $s_cachepath . 'nav' . $n_navroot . '.php'; // Zuordnung Ident/Alias => ID_NAV: $nar_ident2nav


// 4. Alias und Ident ermitteln ================================================
if ($id_nav = ( int ) $_REQUEST ['nav'])
	$s_page = s_findkid ( $id_nav ); #, __LINE__);
else {
	if ($s_page = $_REQUEST ['page'])
		;
	else
		$s_page = s_findkid ( $id_nav ); #, __LINE__);
	$id_nav = $nar_ident2nav [$s_page];
	if ($id_nav)
		if (! file_exists ( 'tpl/' . $s_lang . '/' . $s_page . '.htm' ))
			$s_page = s_findkid ( $id_nav );
}
if ($s_page)
	$id_nav = $nar_ident2nav [$s_page];
elseif ($id_nav)
	$s_page = $ar_nav [$id_nav] ['IDENT'];

if ($id_nav)
	$s_page_alias = (($tmp = $ar_nav [$id_nav] ['ALIAS']) ? $tmp : $s_page);
else {
	$sql_page = "'" . mysql_escape_string ( $s_page ) . "'";
	$sql_alias = "'" . mysql_escape_string ( $s_page_alias ) . "'";
	$id_nav = $db->fetch_atom ( "select ID_NAV from nav where ROOT=" . $n_navroot . " and (ALIAS='" . $sql_alias . "' or IDENT=" . $sql_page . ")
    order by if(ALIAS='" . $sql_alias . "', 0, 1), ID_NAV" );
}

$nar_tplglobals ['curnav'] = $id_nav;
$nav_current = $ar_nav [$id_nav];

if (! ($s_page_alias = $nav_current ['ALIAS']))
	$s_page_alias = $nav_current ['IDENT'];

if ($s_page && ! $nar_pageallow ['admin/' . $s_page]) {
	$s_fnfpage = $s_page;
	$s_page = ($id_nav ? ($uid ? '403' : 'login') : '404');
	if (! $id_nav)
		$id_nav = $nar_ident2nav [$s_page];
}

if (! $s_page) {
	$id_nav = 0;
	if ($uid)
		$fl_404 = true;
	else
		$s_page = 'login';
}
if (is_null ( $id_nav ) && 'login' != $s_page) {
	$nav_current = $db->fetch1 ( $db->lang_select ( 'nav' ) . ' where ID_NAV=' . $n_rootid );
	$s_fnfpage = $s_page;
	$s_page = '404';
} else
	$nav_current = $ar_nav [$id_nav];

$str_frame = $_REQUEST ['frame'];
if (! $layout = $_REQUEST ['layout'])
	$layout = $_SESSION ['layout'];

$s_curref = 'index.php?';
$ar_tmp = $nar_navvars = array (); #'nav', 'frame', 'page',
foreach ( $_REQUEST as $k => $v )
	if (preg_match ( '/^((nav|frame|page|ID|id)$|(ID|id)_)/', $k )) {
		$nar_navvars [$k] = $v;
		$s_curref .= $k . '=' . rawurlencode ( $v ) . '&';
	}
$nar_tplglobals ['curref'] = $s_curref;
$nar_tplglobals ['curpageref'] = 'index.php?page=' . rawurlencode ( $s_page_alias );

// navpath
$ar_navpath = $ar_ident_path = $ar_label_path = array ();

$bak = $id_nav;
while ( $id_nav ) {
	array_unshift ( $ar_navpath, $id_nav );
	$tmp = $ar_nav [$id_nav];
	if (! $tmp) {
		$fl_404 = true;
		break;
	}
	$id_nav = ( int ) $tmp ['PARENT'];
	array_unshift ( $ar_ident_path, $tmp ['IDENT'] );
	array_unshift ( $ar_label_path, $tmp ['V1'] );
}
$id_nav = $bak;

// 5. init templates ===========================================================
$tpl_main = new FrameTemplate ( 'skin/index', $str_frame );
$tpl_main->addvar ( 'page', $s_page );
#$tpl_main->addvar('pagepath', implode('-',$ar_ident_path));
$tpl_main->addvar ( 'frame', $str_frame );
$tpl_main->addvars ( $user );
$_SESSION ['layout'] = $layout;

// DLH (styleguide) only:
$nar_tplglobals ['nav1'] = $tpl_main->tpl_nav ( '1,1,0' );

if (false !== (strpos ( $tpl_main->tpl_text, '{moreparams}' ))) {
	$ar_tmp = array ();
	foreach ( $_REQUEST as $k => $v )
		if (eregi ( '^(fk|id|do)(\_|$)|^(path|nav|page)$', $k ))
			$ar_tmp [] = urlencode ( $k ) . '=' . rawurlencode ( $v );
	$tpl_main->addvar ( 'moreparams', implode ( '&', $ar_tmp ) );
}

$tpl_content_links = $tpl_content_rechts = false;
if ($fl_404) /** / || !file_exists($fn = findfile($ar_dirs, "$s_page.htm")) /**/
{
	if (! count ( $ar_navpath )) {
		$ar_navpath = array ($tmp = $group ['FK_NAV'] );
		while ( $tmp ) {
			array_unshift ( $ar_navpath, $tmp );
			$tmp = $ar_nav [$tmp] ['PARENT'];
		}
	}
	$str_fnfpage = $s_page;
	$s_page = 404;
	$str_title = $err_pagenotfound;
} else {
	if (file_exists ( "tpl/de/$s_page.links.htm" ))
		$tpl_content_links = new FrameTemplate ( "tpl/de/$s_page.links", $str_frame );
	if (file_exists ( "tpl/de/$s_page.rechts.htm" ))
		$tpl_content_rechts = new FrameTemplate ( "tpl/de/$s_page.rechts", $str_frame );
}

// standard vars
$nar_tplglobals ['curnav'] = $id_nav;
$nar_tplglobals ['curpage'] = $s_page;
$nar_tplglobals ['curpagealias'] = ($s_page_alias ? $s_page_alias : $s_page);
$nar_tplglobals ['curframe'] = $str_frame;
#die(ht(dump($nar_tplglobals)));
if (false !== (strpos ( $tpl_main->tpl_text, '{content}' ))) {
	$tpl_content = new FrameTemplate ( 'tpl/de/' . $s_page, $str_frame );
	if (is_array ( $nar_tplvars ))
		$tpl_content->addvars ( $nar_tplvars );
	$tpl_content->addvars ( $nar_tplglobals );
	#  $tpl_main->addvar('pagepath', implode('-',$ar_ident_path));
	#  $tpl_content->addvar('pagepath', implode('-',$ar_ident_path));
	$tpl_content->str_href = 'index.php?' . ($str_frame ? 'frame=' . htmlentities ( $str_frame ) . '&' : '') . ($s_page_alias ? 'page=' . $s_page_alias : ($s_page ? 'page=' . $s_page : 'nav=' . $id_nav));

	#if ($tpl_content_rechts)
#  $tpl_content_rechts->addvars($nar_tplglobals);
#if ($tpl_content_links)
#  $tpl_content_links->addvars($nar_tplglobals);
} else
	$tpl_content = '';
if (! $str_frame)
	$tpl_main->addvar ( 'get', $_SERVER ['QUERY_STRING'] );
$tpl_main->addvar ( 'curpage', $s_page );
$tpl_main->addvars ( $nar_tplglobals );

// 6. run ======================================================================
if (is_object ( $tpl_content ) && file_exists ( $fn = 'tpl/' . $s_page . '.php' )) {
	#  $tpl_content->addvar('msg', $msg = $_SESSION['msg']);
	$_SESSION ['msg'] = false;
	#echo "SKRIPT $page: $fn<br />". dump($msg).'<hr />';
	include_once $fn;
	while ( $s_forward_page ) {
		$tpl_content->tpl_text = implode ( '', file ( findfile ( $ar_dirs, "$s_forward_page.htm" ) ) );
		@include (findfile ( $ar_dirs, "$s_forward_page.php" ));
	}
}

// page title
#$tpl_main->addvar('pagetitle', $str_title);
if (is_object ( $tpl_content )) {
	#  $tpl_content->addvar('pagetitle', $str_title);
	if ('user' != $tpl_content->table)
		$tpl_content->addvars ( $user );
}

// log in / out subtpl
$tpl_log = new Template ( 'tpl/de/' . ($user ['ID_USER'] ? 'logout' : 'login') . '.htm', 'user' );

// nav_links
$tpl_main->addvar ( 'nav_links', $tpl_main->tpl_nav ( '2,3,0' ) );

// Mehrsprachigkeit ins Index-Template ...
if (1 < count ( $tmp = $db->fetch_table ( "select ID_LANG, ABBR, s.V1 LABEL, BITVAL,
  $langval langval, if(BITVAL=$langval,1,0) is_current
from lang g
  left join string s on s.S_TABLE='lang' and s.FK=g.ID_LANG
    and s.BF_LANG=if(g.BF_LANG & $langval, $langval, 1 << floor(log(g.BF_LANG+0.5)/log(2)))
order by g.BITVAL desc" ) ))
	$tpl_main->addlist ( 'languages', $tmp, 'tpl/de/index.langrow.htm' );
	
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
if ($tpl_content_links)
	$tpl_main->addvar ( 'content_links', $tpl_content_links );
if ($tpl_content_rechts)
	$tpl_main->addvar ( 'content_rechts', $tpl_content_rechts );
$tpl_main->addvar ( 'content', $tpl_content );
$tpl_main->addvar ( 'log', $tpl_log );
if (is_object ( $tpl_content ))
	$tpl_content->addvar ( 'log', $tpl_log );

$text = parse ( $tpl_main );

// echo
echo $text;
#echo listtab($ar_query_log);
?>
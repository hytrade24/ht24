<?php
/* ###VERSIONSBLOCKINLCUDE### */

 # todo xxx: blaettern!
  if (!($s_ident = $_REQUEST['ident']))
    die('no ident provided');
  $n_ofs = (int)$_REQUEST['ofs'];

// Suche
$ar_having = $ar_param = array ();
if (strlen($_REQUEST['s_ovr']))
{
  switch ($_REQUEST['s_ovr'])
  {
    case '+': $ar_having[] = 'B_OVR>0'; break;
    case '-+': $ar_having[] = '( B_OVR=0 or  B_OVR=1)'; break;	
    case '-': $ar_having[] = 'B_OVR=0'; break;
    default : $ar_having[] = 'B_OVR<0'; break;
  }
  $ar_param[] = 's_ovr='. rawurlencode($_REQUEST['s_ovr']);
}

$tmp = array (''=>'alle', '+'=>'+', 'o'=>'o', '-'=>'-', '-+'=>'+/-');
$ar = array ();
foreach($tmp as $k=>$v)
  $ar[] = '
  <option '. ($k===$_REQUEST['s_ovr'] ? 'selected ' : ''). 'value="'. $k. '">'. $v. '</option>';
$tpl_content->addvar('opts_ovr', $ar);

if ($s = trim($_REQUEST['s_name']))
{
  $ar_having[] = "NAME like '%". mysql_escape_string($s). "%'";
  $ar_param[] = 's_name='. urlencode($s);
  $tpl_content->addvar('s_name', $s);
}

if ($s = trim($_REQUEST['s_pname']))
{
  $ar_having[] = "(VORNAME like '%". mysql_escape_string($s). "%' or NACHNAME like '%". mysql_escape_string($s). "%')";
  $ar_param[] = 's_pname='. urlencode($s);
  $tpl_content->addvar('s_pname', $s);
}

$s_param = (count($ar_param) ? '&'. implode('&', $ar_param) : '');


// Liste
  if ($fk_user=(int)$_REQUEST['fk_user'])
  {
    require_once 'sys/lib.perm_admin.php';
    pageperm2user_set($fk_user, $s_ident, $_REQUEST['do']);
/** /
    opener_refresh();
/*/
    opener_refresh('index.php?frame=popup&page=pageperm2user&ident='. rawurlencode($s_ident). $s_param. '&ofs='. $n_ofs);
/**/
  }


  $sql_ident = mysql_escape_string($s_ident);
  $s_having = (count($ar_having) ? implode(' and ', $ar_having) : '1');


  // Daten
  $ar_data = $db->fetch_table("select
      if(count(v.FK_ROLE)>count(s.FK_ROLE), 1, 0) INHERIT,
      ifnull(q.B_OVR, -1) B_OVR,
      u.ID_USER 
, u.VORNAME
, u.NACHNAME, u.NAME
    from user u
    left join pageperm2user q on q.FK_USER=ID_USER and q.IDENT='". $sql_ident. "'
    left join role2user v on v.FK_USER=u.ID_USER
    left join pageperm2role s on s.FK_ROLE=v.FK_ROLE and s.IDENT='". $sql_ident. "'
    group by ID_USER
    having $s_having
    order by u.NAME");


//echo ht(dump($ar_data));
  function getroles(&$row, $i) // callback fuer addlist
  {
    $row['roles'] = implode(', ', $GLOBALS['db']->fetch_nar(
      "select ID_ROLE, LABEL from role, role2user
        where ID_ROLE=FK_ROLE and FK_USER=". $row['ID_USER']. "
        order by ID_ROLE"
    ));
  }

  // Template
  
  $tpl_content->addvar('ident', $s_ident);
  $tpl_content->addvar('ofs', $n_ofs);
  $tpl_content->addvar('browseref', 'index.php?frame=popup&page=pageperm2user&ident='
    . urlencode($s_ident). $s_param. '&ofs=');
  $s_param .= '&ofs='. $n_ofs;
  $tpl_content->addvar('s_param', '&'. implode('&', $ar_param));
  $tpl_content->addlist('liste', $ar_data, 'tpl/de/pageperm2user.row.htm', 'getroles');
/*  
  if ($n_count > $n_limit)
    $tpl_content->addlist('browse', $ar_browse, 'skin/browse.item.htm');
*/
	
?>
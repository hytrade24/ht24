<?php
/* ###VERSIONSBLOCKINLCUDE### */



function addsearch(&$row, $i)
{
  $row['SEARCH'] = urlencode($_REQUEST['SEARCH']);
} // addsearch()

if ($_REQUEST) 
   $tpl_content->addvars($_REQUEST);

if($_REQUEST['delete'])
{
  $deleted = $db->fetch_atom("select EMAIL from nl_recp where ID_NL_RECP=".(int)$_REQUEST['delete']);
  if($deleted)
  {
    $db->querynow("delete from nl_recp where ID_NL_RECP=".(int)$_REQUEST['delete']);
	forward("index.php?page=modul_newsletter_confirmed&SEARCH=".urlencode($_REQUEST['SEARCH'])."&deleted=".urlencode($deleted)."&npage=".(int)$_REQUEST['npage']);
  } // gefunden
} // löschen

if($_REQUEST['deleted'])
  $tpl_content->addvar("deleted", urldecode($_REQUEST['deleted']));

$perpage = 25; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

$where = array();

if($_REQUEST['SEARCH'])
{
  $search = sqlString($_REQUEST['SEARCH']);
  $where[] = "EMAIL LIKE '".$search."%'";
}

$where = (count($where) ? " and ".implode(" and ", $where) : ''); 

$all2 = $db->fetch_atom("select count(*) from nl_recp where STAMP is NULL ".$where);  //Alle user im System

$tpl_content->addvar('anzahluser', $all2);


$data = $db->fetch_table('select ID_NL_RECP, EMAIL, ABBR from nl_recp 
  left join  lang ll on ll.BITVAL=LANGVAL 
 where STAMP is NULL '.$where.'
  order by EMAIL LIMIT '.$limit.','.$perpage);

$tpl_content->addvar("pager", htm_browse($all2, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&SEARCH=".urlencode($_REQUEST['SEACH'])."&npage=", $perpage));

$tpl_content->addlist('liste', $data, 'tpl/de/modul_newsletter_confirmed.row.htm', 'addsearch');

?>
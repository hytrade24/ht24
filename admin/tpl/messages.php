<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if($_REQUEST['del'])
   $db->delete("message", (int)$_REQUEST['del']);

 function add_r(&$row, $i)
 {
   $row['SEARCH'] = urlencode($_REQUEST['SEARCH']);
   $row['FKT_R'] = $_REQUEST['FKT'];
   $row['npage'] = $GLOBALS['npage'];
 } // add-r
 
 $npage = ($_REQUEST['npage'] ? $_REQUEST['npage'] : 1);
 $perpage = 20;
 $limit = ($perpage*$npage)-$perpage;
 
 
 $res = $db->querynow("select distinct FKT from message order by FKT");
 $ar=array();
 while($row = mysql_fetch_assoc($res['rsrc']))
 {
   $ar[] = '<option value="'.$row['FKT'].'" '.($row['FKT'] == $_REQUEST['FKT'] ? ' selected' :'').'>'.stdHtmlentities($row['FKT']).'</option>';
 }
 $tpl_content->addvar("liste_fkt", '<option value="">Funktion ausw&auml;hlen</option>'.implode("\n", $ar));
 
 $where = array();
 
 if($_REQUEST['FKT'])
 {
   $where[] = " FKT='".mysql_escape_string($_REQUEST['FKT'])."' ";
 }
 if($_REQUEST['SEARCH'])
 {
   $where[] = " ( t.ERR LIKE '".mysql_escape_string($_REQUEST['SEARCH'])."%'
     or s.V1 LIKE '".mysql_escape_string($_REQUEST['SEARCH'])."%' ) ";
   $tpl_content->addvar("SEARCH", $_REQUEST['SEARCH']);
 } 
 
 $query = "select t.*, left(s.V1, 150) as V1, s.V2, s.T1 
  from `message` t 
   left join string_app s 
    on s.S_TABLE='message' 
	and s.FK=t.ID_MESSAGE 
	and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
   ".(count($where) ? ' where '.implode(" and ", $where) : '')."
   limit ".$limit.", ".$perpage."
 ";
 
 $SILENCE = false;
 
 $all = $db->fetch_atom("select count(t.ID_MESSAGE)
  from `message` t 
   left join string_app s 
    on s.S_TABLE='message' 
	and s.FK=t.ID_MESSAGE 
	and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
   ".(count($where) ? ' where '.implode(" and ", $where) : ''));
 
 $ar = $db->fetch_table($query);
 $tpl_content->addlist("liste", $ar, "tpl/de/messages.row.htm", "add_r");
 $tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=messages&FKT=".$_REQUEST['FKT']."&SEARCH=".urlencode($_REQUEST['SEARCH'])."&npage=", $perpage));
 

?>
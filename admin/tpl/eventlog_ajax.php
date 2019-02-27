<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $npage = ($_REQUEST['npage'] ? $_REQUEST['npage'] : 1);
 $perpage = 5;
 $limit = ($perpage*$npage)-$perpage;
 
 $where = array();
 $show = '';
 $ar_show = array 
 (
   'info', 'warning', 'error'
 ); 
 
 if($_REQUEST['SHOW'])
 {
   if($_REQUEST['SHOW'] == 'all')
     $_REQUEST['SHOW'] = '';
   $show = $_REQUEST['SHOW'];
 } 
 
 $tpl_content->addvar("EVENT_".($show ? $show : 'all'), 1);
 
 if($show)
   $where[] = " EVENT='".$show."'";
 
 $query = "select e.*, left(S_INFO, 18) as S_INFO_SHORT, left(S_ERR, 20) as S_ERR_SHORT
  from eventlog e
  ".(count($where) ? "where ".implode(" and ", $where) : '')."
  order by STAMP DESC, ID_EVENTLOG DESC
  LIMIT 0, ".$perpage;

 $ar_liste = $db->fetch_table($query);
 $tpl_content->addlist("liste", $ar_liste, "tpl/de/eventlog_ajax.row.htm");

?>

<?php
/* ###VERSIONSBLOCKINLCUDE### */

function eventlogErrorHandler($errno, $errstr, $errfile, $errline) {
  if ( E_RECOVERABLE_ERROR===$errno ) {
    return true;
  }
  return false;
}


 $nummer = 1;
 function nummer(&$row, $i)
 {
   global $nummer;
   if($row['USER'])
   {
     $row['NUMMER'] = $nummer;
	 $nummer++;
   }
 } // nummer()

 $npage = ($_REQUEST['npage'] ? $_REQUEST['npage'] : 1);
 $perpage = 50;
 $limit = ($perpage*$npage)-$perpage;
 
 $where = array();
 $show = '';
 $searchText = null;
 $ar_show = array 
 (
   'info', 'warning', 'error'
 ); 
 
 if($_REQUEST['SHOW'])
 {
   if(in_array($_REQUEST['SHOW'], $ar_show))
     $show = $_REQUEST['SHOW'];
 } 
 if(array_key_exists('SEARCH', $_REQUEST) && !empty($_REQUEST['SEARCH']))
 {
   $searchText = $_REQUEST['SEARCH'];
   $tpl_content->addvar("SEARCH", $searchText);
 } 
 
 if($_REQUEST['do']=='löschen')
    {
        $db->querynow('truncate table eventlog');
    }


 if($show)
   $where[] = " EVENT='".$show."'";
 if ($searchText !== null) {
     $where[] = " S_INFO LIKE '%".mysql_real_escape_string($searchText)."%'";
 }
 
 $shows = array();
 
 for($i=0; $i<count($ar_show); $i++)
 {
   $shows[] = '<option value="'.$ar_show[$i].'" '.($show == $ar_show[$i] ? 'selected' : '').'>'.$ar_show[$i].'</option>';
 }
 
 $tpl_content->addvar("liste_show", implode("\n", $shows));
 
 $all = $db->fetch_atom("select count(*) from eventlog 
   ".(count($where) ? "where ".implode(" and ", $where) : ''));
 
 $query = "select e.*, u.NAME as `USER` 
 ,concat(u.VORNAME, ' ', u.NACHNAME) as REALNAME, u.EMAIL, u.ORT, u.PLZ, u.FK_COUNTRY,
 u.GEBDAT,u.STAMP_REG
  from eventlog e
   left join `user`u on e.FK_USER=u.ID_USER
  ".(count($where) ? "where ".implode(" and ", $where) : '')."
  order by STAMP DESC, ID_EVENTLOG DESC
  LIMIT ".$limit.", ".$perpage;

set_error_handler("eventlogErrorHandler");

 $ar_liste = $db->fetch_table($query);
foreach ($ar_liste as $logIndex => $logEntry) {
    $ar_liste[$logIndex]["BACKTRACE"] = false;
    if ($logEntry["S_BACKTRACE"] !== null) {
        $ar_backtrace = unserialize($logEntry["S_BACKTRACE"]);
        if (!empty($ar_backtrace)) {
            try {
                $tpl_backtrace = new Template("tpl/de/eventlogs.backtrace.htm");
                $tpl_backtrace->addlist("liste", $ar_backtrace, "tpl/de/eventlogs.backtrace.row.htm");
                $ar_liste[$logIndex]["BACKTRACE"] = $tpl_backtrace->process();
            } catch (Exception $e) {
                // TODO: Output warning?
            }
        }
    }
}

 $tpl_content->addlist("liste", $ar_liste, "tpl/de/eventlogs.row.htm", "nummer");

$tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=eventlogs&SHOW=".$show."&npage=", $perpage));

restore_error_handler();

?>

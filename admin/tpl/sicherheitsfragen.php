<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $npage = ($_REQUEST['npage'] ? $_REQUEST['npage'] : 1);
 $perpage = 50;
 $limit = ($npage*$perpage)-$perpage;

 if (!empty($_REQUEST['del'])) {
 	// Sicherheitsabfrage löschen
 	$id = (int)$_REQUEST['del'];
 	$query = "DELETE FROM `question` WHERE ID_QUESTION=".$id;
 	mysql_query($query);
 	$query = "DELETE FROM `string_app` WHERE FK=".$id;
 	mysql_query($query);
 }
 
 $liste = $db->fetch_table("select t.*, s.V1, s.V2, s.T1 
  from `question` t 
   left join string_app s 
    on s.S_TABLE='question' 
	and s.FK=t.ID_QUESTION and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2))) 
  order by s.V1 
  limit ".$limit.", ".$perpage."
  ");
  
 $tpl_content->addlist("liste", $liste, "tpl/de/sicherheitsfragen.row.htm");
 
 $all = $db->fetch_atom("select count(*) from question");
 $tpl_content->addvar("pager", htm_browse($all, $npage, "index.php?page=sicherheitsfragen&npage=", $perpage));

?>
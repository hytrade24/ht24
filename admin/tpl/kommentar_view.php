<?php
/* ###VERSIONSBLOCKINLCUDE### */


 $SILENCE = false;
 include_once("sys/lib.newcomment.php");
 include_once($ab_path."sys/lib.bbcode.php");
 
 $id_comment = ($_REQUEST['id_comment'] ? $_REQUEST['id_comment'] : 0);
 $fk = ($_REQUEST['fk'] ? $_REQUEST['fk'] : 0);
 $table = ($_REQUEST['table'] ? $_REQUEST['table'] : 'news');
 $npage = ($_REQUEST['npage'] ? $_REQUEST['npage'] : 0);
 
 $res = $db->querynow("select ID_KOMMENTAR_".strtoupper($table)." from kommentar_".strtolower($table)."
 						where FK = '".$fk."' order by STAMP ASC");
 
 $all = $res['int_result'];
 $i = 0;
 $perpage = 1;
 
 while($row = mysql_fetch_assoc($res['rsrc']))
 {
   $i++;
   if($row['ID_KOMMENTAR_'.strtoupper($table)] == $id_comment)
     $npage = ($npage ? $npage : $i);
 } // pointer position
 
 if($_POST)
 {
   $bbcode = new bbcode();
   $comment = new comment($fk, $table, $id_comment);
 }
   //class comment
 if($_POST['del1'])
 {
   if($_POST['id_comment'] && $_POST['table'])
   {
     $comment->delComment($id_comment, 1);
	 $comment->checkErrors();
   
     if(!empty($comment->err_out))
       {
         # die("HIER ODER WAS?");
	     $tpl_content->addvar('delfail',implode('<br />- ', $comment->err_out)); // Diese im Template ausgeben
       }
	   else
	   {
	       forward("index.php?frame=popup&page=kommentar_view&del=ok&fk=".$_POST['fk']."&table=".$_POST['table']."&npage=".($npage != 1 ? ($npage-1) : $npage));
	   } // keine fehler -> forward
   }// id_comment und table true
 } // ein kommentar löschen
 elseif($_POST['delall'])
 {
   if($_POST['fk'] && $_POST['table'])
   {
     $status = $comment->delComment($_POST['fk'], "all");
	 if($status == 'success')
	   forward("index.php?frame=popup&page=kommentar_view&del=ok&fk=".$_POST['fk']."&table=".$_POST['table']."&npage=".($npage != 1 ? ($npage-1) : $npage));
	 else
	   $tpl_content->addvar("delfail", "Kommentare konnten nicht gel&ouml;scht werden");
   } // fk und table sind true
   else
     $tpl_content->addvar("delfail", "Kommentare können nicht gelöscht werden. Nicht alle Variablen sind vorhanden.");
 } // alle kommentare Löschen
 elseif($_POST['save'])
 {
   $comment->editComment($_POST['comment']);
   $comment->checkErrors();
   
   if(!empty($comment->err_out))
       {
         # die("HIER ODER WAS?");
	     $tpl_content->addvar('delfail',implode('<br />- ', $comment->err_out)); // Diese im Template ausgeben
	     $tpl_content->addvars($_POST);
       }
	   else
	   {
	     forward("index.php?frame=popup&page=kommentar_view&edit=ok&fk=".$_POST['fk']."&table=".$_POST['table']."&npage=".$npage);
	   } // keine fehler -> forward
 }
 else
 {
   if($id_comment && !$npage)
   {
     $where = "where k.FK = '".$fk."' and k.ID_KOMMENTAR_".strtoupper($table)." = ".(int)$id_comment."
					limit 1";
   } /// id_comment true und npage false
   else
     $where = "where k.FK = '".$fk."' order by k.STAMP ASC limit ".($npage-1).", 1";
	 /// where query für pager
	 switch($table)
	 {
	   case "tutorial":
		 $query = "Select k.KOMMENTAR, t.titel as V1, k.ID_KOMMENTAR_".strtoupper($table)." From kommentar_tutorial k
					left join tutorial t on t.ID_TUTORIAL = k.FK
					".$where;
		 break;
	   case "script":
		 $query = "Select k.KOMMENTAR, s.V1, k.ID_KOMMENTAR_".strtoupper($table)." From kommentar_script k
				  left join script t on k.FK = t.ID_SCRIPT 
				  left join string_script s on s.S_TABLE='script' and s.FK=k.FK and s.BF_LANG=if(t.BF_LANG_SCRIPT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_SCRIPT+0.5)/log(2)))
				  ".$where;
		 break;
	   case "handbuch":
		 $query = "Select k.KOMMENTAR, k.FK as V1, k.ID_KOMMENTAR_".strtoupper($table)." From kommentar_handbuch k
				  ".$where;
		 break;
	   default:
		 $query = "Select k.KOMMENTAR, s.V1, k.ID_KOMMENTAR_".strtoupper($table)." From kommentar_news k
				  left join news t on k.FK = t.ID_NEWS 
				  left join string_c s on s.S_TABLE='news' and s.FK=k.FK and s.BF_LANG=if(t.BF_LANG_C & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))
				  ".$where;
		 break;
	 } // query's
	 $comment = $db->fetch1($query);
	 $id_comment = $comment['ID_KOMMENTAR_'.strtoupper($table)];
	 if($comment)
	   $tpl_content->addvars($comment);
 } // kein kommentar Löschen nur anzeigen

 if($_GET['del'])
   $tpl_content->addvar("delok", 1);
   if($_GET['edit'])
   $tpl_content->addvar("editok", 1);
 $tpl_content->addvar("npage", $npage);
 $tpl_content->addvar("fk", $fk);
 $tpl_content->addvar("id_comment", $id_comment);
 $tpl_content->addvar("table", $table);
 $tpl_content->addvar("pager", htm_browse($all, $npage, "index.php?frame=popup&page=kommentar_view&fk=".$fk."&table=".$table."&npage=", $perpage));
 
 
?>
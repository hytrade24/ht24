<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if(!empty($_REQUEST['del']))
 {
   $file = $db->fetch_atom("select DATEINAME from datei where ID_DATEI=".$_REQUEST['del']);
   $del = @unlink($ab_path."uploads/datei/".$file);
   $res = $db->querynow("delete from datei where ID_DATEI=".$_REQUEST['del']);
   forward("index.php?page=datei_uebersicht");
 }
 
 $npage = ($_REQUEST['npage'] ? $_REQUEST['npage'] : 1);
 $orderby = ($_REQUEST['orderby'] ? $_REQUEST['orderby'] : 'DATUM');
 $updown =  ($_REQUEST['updown'] ? $_REQUEST['updown'] : 'DESC');
 $perpage = 10;
 
 $tpl_content->addvar("orderby_".$orderby, 1);
 $tpl_content->addvar("updown_".$updown, 1);
 
 $all = $db->fetch_atom("select count(*) from datei");
 
 $limit = ($npage*$perpage)-$perpage;

 $ar_liste = $db->fetch_table("select * from datei 
   order by ".$orderby." ".$updown."
   limit ".$limit.", ".$perpage);

 $tpl_content->addlist("liste", $ar_liste, "tpl/de/datei_uebersicht.row.htm");
 
 $tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&orderby=".$orderby."&updown=".$updown."&npage=", $perpage));

?>

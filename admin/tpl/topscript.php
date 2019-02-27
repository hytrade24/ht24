<?php
/* ###VERSIONSBLOCKINLCUDE### */


 
 $where = array();
 
 function topdays(&$ar, $i)
 {
   
   $jahresbeginn = mktime(0,0,0,1,1,$ar['JAHR']);
   $anfangstage = date("w", $jahresbeginn-1)*86400;
   $ar['VON'] = $jahresbeginn+(($ar['KW']-1)*86400*7)-$anfangstage;
   $ar['BIS'] = date('d.m.Y', ($ar['VON']+(86400*7))-1);
   $ar['VON'] = date('Y-m-d', $ar['VON']);
   #echo ht(dump($ar));
 } // topdays

 $npage = ($_REQUEST['npage'] ? $_REQUEST['npage'] : 1);
 $perpage = 30;
 $limit = ($npage*$perpage)-$perpage;
 
 $where[] = " ( JAHR >= ".date('Y')." ) and (if(JAHR > ".date('Y').",KW>".date('W').",KW>0))"; 
 
 $liste = $db->fetch_table("select ts.*, s.V1, t.FK_USER, u.`NAME` as UNAME, ".$npage." as npage
  from scripttop ts
   left join script t on ts.FK_SCRIPT=t.ID_SCRIPT
   left join string_script s 
    on s.S_TABLE='script' and s.FK=t.ID_SCRIPT
	and s.BF_LANG=if(t.BF_LANG_SCRIPT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_SCRIPT+0.5)/log(2)))
   left join `user` u on t.FK_USER=u.ID_USER
   where ".implode(" and ", $where)."
   ");
 
 #echo ht(dump($lastresult));
 
 $tpl_content->addlist("liste", $liste, "tpl/de/topscript.row.htm", "topdays");
 
 $all = $db->fetch_atom("select count(*) from scripttop where ".implode(" and ", $where));
 $tpl_content->addvar("pager", htm_browse($all, $npage, "index.php?page=topscript%npage=", $perpage));

?>
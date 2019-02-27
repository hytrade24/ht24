<?php
/* ###VERSIONSBLOCKINLCUDE### */



#select t.*, s.V1, s.V2, s.T1 from `lookup` t left join string s on s.S_TABLE='lookup' and s.FK=t.ID_LOOKUP and s.BF_LANG=if(t.BF_LANG & 128, 128, 1 << floor(log(t.BF_LANG+0.5)/log(2))) 

$perpage = 25; // Elemente pro Seite

if ($_REQUEST) 
   $tpl_content->addvars($_REQUEST);

$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);
$all = $db->fetch_atom("select count(*) from anfrage ");

$data = $db->fetch_table("select t.*,s.V1 as STAT,o.VALUE as CSS 
 from anfrage t
 left join lookup o on t.LU_STATUS = o.ID_LOOKUP 
 left join string s on s.S_TABLE='lookup' and s.FK=o.ID_LOOKUP and s.BF_LANG=if(o.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(o.BF_LANG+0.5)/log(2)))
 order by DATUM DESC LIMIT ".$limit.','.$perpage);
 
#echo ht(dump($lastresult));

$tpl_content->addlist('liste', $data, 'tpl/de/anfragen.row.htm');

$tpl_content->addvar("GESAMT",$all);

$tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&npage=", $perpage));
?>
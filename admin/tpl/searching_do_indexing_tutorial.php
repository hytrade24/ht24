<?php
/* ###VERSIONSBLOCKINLCUDE### */


include "sys/lib.search.php";
$search = new do_search($s_lang);

if($_REQUEST['what'])
  $tpl_content->addvar('what', $_REQUEST['what']);

$perpage=10;

(int)$all = $db->fetch_atom('select  count(*)
				from tutorial_live t
     			left join string_tutorial s on s.S_TABLE="tutorial" and s.FK=t.ID_TUTORIAL_LIVE
				where s.BF_LANG='.$langval.' 				
				group by S_TABLE'); #Anzahl der News ermitteln
(int)$pages_to_go=ceil($all/$perpage); #Anzahl der Seiten die benötigt werden
(int)$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);#limit errechnen
(int)$pages_left=$pages_to_go-($limit/$perpage);

	if (($pages_left)>0) 
	{
		(int)$npage=$_REQUEST['npage']+1;
		//forward('index.php?nav='.$id_nav.'&npage='.$npage.'&running=1&frame=iframe', 0,false, true, 1);
		
		$ar_data = $db->fetch_table('select  t.ID_TUTORIAL_LIVE,s.T1,s.V1
				from tutorial_live t
     			left join string_tutorial s on s.S_TABLE="tutorial" and s.FK=t.ID_TUTORIAL_LIVE 
				where s.BF_LANG='.$langval.'
	 			LIMIT '.$limit.','.$perpage);

		foreach($ar_data as $i=>$row) 
		{			
			$search->add_new_text ($row['T1'].$row['V1'],$row['ID_TUTORIAL_LIVE'],'tutorial');
		}
		$tpl_content->addvars(array ('running' => 1, 'npage' => $npage,'mpage' => $pages_left,'added' => $search->new_word_added));
	}
	else
	{
    if ($_REQUEST['what'] == "all")
      forward('index.php?page=searching_do_indexing_files&what='.$_REQUEST['what'].'&frame=iframe', 0,'self');
    else
      forward('index.php?page=searching_index', 0,'top');
		//forward('index.php?page=searching_index', 0,'top');
	}
?>
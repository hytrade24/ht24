<?php
/* ###VERSIONSBLOCKINLCUDE### */



include "sys/lib.search.php";
$search = new do_search($s_lang);

//$search->add_new_text ($text,'21','news');


if($_REQUEST['what'])
  $tpl_content->addvar('what', $_REQUEST['what']);

$perpage=10;

(int)$all = $db->fetch_atom('select  count(*)
				from faq t
     			left join string_faq s on s.S_TABLE="faq" and s.FK=t.ID_FAQ 
				where s.BF_LANG='.$langval.' 				
				group by S_TABLE'); #Anzahl der News ermitteln
(int)$pages_to_go=ceil($all/$perpage); #Anzahl der Seiten die benötigt werden
(int)$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);#limit errechnen
(int)$pages_left=$pages_to_go-($limit/$perpage);


/*echo "<br>npage :".$_REQUEST['npage'];
echo "<br>limit :".$limit;
echo "<br>pages-left :".$pages_left;
echo "<br>pages_to_go :".$pages_to_go;
echo "<br>";*/

	if (($pages_left)>0) 
	{
		(int)$npage=$_REQUEST['npage']+1;
		//forward('index.php?nav='.$id_nav.'&npage='.$npage.'&running=1&frame=iframe', 0,false, true, 1);
		
		$ar_data = $db->fetch_table('select  t.ID_FAQ,s.T1,s.V1
				from faq t
     			left join string_faq s on s.S_TABLE="faq" and s.FK=t.ID_FAQ 
				where s.BF_LANG='.$langval.'
	 			LIMIT '.$limit.','.$perpage);

		foreach($ar_data as $i=>$row) 
		{			
			#die("angekommen");
			#echo ht(dump($row));
			$search->add_new_text ($row['T1'].$row['V1'],$row['ID_FAQ'],'faq');
		
		}#die();
		$tpl_content->addvars(array ('running' => 1, 'npage' => $npage,'mpage' => $pages_left,'added' => $search->new_word_added));
	}
	else
	{
    forward('index.php?page=searching_index', 0,'top');
		#forward('index.php?page=searching_do_indexing_faq', 0,'self');
		//forward('index.php?page=searching_index', 0,'top');
	}


?>
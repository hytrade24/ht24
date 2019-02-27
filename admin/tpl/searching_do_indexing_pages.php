<?php
/* ###VERSIONSBLOCKINLCUDE### */



include "sys/lib.search.php";
$search = new do_search($s_lang);

//$search->add_new_text ($text,'21','news');

if($_REQUEST['what'])
  $tpl_content->addvar('what', $_REQUEST['what']);

$perpage=10;

(int)$all = $db->fetch_atom('select  count(*)
				from nav t
     			left join string s on s.S_TABLE="nav" and s.FK=t.ID_NAV 
				where s.BF_LANG='.$langval.' 
				and t.ROOT=1 and t.LFT >= 2 and IDENT <> "" and IDENT IS NOT NULL
				group by S_TABLE'); #Anzahl der News ermitteln
(int)$pages_to_go=ceil($all/$perpage); #Anzahl der Seiten die benötigt werden
(int)$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);#limit errechnen
(int)$pages_left=$pages_to_go-($limit/$perpage);

/*
echo "<br>npage :".$_REQUEST['npage'];
echo "<br>limit :".$limit;
echo "<br>pages-left :".$pages_left;
echo "<br>pages_to_go :".$pages_to_go;
echo "<br>";
*/
	if (($pages_left)>0) 
	{
		(int)$npage=$_REQUEST['npage']+1;
		//forward('index.php?nav='.$id_nav.'&npage='.$npage.'&running=1&frame=iframe', 0,false, true, 1);
		
		$ar_data = $db->fetch_table('select  t.ID_NAV,t.IDENT,t.B_SEARCH,s.V1
				from nav t
     			left join string s on s.S_TABLE="nav" and s.FK=t.ID_NAV 
				where s.BF_LANG='.$langval.' and t.B_SEARCH=1
				and t.ROOT=1 and t.LFT >= 2  and IDENT <> "" and IDENT IS NOT NULL
	 			LIMIT '.$limit.','.$perpage);

		foreach($ar_data as $i=>$row) 
		{
		  if (file_exists(CacheTemplate::getHeadFile("tpl/".$s_lang."/".$row['IDENT'].".htm"))) {
  			$content = file_get_contents(CacheTemplate::getHeadFile("tpl/".$s_lang."/".$row['IDENT'].".htm"));
  			#echo stdHtmlentities($content);
  			#die();
  			$search->add_new_text ($content.$row['V1'],$row['ID_NAV'],'nav');
		  }
		}
		$tpl_content->addvars(array ('running' => 1, 'npage' => $npage,'mpage' => $pages_left,'added' => $search->new_word_added));
	}
	else
	{
    if ($_REQUEST['what'] == "all")
      forward('index.php?page=searching_do_indexing_script&what='.$_REQUEST['what'].'&frame=iframe', 0,'self');
    else
      forward('index.php?page=searching_index', 0,'top');
		#forward('index.php?page=searching_do_indexing_faq&frame=iframe', 0,'self');
		#forward('index.php?page=searching_index', 0,'top');
	}


?>
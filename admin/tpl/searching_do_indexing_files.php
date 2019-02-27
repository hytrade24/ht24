<?php
/* ###VERSIONSBLOCKINLCUDE### */



include "sys/lib.search.php";
$search = new do_search($s_lang);

$search->currentDir();

//$search->add_new_text ($text,'21','news');

if($_REQUEST['what'])
  $tpl_content->addvar('what', $_REQUEST['what']);

$perpage=10;

(int)$all = $search->countFiles();
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
		
		$ar = $search->getNextFiles($limit, $perpage);
		for($i=0; $i<count($ar); $i++)
		{
		  $content = file_get_contents($ar[$i]);
		  $content = tagFilter($content); //strip_tags($content);
		  $content = $content['TITLE']." ".$content['TEXT'];
		  #die($content);
		  $hack = explode("/", $ar[$i]);
		  $n = count($hack)-1;
		  $file_name = $hack[$n];
          $search->add_new_text ($content,0, 'files', $_SESSION['last_dir'], $file_name);
		  #die();
		}
		#die(ht(dump($ar)));
		$tpl_content->addvars(array ('running' => 1, 'npage' => $npage,'mpage' => $pages_left,'added' => $search->new_word_added));
	}
	else
	{
		if(!$_SESSION['start'])
		{
		  $next=$_SESSION['std']=false;
		  foreach($search->spider_dirs as $key=>$value)
		  {
		    $std=true;
			if($next == true)
			{
			  $_SESSION['c_files']=0;
			  $_SESSION['last_dir'] = $value;
			  die(forward('index.php?page=searching_do_indexing_files&frame=iframe', 0,'self'));
		    }
			if($value == $_SESSION['last_dir'])
			  $next = true;
		  }
		  if($next == true)
		  {		    
			$next2 = false;			
			if(!empty($search->new_dirs))
			{
			  
			  foreach($search->spider_dirs as $key=>$value)
		      {
		        if($next2 == true || !$_SESSION['std'])
			    {
			      $_SESSION['c_files']=0;
			      $_SESSION['last_dir'] = $value;
			      die(forward('index.php?page=searching_do_indexing_files&frame=iframe', 0,'self'));
		        }
				$_SESSION['std']=true;
			    if($value == $_SESSION['last_dir'])
			      $next2 = true;
		        
			  } // foreach
			  if($next2 == true)
			  {
			    $search->writeScanned();
			  }
			} // neue Dirs
			else
			{
			  $search->writeScanned();
			} // keine neuen dirs
		  } // normale Dirs fertig
		} // kein Ordner gewünscht
		else
		{
		  $search->writeScanned();
		} // nur ein Ordner
		#die("Baustelle in Zeile 49");
		forward('index.php?page=searching_do_indexing_faq&frame=iframe', 0,'self');
		#forward('index.php?page=searching_index', 0,'top');
	}
	$tpl_content->addvar("DIR", $_SESSION['last_dir']);


?>
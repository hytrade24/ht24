<?php
/* ###VERSIONSBLOCKINLCUDE### */

 require_once 'inc.app.php';
 require_once 'sys/lib.kernel.php';
 require_once 'cache/lang.php';
 include "inc.all.php";
 
 $db = new ebiz_db($db_name, $db_host, $db_user, $db_pass);
 unset($db_user); unset($db_pass);
 
 list($s_lang, $langval) = get_language();
 
 // Alte Suchanfragen l�schen
 $db->querynow("delete from searchstring where LIFETIME < now()");
 
 // skin & Tpl
 $tpl_main = new FrameTemplate('skin/'.$s_lang.'/index-suche','main');

 $tpl_content = new Template("tpl/".$s_lang."/search_wait.htm");
  
 // Suchstring basteln 
 $str = trim($_REQUEST['SEARCH']);
 
 $hack = explode(" ", $str);
 $wordcount = count($hack);
 
 if($wordcount > 0)
 {
   $query="";
   $in=array();
   for($i=0; $i<$wordcount; $i++)
   {
	 $in[] = "'".mysql_escape_string($hack[$i])."'";
   }
   
   $in_str = implode(",", $in);
   
   $res = $db->querynow("select ID_WORDS from searchdb_words_".$s_lang."
       where wort in (".$in_str.")");
   if(!$res['rsrc'])
     die(ht(dump($res)));
   $in=array();
   while($row = mysql_fetch_assoc($res['rsrc']))
     $in[] = $row['ID_WORDS'];
   if(!empty($in))
   {
     $query = "select count(*),FK_ID,S_TABLE,sum(SCORE) as REL,`DIR`, `FILE`,
	   concat(FK_ID,`FILE`) as UN_KEY
               from searchdb_index_".$s_lang." 
		      where FK_WORDS in (".implode(",",$in).") 
		      and ( FK_ID > 0 OR `DIR`> '')
			  group by UN_KEY";
     if(($wordcount = count($in)) > 1)
	 {
	   if(!$_REQUEST['FIND'] == 'ALL')
	     $query .="\nhaving count(*)  >=".$wordcount;
	 }	
	 $query .= "\norder by REL DESC";
   }
   

   if(!empty($query))
   {
     
	 $ins = $db->querynow("insert into searchstring set
	    QUERY='".mysql_escape_string($query)."',
		S_LANG='".$s_lang."',
		C_ROWS=0, S_STRING='".mysql_escape_string($_REQUEST['SEARCH'])."',
		LIFETIME=date_add(now(), interval 2 hour)");
     if(!$ins['rsrc'])
	   die(ht(dump($ins)));
     $tpl_main->addvar("S_ID", $ins['int_result']);
     $tpl_content->addvar("S_ID", $ins['int_result']);
   }   
 }
 
 /* W�RGARROUND */
 $tpl_content->addvar("forward", "suche");
 $tpl_main->addvar("forward", "suche");
 
 $tpl_main->addvar("content", $tpl_content);
 
 echo $tpl_main->process();

?>

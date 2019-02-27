<?php
/* ###VERSIONSBLOCKINLCUDE### */



 function news_adv($ar)
 {
   global $s_lang;
   //echo ht(dump($ar));
   if(count($ar) < 1)
     return "Function news_adv failed, cause of missing parameter 1";
   list($num,$text,$bild) = $ar;
   if(!is_numeric($num) || 0 == $num)
     return "Function news_adv falied´! First parameter must be numeric!";
   global $nar_systemsettings;

   $text = (int)$text;
   $bild = (int)$bild;
   $cache_name = $s_lang.".news_adv.".$num.".".$text.".".$bild.".htm";
   $time = time();
   $f_time = false; @filemtime("cache/".$cache_name);
   if(!$f_time || ($time - $f_time) > $nar_systemsettings['CACHE']['LIFETIME_NEWS'])
     $code = @write_tpl($cache_name,$num,$text,$bild);
   else
     $code = @file_get_contents("cache/".$cache_name);
   if(!$code)
     $code = "Fatal error! Cache file not found/not writable [Function news_adv]";
   return $code;
 }

 function write_tpl($cache_name,$num,$text,$bild)
 {
#   echo $cache_name." ".$num."<hr />";
   global $db,$s_lang;
   $tpl = new Template("module/tpl/".$s_lang."/area_news_adv.htm");
   $ar = $db->fetch_table($db->lang_select("news","*")."
	 where OK=3 and V1 > ''
      order by B_TOP DESC, STAMP DESC
	  LIMIT 0,".$num);
//echo ht(dump($GLOBALS['lastresult']));
   $tmp=array();
#echo ht(dump($ar));
   $c_text= $c_bild = 0;
   for($i=0; $i<count($ar); $i++)
   {
     $ar[$i]['path'] = $db->fetch_atom("select n.IDENT from modul2nav m
	   left join nav n on m.FK_NAV = n.ID_NAV
	   where m.FK = ".$ar[$i]['FK_KAT']." and m.S_MODUL='news_adv'");
	 if(empty($ar[$i]['path']))
	   $ar[$i]['path']="news";
	 $ar[$i]['path'] = $ar[$i]['path']."/".$ar[$i]['path'];
//echo $c_text ." :: ".$text." ID: ".$ar[$i]['ID_NEWS']." ".$ar[$i]['V1']."<br />";
	 if($c_text >= $text)
	 {
	   unset($ar[$i]['V2']);
	 }
	 if($c_bild <= $bild)
	 {
	   if(!empty($ar['IMG']))
	     $ar['BILD']=1;
	 }
	 $tpl_tmp = new Template("module/tpl/".$s_lang."/area_news_adv.row.htm");
	 $tpl_tmp->addvars($ar[$i]);
	 $tmp[] = $tpl_tmp;
	 $c_text++;
	 $c_bild++;
   }
   $tpl->addvar("liste", $tmp);
   $fp = @fopen("cache/".$cache_name, "w");
   $fw = @fwrite($fp, $code = $tpl->process());
   @fclose($fp);
   @chmod("cache/".$cache_name, 0777);
   return $code;
 }

?>
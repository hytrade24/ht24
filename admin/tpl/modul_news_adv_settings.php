<?php
/* ###VERSIONSBLOCKINLCUDE### */


 #$tpl_content->table = 'user';
 if (count($_POST))
 {
	 $ar_ini = $ini = array();
	 
	 $ar_ini[] = "\t'comment' => '".$_POST['comment']."'";
	 $ar_ini[] = "\t'pages' => '".$_POST['pages']."'";
     $ini[] = "<?php\n\$ar_modul_option = array(\n";
     $ini[] = implode(",\n", $ar_ini);
     $ini[] = "\n);\n?>";
     
     $fp = @fopen("../module/news_adv/ini.php", "w");
     if(!$fp)
       die("Could not open ini File");
     $write = @fwrite($fp, implode($ini));
     if(!$write)
       die("Could not write ini File");	
     @fclose($fp);
     $tpl_content->addvar("comment", $_POST['comment']);
	 
     $db->querynow ("update modul set B_VIS=".$_POST['B_VIS']." where IDENT='news_adv'");
     /* forward('index.php?nav='. $nav, 2); */
 }

   if(filesize("../module/news_adv/ini.php") > 0)
   {
     include "../module/news_adv/ini.php";
     $tpl_content->addvars($ar_modul_option);
   }


 $B_VIS = $db->fetch_atom("select B_VIS from modul where IDENT='news_adv'");
 $tpl_content->addvar('B_VIS',$B_VIS);
?>
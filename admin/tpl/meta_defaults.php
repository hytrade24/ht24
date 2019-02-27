<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if(count($_POST))
 {
   $fp=fopen($file_name = $ab_path."cache/meta_def_".$s_lang.".txt", 'w');
   fwrite($fp, $_POST['META']);
   fclose($fp);
   @chmod($file_name, 0777);
   $tpl_content->addvar("ok", 1);

     if ($_POST['navpages']==1)
     {
        eventlog("info", 'Metatags für Webseiten neu geschrieben"');
        $db->querynow("update string set T1 ='".$_POST['META']."' where S_TABLE = 'nav' and BF_LANG=".$langval);
     }

 } // post

 $meta = @file_get_contents($ab_path."cache/meta_def_".$s_lang.".txt");
 $tpl_content->addvar("META", $meta);

?>

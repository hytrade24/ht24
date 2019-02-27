<?php
/* ###VERSIONSBLOCKINLCUDE### */



 include "sys/lib.baum.php";
 
 $level = false;
 $table = 'script';
 
 if($_REQUEST['table'] == 'tutorial')
   $table = 'tutorial';
 elseif($_REQUEST['table'] == 'script')
   $table = 'script';
 elseif($_REQUEST['table'] == 'job')
   $table = 'job';	 
   
 function levelcheck(&$row, $i)
 {
   global $level;
   if($level === false)
     $level = $row['level'];
   if($level != $row['level'])
     $row['einruecken'] = 1;
 } // levelcheck
 
 $baum = new baum($table);
 $baum->read((int)$_REQUEST['ID_KAT']);
 $tpl_content->addlist("liste", $baum->ar_baum, "tpl/".$s_lang."/katselector.row.htm");
 
 #echo ht(dump($baum->ar_baum));
 
 unset($baum);
 $baum = new baum($table);
 
 $baum->readPath((int)$_REQUEST['ID_KAT']); 
 $tpl_content->addlist("ariane", array_reverse($baum->ar_path_all), "tpl/".$s_lang."/katselector.ariane.htm");

?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */


 
 $user_name = sqlString($_REQUEST['USER']);
 
 $ar = $db->fetch1("select * , TIMESTAMPDIFF(YEAR,GEBDAT,CURDATE()) as age from `user` where `NAME`='".$user_name."'");
 if(!empty($ar))
 {
   $in = array();
   $res = $db->querynow("select * from role2user where FK_USER=".$ar['ID_USER']);
   while($row = mysql_fetch_assoc($res['rsrc']))
     $in[] = $row['FK_ROLE'];
   if(!empty($in))
     $ar_roles = $db->fetch_table("select * from role where ID_ROLE IN(".implode(",", $in).")");
   
   foreach($ar as $key => $value)
     $tpl_content->addvar($key, $value);
   
   if(!empty($ar_roles))
     $tpl_content->addlist("liste", $ar_roles, "tpl/de/usershortinfo.row.htm"); 
 }
 else
   $tpl_content->addvar("err", $user_name." konnte nicht gefunden werden!");

?>

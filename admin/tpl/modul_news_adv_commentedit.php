<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $tpl_content->addvar("ID_NEWS", $_REQUEST['ID_NEWS']);

 if(count($_POST))
 {
   if(!isset($_POST['DELETE']))
   {     
	 $up = $db->querynow("update comment set BODY='".mysql_escape_string($_POST['BODY'])."',
	    SUBJECT='".mysql_escape_string($_POST['SUBJECT'])."'
	   where ID_COMMENT=".$_REQUEST['ID_COMMENT']);
     if(!$up['rsrc'])
	   die(ht(dump($up)));
	 else
	   $tpl_content->addvar("ok", 1);  
   }
   else
   {
     $com = $db->fetch1("select c.PARENT, ct.FK 
	   from comment c
	    left join comment_thread ct on c.FK_COMMENT_THREAD=ct.ID_COMMENT_THREAD
	   where ID_COMMENT=".$_REQUEST['ID_COMMENT']);
	 $up = $db->querynow("update comment set PARENT=".$com['PARENT']." 
	      where PARENT=".$_REQUEST['ID_COMMENT']);
     if(!$up['rsrc'])
	   die(ht(dump($up)));
     else
	   $tpl_content->addvar("deleted", 1);
     
	 $up = $db->querynow("update news set PCOUNT=PCOUNT-1 where ID_NEWS=".$com['FK']);
     if(!$up['rsrc'])
	   die(ht(dump($up)));
     $db->delete("comment", $_REQUEST['ID_COMMENT']);
	 if($com['PARENT'] == 0)
	 {
	   $db->delete("comment_thread", $com['FK']);
	   $db->querynow("update news set TCOUNT=TCOUNT-1 where ID_NEWS=".$_REQUEST['ID_NEWS']);
     }
   }
 }
 
 if(!$_REQUEST['DELETE'])
   $ar_com = $db->fetch1("select SUBJECT, BODY, ID_COMMENT, STAMP, u.NAME as USER   
     from comment c
      left join user u on c.FK_USER=u.ID_USER
     where ID_COMMENT=".$_REQUEST['ID_COMMENT']); 
 else
   $ar_com = array();
 $tpl_content->addvars($ar_com);

?>

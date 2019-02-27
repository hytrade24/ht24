<?php
/* ###VERSIONSBLOCKINLCUDE### */



  $comment = $db->fetch1('select * from comment where ID_COMMENT='.$_REQUEST['ID_COMMENT'].'');

  $tpl_content->addvars($comment);
  
  if(count($_POST))
  {
    if($_POST['STAMP']=="")
	  $err[] = "Kein Datum angegeben";
	
	if($_POST['SUBJECT']=="")
	  $err[] = "Kein Thema angegeben";
	
	if($_POST['BODY']=="")
	  $err[] = "Kein Text angegeben";
    
	if(!count($err))
	{
	  $db->querynow('update comment set STAMP='.$_POST['STAMP'].',SUBJECT='.$_POST['SUBJECT'].',BODY='.$_POST['BODY'].' where ID_COMMENT='.$_POST['ID_COMMENT'].'');
	  $tpl_content->addvar('ok', 1);
	  forward('index.php?page=modul_news_adv_comments_edit&ID_COMMENTS='.$_GET['ID_COMMENT'].'&FK_NEWS='.$_REQUEST['FK_NEWS']);
	}
	else
	  $tpl_content->addvar('err', implode('<br />', $err));
	  forward('index.php?page=modul_news_adv_comments_edit&ID_COMMENTS='.$_GET['ID_COMMENT'].'&FK_NEWS='.$_REQUEST['FK_NEWS']);
  }

?>
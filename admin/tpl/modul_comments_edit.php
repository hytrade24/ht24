<?php
/* ###VERSIONSBLOCKINLCUDE### */




if(!empty($_REQUEST['modul']))
	$modul = $_REQUEST['modul'];

if ( !empty($_REQUEST['id']) )
	$id = $_REQUEST['id'];
elseif ( !empty($_REQUEST['ID_COMMENT']) )
	$id = $_REQUEST['ID_COMMENT'];
else
	$id = 0;

require_once($ab_path."admin/sys/lib.comment.php");
$comm = new comment( $modul, 0, $id);

 if(count($_POST))
 {
   if(!isset($_POST['DELETE'])) {
		if ( $comm->updateComment( $_POST['SUBJECT'], $_POST['BODY'] ) )
			$tpl_content->addvar("ok", 1); 
	}
   else
		if ( $comm->deleteComment() )
			$tpl_content->addvar("deleted", 1);
 }
 
 if(!$_REQUEST['DELETE'])
 	if ( $thisComment = $comm->getSingleComment() )
		$tpl_content->addvars($thisComment);
		
//{subtpl(tpl/de/modul_news_adv_kommentaransicht.htm,*)}

?>

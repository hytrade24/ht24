<?php
/* ###VERSIONSBLOCKINLCUDE### */



### Page Counter Init.
$perpage=20;
if(!isset($_REQUEST['npage']) || empty($_REQUEST['npage']))
  $_REQUEST['npage']=1;
$start = ($_REQUEST['npage']-1)*$perpage;

### Einschräken auf user?
$where = array();
$usr = false;

if(!empty($_REQUEST['modul']))
	$modul = $_REQUEST['modul'];

if(!empty($_REQUEST['id']))
	$id = $_REQUEST['id'];
else
	$id = 0;

require_once($ab_path."admin/sys/lib.comment.php");
$comm = new comment( $modul, 0, $id);


if(!empty($_REQUEST['action'])) 
{
	if($_REQUEST['action'] == "delete")
		$comm->deleteComment();
	if($_REQUEST['action'] == "unreport")
		$comm->unreportComment();
}
if(!empty($_REQUEST['modul']))
  $where[] = " ct.S_TABLE='".$_REQUEST['modul']."'";

if(!empty($_REQUEST['FK_AUTOR']))
{
  $usr = $db->fetch_atom("select NAME from user where ID_USER=".$_REQUEST['FK_AUTOR']);
  $tpl_content->addvar("NAME_", $usr);
}
if(!$usr && !empty($_REQUEST['NAME_']))
{
  $usr = $db->fetch_atom("select ID_USER from user where NAME='".$_REQUEST['NAME_']."'");
  $tpl_content->addvar("FK_AUTOR", $usr);
  $_REQUEST['FK_AUTOR'] = $usr;
}

$tpl_content->addvars($_REQUEST);

if($usr)
{
  $where[] = " c.FK_USER=".$_REQUEST['FK_AUTOR'];
}

if(count($where))
  $where = " where ".implode(" and ", $where); 
else
  $where = NULL;
### Kommentare
 
 $all = $db->fetch_atom("select count(*) from comment c left join comment_thread ct on c.FK_COMMENT_THREAD = ct.ID_COMMENT_THREAD ".$where);
 
 $ar_comments = $comm->getLastVotes( $start, $perpage, $where );
 
 ### Falls ein Kommentar im popup gelöscht wurde.
 ### ( nach dem löschen wird diese Seite neu geladen )
 #if(empty($ar_comments))
   #forward("index.php?page=".$tpl_content->vars['curpage']."&FK_AUTOR=".$_REQUEST['FK_AUTOR']."&npage=".($_REQUEST['npage']-1));

 $tpl_content->addlist('liste', $ar_comments, 
         $ab_path.'admin/tpl/de/modul_comments_overview.row.htm');
	
 $tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&FK_AUTOR=".$_REQUEST['FK_AUTOR']."&NAME_=".$_REQUEST['NAME_']."&npage=", $perpage));

?>
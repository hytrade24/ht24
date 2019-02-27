<?php
/* ###VERSIONSBLOCKINLCUDE### */


	if ( !empty( $_REQUEST["fk"] ) )
	{
		$id = $_REQUEST["fk"];
		$tpl_content->addvar("INCLUDE", 0);
	}
	else 
	{
		$tpl_content->addvar("INCLUDE", 1);
		$id = $contentId;
	}

	if ( !empty( $_REQUEST["action"] ) )
		$s_mode = $_REQUEST["action"];
	else 
		$s_mode = "list";	
	
	if ( !empty( $_REQUEST["idc"] ) )
		$id_comment = $_REQUEST["idc"];
	else
		$id_comment = 0;
		
	if ( !empty( $_REQUEST["table"] ) )
		$s_table = $_REQUEST["table"];
		

  // ggf. ID des Contents aus DB holen
	if ($id_comment && !$id)
		$id = (int)$db->fetch_atom("select FK from comment_thread, comment
    		where ID_COMMENT_THREAD=FK_COMMENT_THREAD and ID_COMMENT=". $id_comment);

	require_once($ab_path."admin/sys/lib.comment.php");
	$comm = new comment( $s_table, $id, $id_comment);
	if ( is_array( $comm->getContent() ) )
		$tpl_content->addvars( $comm->getContent() );


	if (count($_POST))
	{
		if ($_POST['submit']) 
		{
			if ( ! ($id_comment = $comm->insertComment( $_POST )) ) 
			{
				//$tpl_content->addvar("err", implode("<br />", $comm->err));
				$tpl_content->addvars( $_POST );
				$tpl_content->addvar("post", true);
			}
//			$tpl_content->addvar("err", $comm->id_comment);
			
		}
		else
		{
			if (count($comm->err)) {}
//				$tpl_content->addvar('err', implode('<br />', $comm->err));
			else {
//				$tpl_content->addvar('post', true);
				$tpl_content->addvar('preview', true);
			}
			$tpl_content->addvars($_POST);
			$tpl_content->addvar('post', true);
		}
	}
	
				/*
				 echo "Mode: ".$s_mode."<br>";
				 echo "ID des Buches: ".$id."<br>";
				 echo "ID_Comment: ".$id_comment."<br>";
				 echo "__[__".$comm->id_comment."__]__<br>";
				 echo "S_Table: ".$s_table."<br>";
				*/
	
  if ($s_mode == 'read')
  {
    if ($item = $db->fetch1('select * from comment where ID_COMMENT='. $id_comment))
    {
      $tpl_content->addvar('read', true);
      $tpl_content->addvar('post', true);
      $tpl_content->addvars($item, 'o_');
      if (!count($_POST))
      {
        $tpl_content->addvar('SUBJECT', 'Re: '. $item['SUBJECT']);
        $tpl_content->addvar('BODY', $db->fetch_atom("select NAME from `user` where ID_USER=". $item['FK_USER']). ' schrieb: 
-------------------------------------------------------
> '. preg_replace('/(\r|\n|\r\n|\n\r)/', '$1> ', $item['BODY']));
      }
    }
  }
  elseif ( $s_mode == 'add')
		$tpl_content->addvar('post', 1);

if ( ($s_mode != 'add') && empty( $comm->err ) )
{
		if ($id_comment) {
			$ar_comments = $db->fetch_table("select t.* from comment_thread t, comment c
					where FK_COMMENT_THREAD=ID_COMMENT_THREAD and ID_COMMENT=". $comm->id_comment);
/*			$query = "select t.* from comment_thread t, comment c
					where FK_COMMENT_THREAD=ID_COMMENT_THREAD and ID_COMMENT=". $comm->id_comment;*/
		}
		else
		{
//			$tpl_content->addvar('showing_all', true);
			$ar_comments = $db->fetch_table("select t.*, max(c.STAMP) as TSTAMP from comment_thread t
					left join comment c on FK_COMMENT_THREAD=ID_COMMENT_THREAD
					where S_TABLE='".$comm->type."' and FK=".$comm->fk. "
					group by ID_COMMENT_THREAD
					order by TSTAMP desc");
/*			$query = "select t.*, max(c.STAMP) as TSTAMP from comment_thread t
					left join comment c on FK_COMMENT_THREAD=ID_COMMENT_THREAD
					where S_TABLE='".$comm->type."' and FK=".$comm->fk. "
					group by ID_COMMENT_THREAD
					order by TSTAMP desc";*/
		}
}
else 
{
 	 $ar_comments = array ();
}
#		$tpl_content->addvar("query", $query);

  $ar_liste = array ();
  require_once($ab_path.'sys/lib.tree.php');
  $ar_data = array ();
  foreach($ar_comments as $i=>$row)
  {
	$ar_data = array ();
    tree_fetch($ar_data, 'comment', 'FK_COMMENT_THREAD='. $row['ID_COMMENT_THREAD'], 'STAMP');
	$ar_liste[] = tree_show($ar_data, 0, 0,$ab_path.'admin/tpl/de/comment.row.htm', $ab_path.'comment,read,{ID_COMMENT}.htm', $id_comment);
  }
#	echo ht(dump($row));
  $tpl_content->addvar('liste', $ar_liste);
  
require_once($ab_path."module/news_adv/ini.php");
if($ar_modul_option['comment']) 
{
//	if ( empty( $comm->err ) )
		$tpl_content->addvar("comment", 1);
}
else
  $tpl_content->addvar("comment", 0);

//  $tpl_content->addvar('root', $ab_path);
  $tpl_content->addvar( "linkback", $comm->createBackLink() );
  $tpl_content->addvar( "ID_COMMENT", $comm->id_comment );
  $tpl_content->addvar( "id", $comm->fk );
  if ( $comm->err ) {
	  $tpl_content->addvar("err", implode("<br />", $comm->err));
	  
  }
?>

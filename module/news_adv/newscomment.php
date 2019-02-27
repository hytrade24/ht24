<?php
/* ###VERSIONSBLOCKINLCUDE### */



// TPL Init 
$tpl_mode = new Template("module/tpl/".$s_lang."/newscomment.htm");

  // id = ID_NEWS
  $id = (int)$ar_params[1];
  $tpl_mode->addvar('mode', $s_mode = trim($ar_params[2]));
  $id_comment = (int)$ar_params[4];

  // ggf. ID_NEWS aus DB holen
  if ($id_comment && !$id)
    $id = (int)$db->fetch_atom("select FK from comment_thread, comment
      where ID_COMMENT_THREAD=FK_COMMENT_THREAD and ID_COMMENT=". $id_comment);

  
  if (count($_POST))
  {
    foreach($_POST as $k=>$v)
      $_POST[$k] = trim($v);
    $err = array ();
    if (!$_POST['SUBJECT'])
      $err[] = 'Bitte geben Sie ein Thema an.';
    if (!$_POST['BODY'])
      $err[] = 'Ein leerer Beitrag macht wenig Sinn.';
    if (!count($err) && $_POST['submit'])
    {
      $_POST['STAMP'] = date('Y-m-d H:i:s');
      $_POST['FK_USER'] = (int)$uid;
      $_POST['FK_COMMENT_THREAD'] = ($id_comment
        ? $tid = $db->fetch_atom("select FK_COMMENT_THREAD from comment where ID_COMMENT=". $id_comment)
        : $db->update('comment_thread', array ('S_TABLE'=>'news','FK'=>$id, 'FK_LANG'=>$langval))
      );
    $update = "PCOUNT=PCOUNT+1,LAST_COMMENT=NOW()";
    if(!$tid)
      $update .= ",TCOUNT=TCOUNT+1";
      $db->querynow("update news set ".$update." where ID_NEWS=".$id);
    $id_comment = $db->update('comment', $_POST);
      forward($tpl_content->vars['curpage'].','. $id. ',read,newscomment,'. $id_comment. '.htm');
    }
    else
    {
      if (count($err))
        $tpl_mode->addvar('err', implode('<br />', $err));
      else
        $tpl_mode->addvar('preview', true);
      $tpl_mode->addvars($_POST);
#      $tpl_mode->addvar('post', true);
    }
  }
  if ('read'==$s_mode)
  {
    if ($item = $db->fetch1('select * from comment where ID_COMMENT='. $id_comment))
    {
      $tpl_mode->addvar('read', true);
      $tpl_mode->addvar('post', true);
      $tpl_mode->addvars($item, 'o_');
      if (!count($_POST))
      {
        $tpl_mode->addvar('SUBJECT', 'Re: '. $item['SUBJECT']);
        $tpl_mode->addvar('BODY',
          $db->fetch_atom("select NAME from `user` where ID_USER=". $item['FK_USER']). ' schrieb:
-------------------------------------------------------
> '. preg_replace('/(\r|\n|\r\n|\n\r)/', '$1> ', $item['BODY'])
        );
      }
    }
  }
  elseif ('add'==$s_mode)
    $tpl_mode->addvar('post', 1);

if ('add'!=$s_mode)
{
  if ($id_comment)
    $ar_threads = $db->fetch_table("select t.*
      from comment_thread t, comment c
      where FK_COMMENT_THREAD=ID_COMMENT_THREAD and ID_COMMENT=". $id_comment);
  else
  {
    $tpl_mode->addvar('showing_all', true);
    $ar_threads = $db->fetch_table("select t.*, max(c.STAMP) as TSTAMP from comment_thread t
        left join comment c on FK_COMMENT_THREAD=ID_COMMENT_THREAD
      where S_TABLE='news' and FK=". $id. "
      group by ID_COMMENT_THREAD
      order by TSTAMP desc");
  }
}
else
  $ar_threads = array ();
#echo listtab($ar_threads);

	//15.12.06 - jan
  $ar_tmp = $db->fetch1($db->lang_select('news'). ' where ID_NEWS='. $id);
	if (is_array($ar_tmp))
		$tpl_mode->addvars($ar_tmp, 'n_');
	else
		$tpl_mode->addvar($ar_tmp, 'n_');

  $ar_liste = array ();
  require_once 'sys/lib.tree.php';
  $ar_data = array ();
  foreach($ar_threads as $i=>$row)
  {
#echo "<b>$i</b>", ht(dump($row));
$ar_data = array ();
    tree_fetch($ar_data, 'comment', 'FK_COMMENT_THREAD='. $row['ID_COMMENT_THREAD'], 'STAMP');
#echo "<b>$i</b>", ht(dump($lastresult)), ht(dump($row)), count(($ar_data)), ht(dump($ar_data)), '<hr />';
#echo ht(dump($ar_data));
	$ar_liste[] = tree_show($ar_data, 0, 0,
      'module/tpl/de/newscomment.row.htm', 'comment,'. $id. ',read,{ID_COMMENT}.htm',
      $id_comment);
  
  #echo $id_comment."<hr />";
  }
#echo '<hr>', ht(dump($ar_liste));
  $tpl_mode->addvar('liste', $ar_liste);
  $tpl_modul->addvar("MODECODE", $tpl_mode);
  
include "ini.php";
if($ar_modul_option['comment'])
  $tpl_modul->addvar("comment", 1);
else
  $tpl_modul->addvar("comment", 0);
?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */



### Page Counter Init.
$perpage=15;
if(!isset($_REQUEST['npage']) || empty($_REQUEST['npage']))
  $_REQUEST['npage']=1;
$start = ($_REQUEST['npage']-1)*$perpage;

### Einschräken auf user?
$where = array();
$usr = false;


if(!empty($_REQUEST['FK_AUTOR']))
{
  $usr = $db->fetch_atom("select NAME from user where ID_USER=".$_REQUEST['FK_AUTOR']);
  $tpl_content->addvar("NAME_", $usr);
}
if(!$usr && !empty($_REQUEST['NAME_']))
{
  $usr = $db->fetch_atom("select ID_USER from user where NAME='".$_REQUEST['NAME_']."'");
  $tpl_content->addvar("FK_AUTOR", $usr);
  $tpl_content->addvar("err", "User nicht gefunden");
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
 
 $all = $db->fetch_atom("select count(*) from comment c ".$where);
 
 $ar_comments = $db->fetch_table("select concat(c.SUBJECT, '   ',left(c.BODY, 200)) as VORSCHAU,
     c.STAMP, c.ID_COMMENT, c.FK_USER, u.NAME as USER,s.V1 as ARTIKEL, count(cc.ID_COMMENT) as C_COM,
	 ct.FK as FK_NEWS 
	from comment c
	 left join user u on c.FK_USER=u.ID_USER
	 left join comment_thread ct on c.FK_COMMENT_THREAD=ct.ID_COMMENT_THREAD 
	 left join news t on ct.FK=t.ID_NEWS and ct.S_TABLE='news'
     left join string_c s on s.S_TABLE='news' and s.FK=t.ID_NEWS and s.BF_LANG=if(t.BF_LANG_C & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))	 
	 left join comment cc on u.ID_USER=cc.FK_USER
	".$where."
	group by c.ID_COMMENT
	order by STAMP desc 
	limit ".$start.",".$perpage);

  #echo ht(dump($comments));
 
 ### Falls ein Kommentar im popup gelöscht wurde.
 ### ( nach dem löschen wird diese Seite neu geladen )
 #if(empty($ar_comments))
   #forward("index.php?page=".$tpl_content->vars['curpage']."&FK_AUTOR=".$_REQUEST['FK_AUTOR']."&npage=".($_REQUEST['npage']-1));

 $tpl_content->addlist('liste', $ar_comments, 
    'tpl/de/modul_news_adv_comments.row.htm');
	
 $tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&FK_AUTOR=".$_REQUEST['FK_AUTOR']."&NAME_=".$_REQUEST['NAME_']."&npage=", $perpage));

?>
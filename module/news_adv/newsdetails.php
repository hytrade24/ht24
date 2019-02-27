<?php
/* ###VERSIONSBLOCKINLCUDE### */


  $id = (int)$ar_params[1];

  if (!$id)
  {
  $where = ' order by ID_NEWS DESC limit 1';
  }
  else
  {
    $where = ' where ID_NEWS='. $id;
  }

  $tpl_content->addvars($item = $db->fetch1($db->lang_select('news','*,NAME'). ' left join user on FK_USER=ID_USER '.$where));

  // Kommentare
  $tpl_content->addlist('kommentare', $cc = $db->fetch_table("select c.* from comment c, comment_thread t
    where t.S_TABLE='news' and t.FK=". $id. " and t.ID_COMMENT_THREAD=c.FK_COMMENT_THREAD
    order by STAMP desc limit 5"), 'tpl/'. $s_lang. '/newsdetails.comment.htm');
#echo ht(dumP($cc));

  // links
#  $tpl_content->addlist('links', ???, 'tpl/'. $s_lang. '/news_links.htm');

  // verwandte anzeige (keywords)
  $ar_keys = $db->fetch_nar("select FK_NEWS_KEY,FK_NEWS_KEY from news2key
    where FK_NEWS=". $id);
  $s_keys = implode(', ', $ar_keys);
  $ar_news = $db->fetch_table($s_keys
    ? $db->lang_select('news', '*, count(*) as kwcount'). '
      left join news_key k on k.FK_NEWS=ID_NEWS
        where k.FK_NEWS_KEY in ('. $s_keys. ')
      group by ID_NEWS
      order by kwcount desc, rand() limit 5'
    : $db->lang_select('news'). '
      order by rand() limit 5'
  );

  $tpl_content->addlist('verwandt', $ar_news, 'tpl/'. $s_lang. '/newsdetails.ref.htm');

  // mehr zum thema (kategorie)
  if (!($fk_kat = (int)$item['FK_KAT']))
    $fk_kat = $db->fetch_atom("select ID_KAT from kat where ROOT=2");
  if ($kat = $db->fetch1($db->lang_select('kat'). ' where ID_KAT='. $fk_kat))
    $tpl_content->addvars($kat, 'KAT_');
  // mehr zum thema (keywords)
/*
  if ($$s_keys)
  {
    $ar_keys = $db->fetch_table($db->lang_select('news_key'). '
      where ID_NEWS_KEY in ('. $$s_keys. ') order by ID_NEWS_KEY');
    $tpl_content->addlist('keywords', $ar_keys, 'tpl/'. $s_lang. '/newsdetails.key.htm');
  }
*/
?>
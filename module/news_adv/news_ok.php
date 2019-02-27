<?php
/* ###VERSIONSBLOCKINLCUDE### */

/*
  //echo $_SERVER['QUERY_STRING'];
  if ($id = (int)$ar_params[1])
  {
    $datum = $db->fetch_atom("select STAMP from news where OK=3 and ID_NEWS=". $id);
  $data = $db->fetch_table($db->lang_select('news', '*,NAME')." left join user on FK_USER=ID_USER
    where OK=3 and (STAMP<'". $datum. "' or (STAMP='". $datum. "' and ID_NEWS<=". $id. "))
    order by STAMP desc ,ID_NEWS DESC
    limit 5");
  }
  else
  {
    $data = $db->fetch_table($db->lang_select('news', '*,NAME').'
    left join user on FK_USER=ID_USER
    where OK=3
    order by STAMP desc ,ID_NEWS DESC
    limit 5');
    //print_r($data0);
  }

    $data0 = array_shift($data);
  $tpl_content->addlist('liste', $data, 'tpl/'.$s_lang.'/news.row.htm');
  $tpl_content->addvars($data0);
  */

  //echo $_SERVER['QUERY_STRING'];
  if ($id = (int)$ar_params[1])
  {
    $datum = $db->fetch_atom("select STAMP from news where OK=3 and ID_NEWS=". $id);
  $data = $db->fetch_table($db->lang_select('news', '*,NAME')." left join user on FK_USER=ID_USER
    where OK=3 and (STAMP<'". $datum. "' or (STAMP='". $datum. "' and ID_NEWS<=". $id. "))
    order by STAMP desc ,ID_NEWS DESC
    limit 5");
  }
  else
  {
    $data = $db->fetch_table($db->lang_select('news', '*,NAME').'
    left join user on FK_USER=ID_USER
    where OK=3
    order by STAMP desc ,ID_NEWS DESC
    limit 5');
    //print_r($data0);
  }

  //$data0 = array_shift($data);
  $tpl_content->addlist('liste', $data, 'tpl/'.$s_lang.'/news.row.htm');
  //$tpl_content->addvars($data0);
?>
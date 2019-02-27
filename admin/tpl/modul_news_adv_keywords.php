<?php
/* ###VERSIONSBLOCKINLCUDE### */


  if (!($item = (($id = (int)$_REQUEST['ID_NEWS'])
    ? $db->fetch1($db->lang_select('news'). ' where ID_NEWS='. $id)
    : false
  )))
    forward('index.php?page=news_edit');

  $err = array ();

  $nar_data = $db->fetch_nar($db->lang_select('news_key', 'ID_NEWS_KEY,LABEL'). '
    left join news2key on FK_NEWS_KEY=ID_NEWS_KEY
    where FK_NEWS='. $id);
  $ar_liste = array ();
  $b_even = true;
  foreach($nar_data as $fk_key => $s_label)
  {
    $ar_liste[] = '<tr class="zeile'. (int)$b_even. '">
      <td><input type="checkbox" class="nob" name="rm[]" value="'. $fk_key. '" /></td>
      <td>'. stdHtmlentities($s_label). '</td>
    </tr>';
    $b_even = !$b_even;
  }
  $tpl_content->addvar('kliste', $ar_liste);
//die(ht(dump($_POST)));
  if ($_POST['rm'])
  {
    $lastresult = $db->querynow('delete from news2key
      where FK_NEWS='. $id. ' and FK_NEWS_KEY in ('. implode(', ', $_POST['rm']). ')');
    if ($tmp = $lastresult['str_error'])
      $err[] = 'Datenbank meldet: '. $tmp;
    else
	  forward("index.php?page=modul_news_adv_keywords&ID_NEWS=".$_REQUEST['ID_NEWS']);
	 //die(ht(dump($lastresult)));
  }
  if ($_POST['add'])
  {
    $db->querynow('insert into news2key (FK_NEWS, FK_NEWS_KEY)
      select '. $id. ', ID_NEWS_KEY from news_key
        where ID_NEWS_KEY in ('. implode(', ', $_POST['add']). ')');
    if ($tmp = $lastresult['str_error'])
      $err[] = 'Datenbank meldet: '. $tmp;
    else
	  forward("index.php?page=modul_news_adv_keywords&ID_NEWS=".$_REQUEST['ID_NEWS']);	  
  }

  if ($qry = trim($_POST['qry']))
  {
    
    $nar_data = $db->fetch_nar($db->lang_select('news_key', 'ID_NEWS_KEY,LABEL,FK_NEWS'). "
      left join news2key on FK_NEWS=ID_NEWS_KEY
      where s.V1 like '%". mysql_escape_string($qry). "%'
      group by ID_NEWS_KEY");
      
      }
    else {
        
        $nar_data = $db->fetch_nar($db->lang_select('news_key', 'ID_NEWS_KEY,LABEL,FK_NEWS'). "
      left join news2key on FK_NEWS=ID_NEWS_KEY 
      group by ID_NEWS_KEY");
    }
    //echo ht(dump($lastresult));
	$b_even = true;
    $ar_liste = array ();
    foreach($nar_data as $fk_key => $s_label)
    {
      $ar_liste[] = '<tr class="zeile'. (int)$b_even. '">
        <td><input type="checkbox" class="nob" name="add[]" value="'. $fk_key. '" /></td>
        <td style="width:100%;">'. stdHtmlentities($s_label). '</td>
      </tr>';
      $b_even = !$b_even;
    }
    $tpl_content->addvar('sliste', $ar_liste);
    $tpl_content->addvar('search', 1);
    if ($_POST['new'])
    {
      if(in_array ($qry, $nar_data))
        $err[] = "Keyword '". stdHtmlentities($qry). "' existiert bereits.";
      else
        $db->update('news_key', array ('V1'=>$qry));
    }
   

  $tpl_content->addvar('err', implode('<br />', $err));
  $tpl_content->addvars($item);
  $tpl_content->addvars($_REQUEST);
?>
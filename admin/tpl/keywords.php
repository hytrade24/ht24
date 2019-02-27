<?php
/* ###VERSIONSBLOCKINLCUDE### */


  $id = (int)$_REQUEST['ID_NEWS_KEY'];
  $c = strtolower($_REQUEST['c']);

  // post?
  if (count($_POST))
  {
    $err = array ();
    if (!$_POST['V1'])
      $err[] = 'leeres Keyword?';
    if (count($err))
      $tpl_content->addvars($_POST);
    else
    {
      $id = $db->update('news_key', $_POST);
      if ($c)
      {
        $s = $_POST['V1'];
        if (preg_match('/^[0-9]/', $s))
          $c = '#';
        elseif (preg_match('/^[a-z]/i', $s))
          $c = strtolower(substr($s,0,1));
        elseif ('*' != $c)
          $c = '~';
        $s = '&c='. $c;
      }
      else
        $s = '';
#echo 'index.php?page=keywords'. $s. '&ID_NEWS_KEY='. $id, '<br /><br />';
#die(ht(dump($lastresult)));
      forward('index.php?page=keywords'. $s. '&ID_NEWS_KEY='. $id);
    }
  }
  elseif ($id)
    // Keyword ausgewaehlt?
    $tpl_content->addvars($db->fetch1($db->lang_select('news_key'). ' where ID_NEWS_KEY='. $id));



  // Buchstaben
  if ($c)
  {
    if ('*'==$c) // %2a
      ;
    elseif ('#'==$c) // %23
      $ar_where[] = "s.V1 regexp '^[0-9]'";
    elseif ($c>='a' || $c<='z')
      $ar_where[] = "s.V1 like '$c%'";
    else
    {
      $c = '~'; // %7e
      $ar_where[] = "s.V1 regexp '^[^a-z0-9]'";
    }
  }
  else
    $c = '*';
  $tpl_content->addvar('c', $c_search = $c);

  function charref($c, $s_label)
  {
    return ($c==$GLOBALS['c_search']
      ? '<b>'. stdHtmlentities($s_label). '</b>'
      : '<a href="index.php?page=keywords&c='. rawurlencode($c). '">'. stdHtmlentities($s_label). '</a>'
    );
  }
  $ar = array ();
  for ($c='a'; strlen($c)==1; $c++)
    $ar[] = charref($c, strtoupper($c));
  $ar[] = charref('#', '0-9');
  $ar[] = charref('~', '!?%');
  $ar[] = charref('*', 'alle');
  $tpl_content->addvar('charrefs', implode(' | ', $ar));

  // auflisten
  if ($c_search)
  {
/**/
    $res = $db->querynow($db->lang_select('news_key', '*,count(FK_NEWS) as usecount'). '
      left join news2key on FK_NEWS_KEY=ID_NEWS_KEY'. (count($ar_where) ? '
      where '. implode(' and ', $ar_where) : ''). '
      group by ID_NEWS_KEY order by V1'
    );
    $ar = array ();
    $tpl_content->addvar('matchcount', $res['int_result']);
#echo ht(dump($res));
    $n_div = ceil($res['int_result']/6);
#echo dump($n_div);
    for ($i=0; $row = mysql_fetch_assoc($res['rsrc']); $i++)
{
#echo "<b>$i</b> - ", ($i%$n_div), ht(dump($row));
      $ar[$i%6][] = '<a href="index.php?page=keywords&ID_NEWS_KEY='. $row['ID_NEWS_KEY']
        . '">'. stdHtmlentities($row['V1']). ' ('. $row['usecount']. ')</a>';
}
/*/
    $nar = $db->fetch_nar($db->lang_select(news_key,
      "ID_NEWS_KEY, concat(s.V1, ' (', count(FK_NEWS), ')'), LABEL"). (count($ar_where) ? '
      where '. implode(' and ', $ar_where) : ''). '
      left join news2key on FK_NEWS_KEY=ID_NEWS_KEY'. '
      group by ID_NEWS_KEY order by V1'
    );
    $ar = array_chunk($nar, ceil(count($nar)/6));
#die(ht(dump($ar)));
/**/
    foreach($ar as $x=>$ar_col)
      $tpl_content->addvar('words'. $x, implode('<br />', $ar[$x]));
  }
?>
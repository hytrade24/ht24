<?php
/* ###VERSIONSBLOCKINLCUDE### */


  include ('tpl/trans_params.php'); // do, Sprache 0, Sprache 1
#echo ht(dump($_REQUEST));
  if ($s_ftable = $_REQUEST['tbl'])
  {
    $do = $_REQUEST['do'];
    // Suchergebnisse?
    if ('qry'!=$do)
      $s_fks = '';
    elseif (!($s_fks = $_SESSION['translate.matches.'. $s_ftable]))
      $s_fks = '0';

    if (false!==strpos($s_ftable, '.'))
    {
      list($s_ptable, $s_ftable) = explode('.', $s_ftable);
      $s_lang_field = 'BF_LANG'. $s_ptable;
      $s_ptable = 'string'. ($s_ptable ? '_'. $s_ptable : '');
    }
    else
    {
      $s_lang_field = $db->fetch_atom("show fields from `$s_ftable` like 'BF_LANG%'");
      $s_ptable = 'string'. strtolower(substr($s_lang_field, 7));
    }
    $s_pk = 'ID_'. strtoupper($s_ftable);

    $ar_data = array ();
    $lastresult = $db->querynow("select * from `$s_ftable`". ($s_fks ? "
      where $s_pk in ($s_fks)" : ''). "
      order by $s_pk"); #xxx todo: blaettern
    $res = $lastresult;
    $nar_head = $nar_langhead = array ();
#die(var_dump($s_ptable));
    $ar_str_fields = getstrfields($s_ptable, $s_ftable);

    for($i = 0; $row = mysql_fetch_assoc($res['rsrc']); $i++)
    {
      $ar_row = array ();
      foreach($row as $k=>$v) if (!preg_match('/^BF_LANG/', $k))
      {
        if (!$i) $nar_head[$k] = $k;
        $ar_row[$k] = val_convert($v);
      }

      if ($ar_str_fields && count($ar_str_fields))
      {
        $lastresult = $db->querynow("select ifnull(g.ABBR, p.BF_LANG), p.BF_LANG, p.V1, p.V2, p.T1
          from `$s_ptable` p, lang g
          where S_TABLE='$s_ftable' and FK=$row[$s_pk] and g.BITVAL=p.BF_LANG
          order by g.BITVAL desc");
        $v = array ();
        while (list($s_abbr, $bf, $v['V1'], $v['V2'], $v['T1']) = mysql_fetch_row($lastresult['rsrc']))
        {
          foreach($v as $k=>$vv) if (in_array ($k, $ar_str_fields))
          {
/**/
            $s_k = str_replace('T', 'Z', $k). str_pad((99999-$bf), 5, '0', STR_PAD_LEFT);
            $ar_row[$s_k] = val_convert($v[$k]);
            $nar_langhead[$s_k] = "$k ($s_abbr)";
/*/
            $s_k = "$k ($s_abbr)";
            $nar_head[$s_k] = $s_k;
            $ar_row[$s_k] = val_convert($v[$k]);
/**/
          }
        }
      }
      $ar_data[] = $ar_row;
    }
    ksort($nar_langhead);
    foreach($nar_langhead as $k=>$v)
      $nar_head[$k] = $v;
    $ar_table = array ('<table class="liste"><tr>
<th>Aktionen</th>
<th>', implode('</th>
<th>', $nar_head), '</th>
</tr>');
    foreach($ar_data as $i=>$row)
    {
      $ar_table[] = '<tr class="zeile'. !($i&1). '" id="z'. $i. '"
  onClick="popup(1024,600).location.href=document.getElementById(\'r'. $i. '\').href;">
<td><a onClick="popup(1024,600).location.href=this.href;return false;" id="r'. $i. '"
  href="index.php?frame=popup&page=trans_edit&tbl='. $s_ftable. '&id='. $row[$s_pk]. '">edit</a></td>';
      foreach($nar_head as $k=>$v) $ar_table[] = '
<td>'. $row[$k]. '</td>';
      $ar_table[] = '
</tr>';
    }
    $ar_table[] = '</table>';

    $tpl_content->addvar('listmode_'. $do, true);
    if ('qry'==$do)
      $tpl_content->addvar('qry', $_SESSION['translate.qry']);
    $tpl_content->addvar('result_table', $s_ftable);
    $tpl_content->addvar('results', $ar_table);
  }
?>
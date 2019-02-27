<?php
/* ###VERSIONSBLOCKINLCUDE### */


  $n_perpage = 25;

$nar_2tables = array (
  'attr_group'=>array (
    'LABEL'=>'Attributgruppe',
    'href'=>'attr_group_edit&ID_ATTR_GROUP='
  )
);

if ('rm'==$_REQUEST['do'])
{
  # todo: Attribut loeschen inkl. attr2group, attr_val_?
}

/**/
  $n_page = max(1, (int)$_REQUEST['browse']);
  $n_ofs = ($n_page-1)*$n_perpage;
  $tpl_content->addvar('browse', htm_browse(
    $db->fetch_atom('select count(*) from attr'),
    $n_page, 'index.php?page=attribs&browse=', $n_perpage
  ));
/*/
$n_ofs = (int)$_REQUEST['ofs'];
$n_count = $db->fetch_atom("select count(*) from attr");
list($s_limit, $ar_browse) = browse($n_count, $n_ofs, $n_limit=25, 5);
/**/

$nar_attr = array ();
function read_attr2($s_ftable)
{
  global $db, $nar_attr;
  $s_upper = strtoupper($s_ftable);
  $nar_fk2label = $db->fetch_nar($db->lang_select($s_ftable, 'ID_'. $s_upper. ', LABEL'));
#echo ht(dump($GLOBALS[lastresult]));
  $s_ft2 = 'attr2'. (preg_match('/^attr_/', $s_ftable) ? substr($s_ftable, 5) : $s_ftable);
  $res = $db->querynow('select distinct FK_ATTR,FK_'. $s_upper. ', B_MANDATORY
    from '. $s_ft2. '
    order by FK_ATTR');
#echo ht(dump($nar_fk2label));
  while (list($id_attr, $fk, $b_mandatory) = mysql_fetch_row($res['rsrc']))
#{echo "$id_attr -> $fk ($s_ftable)<br>";
    $nar_attr[$id_attr][$s_ftable][$fk] = array ($nar_fk2label[$fk], (int)$b_mandatory);
#}
}
foreach($nar_2tables as $s_ftable=>$tmp)
  read_attr2($s_ftable);
#echo ht(dump($nar_attr));

function print_attr2($i, &$row)
{
  global $nar_attr, $nar_2tables;
  $tmp = array ();
  if (is_array ($ar = &$nar_attr[$row['ID_ATTR']]))
    foreach ($ar as $s_ftable=>$nar_frow)
    {
      $tmp2 = array ();
      $tmp3 = $nar_2tables[$s_ftable];
      $s_href = $tmp3['href'];
      if ($s_href)
        foreach ($nar_frow as $fk=>$ar_tmp)
          $tmp2[] = '<a href="index.php?page='. $s_href. $fk. '">'
#            . ($ar_tmp[1] ? '<b>' : '')
            . stdHtmlentities($ar_tmp[0]). ($ar_tmp[1] ? '*' : ''). '</a>';
      else
        foreach ($nar_frow as $fk=>$s_label)
          $tmp2[] = $s_label;
      $tmp[] = '<b>'. $tmp3['LABEL']. '</b> '. implode(', ', $tmp2);
    }
#echo "<b>$i</b>", ht(dump($tmp));
  $row['fks'] = implode('<br />', $tmp);
}

$tpl_content->addlist('attribute', $db->fetch_table($db->lang_select('attr')
  . ' order by V1 limit '. $n_ofs. ', '. $n_perpage), 'tpl/de/attribs.row.htm', 'print_attr2');
?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */


set_time_limit(0);
if ($count=(int)$_REQUEST['anz'])
{
  require_once 'sys/lib.nestedsets.php';

  function fetch_groups($sql, $fk, $pk, $db=NULL)
  {
    if (is_null($db)) $db = $GLOBALS['db'];
    $res = $db->querynow($sql);
    $ret = array ();
    while ($row = mysql_fetch_assoc($res['rsrc']))
      $ret[$row[$fk]][$row[$pk]] = $row;
    return $ret;
  }
  function getrand($p)
  {
    if (is_array ($p))
      return $p[getrand(count($p))];
    elseif (is_int($p))
      return (int)rand(0, $p-1);
    elseif (is_float($p))
      return sprintf('%.2f', 1+ ($p-1) * rand() / getrandmax());
    elseif ('n'==$p)
      return (int)rand(1, 99);
    elseif ('f'==$p)
      return getrand(9999.0);
    else
      return rand();
  }

  $attr2group = fetch_groups("select z.*, a.*, u.VALUE as S_TYP from attr a, attr2group z, lookup u
    where FK_ATTR=ID_ATTR and ID_LOOKUP=LU_TYPE", 'FK_ATTR_GROUP', 'ID_ATTR');

  $ar_path = $attr_groups = array ();
  $nest = new nestedsets ('attr_group', 1);
  $rsrc = $nest->nestSelect();
#echo ht(dump($attr2group));
  while ($row = mysql_fetch_assoc($rsrc))
  {
    $row['attr'] = array ();
    for($k=1; $k<$row['level']; $k++)
      $row['attr'] = array_merge($row['attr'], $ar_path[$k]['attr']);
    if ($tmp = $attr2group[$row['ID_ATTR_GROUP']])
      foreach($tmp as $id_attr=>$attr)
        $row['attr'][$id_attr] = $attr;

    $attr_groups[$row['ID_ATTR_GROUP']] = $row;
    $ar_path[$row['level']] = &$attr_groups[$row['ID_ATTR_GROUP']];
  }
#echo (listtab($attr_groups));

  $ar_path = $nar_kat = array ();
  $nest = new nestedsets ('kat', 1);
  $rsrc = $nest->nestSelect('');
  while ($row = mysql_fetch_assoc($rsrc))
  {
    for ($k=$row['level']-1; !$row['FK_ATTR_GROUP'] && $k>0; $k--)
      $row['FK_ATTR_GROUP'] = $ar_path[$k]['FK_ATTR_GROUP'];

    $row['attr'] = $attr_groups[$row['FK_ATTR_GROUP']]['attr'];
    $row['opts'] = unserialize($row['SER_OPTIONS']);
    $row['vart'] = explode(',', $row['SET_ANZART']);
#echo ht(dump($row));

    $nar_kat[$row['ID_KAT']] = $row;
    $ar_path[$row['level']] = &$nar_kat[$row['ID_KAT']];
  }

  $nar_opts = $db->fetch_table($db->lang_select('kat_option'), 'ID_KAT_OPTION');
  $nar_vart = $db->fetch_table('select * from anzart', 'ID_ANZART');

  echo $count. ' Anzeigen generieren ...<br />';

  $def = $db->getdef('anzeige');
  #echo listtab($nar_kat);
  $s_lang = 'de';

  $nar_lookup = array ();
  $res = $db->querynow("select art, ID_LOOKUP from lookup");
  while (list($art, $id) = mysql_fetch_row($res['rsrc']))
    $nar_lookup[$art][] = $id;

  $nar_opts = $db->fetch_nar('select ID_KAT_OPTION, IDENT from kat_option');
#  $lu_typ = $db->fetch_nar("select ID_LOOKUP, VALUE from lookup where art='attr_type'");
/*
  $nar_attr = $db->fetch_table('select a.*, u.VALUE S_TYP from attr a, lookup u
    where ID_LOOKUP=LU_TYPE', 'ID_ATTR');
*/

  for ($n=1; $count-- > 0; $n++)
  {
    $anz = $db->fetch_blank('anzeige');
    $anz['FK_USER'] = $db->fetch_atom("select FK_USER from role2user where FK_ROLE=3 order by rand()");
    $kat = $nar_kat[$id_kat = $anz['FK_KAT'] = getrand(array_keys($nar_kat))];
    $art = $nar_vart[$id_art = $anz['FK_ANZART'] = getrand($kat['vart'])];

    $anz['STAMP_START'] = 'now()';
    $anz['STAMP_END'] = 'date_add(now(), interval RUNTIME day)';
    $anz['BF_VIS'] = 3;
    if ($kat['opts'][1])
      $anz['B_TOP'] = rand(0,1);
    if ($kat['opts'][11])
      $anz['B_START'] = rand(0,1);

    $opts = array ();
    foreach($kat['opts'] as $i=>$k)
      if ($k && $i!=1 && $i!=11 && rand(0,1) && preg_match('/^b\_/', $nar_opts[$i]))
        $opts[] = $nar_opts[$i];
    $anz['SET_OPTIONS'] = implode(',', $opts);

    if ($art['B__COUNT'])
      $anz['COUNT'] = getrand(25);
    if ($art['B__MINBID'])
      $anz['MINBID'] = getrand(200.0);
    if ($art['B__BUYNOW'])
      do
        $anz['BUYNOW'] = getrand('f');
      while ($anz['BUYNOW'] <= $anz['MINBID'])
      ;
    if ($art['B__LU_ZUSTAND'])
      $anz['LU_ZUSTAND'] = getrand($nar_lookup['ZUSTAND']);
    if ($art['B__LU_VKWEG'])
      $anz['LU_VKWEG'] = getrand($nar_lookup['VKWEG']);
    if ($art['B__LU_ZAHLUNG'])
      $anz['LU_ZAHLUNG'] = getrand($nar_lookup['ZAHLUNG']);
    if ($art['B__LU_VERSAND'])
      $anz['LU_VERSAND'] = getrand($nar_lookup['VERSAND']);
    if ($art['B__VERSANDKOSTEN'])
      $anz['VERSANDKOSTEN'] = getrand(18.0);
    if ($art['B__PLZ__ORT'])
      ; // nop
    $anz['V1'] = 'Automatik '. $n;
    $anz['T1'] = 'automatisch generiert am '. date('d.m.Y'). ' um '. date('H:i:s'). ' Uhr.';
    $id = $db->update('anzeige', $anz);
#echo ht(dump($anz));

    // Attribute
    if ($kat['attr']) foreach($kat['attr'] as $attr) if ($attr['B_MANDATORY'] || rand(0,1))
    {
#      echo ht(dump($z)), '<hr />';
      $params = unserialize($attr['PARAMS']);
      switch($s_typ = strtolower($attr['S_TYP']))
      {
        case 'int':
          if ($n = $params['maxval'])
            $val = rand(0, $n);
          elseif ($n = $params['maxlen'])
            $val = rand(0, pow(10,$n));
          else
            $val = rand();
          break;
        case 'flt':
          if ($n = $params['maxval'])
            $val = rand(0, $n);
          elseif ($n = $params['maxlen'])
            $val = rand(0, pow(10,$n)-1);
          else
            $val = rand();
          if ($n = $params['decimals'])
            $val = $val / pow(10, $n);
          break;
        case 'va':
        case 'txt':
          $val = 'random text ';
          if ($n = $params['maxlen'])
          {
            $val .= '- max. '. $n. ' Zeichen';
            if (strlen($val)>$n)
              $val = $n;
          }
          $val .= str_repeat(chr(rand(65,90)), 3);
          break;
        case 'msel':
        case 'sel':
          $db->querynow("insert into attr_val_sel (FK_ATTR, S_TABLE, FK, VALUE)
            select ". $attr['ID_ATTR']. ", 'anzeige', ". $id. ", ID_ATTR_OPTION
            from attr_option where FK_ATTR=". $attr['ID_ATTR']. "
            order by rand limit ". ('msel'==$s_typ ? 'rand()':'1')
          );
          $val = null;
          break;
      }
      if (!is_null($val))
        $db->querynow("insert into attr_val_". $s_typ. " (FK_ATTR, S_TABLE, FK, VALUE)
          values (". $attr['ID_ATTR']. ", 'anzeige', ". $id. ", '". mysql_escape_string($val). "')");
    }
    if (!($count%100)) echo '<br>noch ', $count, ' ...';
  }
#echo listtab($nar_vart);
}
else
  $tpl_content = 'Bitte Parameter "anz" angeben!';
?>
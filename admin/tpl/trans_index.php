<?php
/* ###VERSIONSBLOCKINLCUDE### */


/*
insert into `string_c` values
    ('anzeige', '6', '128', NULL , NULL , NULL ), -- nicht-existenter Datensatz
    ('6', '128', '0', NULL , NULL , NULL), -- ungueltiger Tabellenname
    ('nixda', '128', '0', NULL , NULL , NULL), -- nicht existente Tabelle
    ('nav', 1, 128, null, NULL , NULL), -- falsche string-Tabelle
;*/
/**/
$bak = $ar_query_log;
$ar_log = array ();
$lastresult = $db->querynow("show tables like 'string%'");
$res_p = $lastresult['rsrc'];
while (list($s_ptab) = mysql_fetch_row($res_p))
{
#echo '<h1>', $s_ptab, '</h1>';
  $s_bf_lang = 'BF_LANG'. strtoupper(substr($s_ptab,6));
  $lastresult = $db->querynow("select distinct(S_TABLE) from $s_ptab");
  $res_f = $lastresult['rsrc'];
  while (list($s_ftab) = mysql_fetch_row($res_f))
  {
    $s_pk = 'ID_'. strtoupper($s_ftab);

#echo "<b>$s_ftab</b><BR />";
    $sql = false;
    if (preg_match('/[`"\/\\]/', $s_ftab) || !trim($s_ftab))
      $sql = ''; //invlid table name
    elseif (!($x = $db->fetch_atom("show tables like '$s_ftab'")))
      $sql = ''; // non existent table
    elseif (!($x = $db->fetch_atom("show fields from `$s_ftab` like '$s_bf_lang'")))
      $sql = ''; // wrong string table            #xxx evtl. vorm loeschen kopieren?
    elseif (count($nar = $db->fetch_nar("select `$s_ptab`.FK, FK, `$s_pk` from `$s_ptab`
        left join `$s_ftab` on `$s_pk`=FK
        where S_TABLE='$s_ftab'
        having `$s_pk` is null")))
#{ echo 'some FKs not found<br />';
      $sql = " and FK in (". implode(', ', $nar). ")";
#}

#echo dump($sql), '<br />';
    if ($sql!==false)
#echo "delete from $s_ptab where S_TABLE='$s_ftab'$sql;<br />";if(0)
    {
      $lastresult = $db->querynow("delete from `$s_ptab` where S_TABLE='$s_ftab'". $sql);
      $ar_log[] = $lastresult;
    }
  }
}
#echo '<hR />';
/* if (count($ar_log)) echo listtab($ar_log); /**/
$ar_query_log = $bak;
/**/
  $n_tab = max(1, (int)$_REQUEST['tab']);
  include ('tpl/trans_params.php'); // do, Sprache 0, Sprache 1

  $ar_head = array (
  #  'table'=> null,
    'S_TABLE'=> 'Tabelle',
    'LABEL' => 'Verwendung',
    'FIELDS' => 'Felder',
    'ANZ' => 'Datens&auml;tze gesamt'
  );

// Daten sammeln ---------------------------------------------------------------
  $lastresult = $db->querynow("show tables like 'string%'");
  $res_p = $lastresult['rsrc'];
  $ar_ptables = $ar_ftables = $ar_sfields = $ar_langhead = array ();

  while (list($s_ptable) = mysql_fetch_row($res_p))
  {
    $ar_ptables[] = $s_ptable;
    $lastresult = $db->querynow("select S_TABLE, count(*) from `". $s_ptable. "` group by 1 order by 1");
    $res_f = $lastresult['rsrc'];
    for ($i=0; list($s_ftable, $n_count) = mysql_fetch_row($res_f); $i++)
    {
      $nar_f2p[$s_ftable] = substr($s_ptable, 7);
      $s_tmp = (string)substr($s_ptable, 7);
      $ar_tables[$s_ptable][] = $s_ftable;
      // fuers select:
      $s_label = plabel($s_tmp). ': '. flabel($s_ftable);
      $nar_tmp[$s_tmp. '.'. $s_ftable] = $s_label. ' ('. $n_count. ')';
      // fuer Tabelle:
      $row = array (
        'table' => $s_ptable,
        'ptable' => $s_tmp,
        'S_TABLE' => $s_ftable,
        'LABEL' => $s_label
      );
      $row['ANZ'] = $db->fetch_atom("select count(*) from `$s_ftable`");
      $lastresult = $db->querynow($db->lang_select('lang', 'ID_LANG, BITVAL, LABEL, count(z.FK) ANZ,
        sum(z.V1 is not null) ANZ_V1, sum(z.V2 is not null) ANZ_V2, sum(z.T1 is not null) ANZ_T1'). "
        left join ". $s_ptable. " z on z.S_TABLE='". $s_ftable. "' and z.BF_LANG=BITVAL
        group by ID_LANG");
      $ar_fields = array ();
      while ($tmp = mysql_fetch_assoc($lastresult['rsrc']))
      {
        if (!$i) $ar_head['LANG'.$tmp['ID_LANG']] = $tmp['LABEL'];
        $row['LANG'. $tmp['ID_LANG']] = $tmp['ANZ'];
#echo "<b>$s_ptable.$s_ftable</b> : $tmp[ANZ] = v1:$tmp[V1], v2:$tmp[V2], t1:$tmp[T1]<br />";
        // fuer Suche
        if ($tmp['ANZ_V1']) { $nar_str_fields[$s_ftable][1] = $ar_fields['V1'] = 'V1'; $row['FIELDS_V1'] = true; }
        if ($tmp['ANZ_V2']) { $nar_str_fields[$s_ftable][2] = $ar_fields['V2'] = 'V2'; $row['FIELDS_V2'] = true; }
        if ($tmp['ANZ_T1']) { $nar_str_fields[$s_ftable][3] = $ar_fields['T1'] = 'T1'; $row['FIELDS_T1'] = true; }
      }
      $row['FIELDS'] = implode(', ', $ar_fields);

      $ar_data[] = $row;
    }
  }

// Reiter 1: Uebersicht ========================================================
  $s_head = '<tr>
    <th>Aktionen</th>
    <th>'. implode('</th>
    <th>', $ar_head). '</th>
  </tr>';

  $s_tpl =
  '<tr class="zeile{even}"{if ANZ>1} id="all{S_TABLE}" onClick="listresults(\'all{S_TABLE}\', \'tbl\', \'{S_TABLE}\');"{endif}>
    <td>{if ANZ>1}<a href="javascript:listresults(\'all{S_TABLE}\', \'tbl\', \'{S_TABLE}\');">list</a>{endif}</td>
    <td>{htm('. implode(')}</td>
    <td>{htm(', array_keys($ar_head)). ')}</td>
  </tr>';
  $assoc_loaded_templates['translate.tmprow'] = $s_tpl;
  $tpl_content->addvar('stat_head', $s_head);
  $tpl_content->addlist('stat_list', $ar_data, 'translate.tmprow');
#echo ht(dump($tpl_content->vars['stat_list']));
#$tpl_content->vars['stat_list'] = preg_replace('%\<td\>\d+\</td\>%', '<td align="center">$1</td>', $tpl_content->vars['stat_list']);

  $tpl_content->addvar('select_table', nar2select('name="tbl" id="select_table"
    onChange="do_select(this);"', $_REQUEST['tbl'], $nar_tmp, '-- alle --'));

// Reiter 2: Suche =============================================================
// Suche -----------------------------------------------------------------------
  $err = array ();
  $ar_tbl_fields = $_REQUEST['tbl_fields'];
  $ar_str_fields = $_REQUEST['str_fields'];
  if ($s_query = $_REQUEST['qry'])
  {
    if (!$ar_tbl_fields && !$ar_str_fields)
      $err[] = 'Bitte w&auml;hlen Sie mindestens ein Feld aus!';
    else
    {
      $s_qry_lang = $_REQUEST['qry_lang'];
      $s_query_table = $_REQUEST['tbl'];
      if ('0'!=$s_query_table)
      {
        $s_table = (($p=strpos($s_query_table, '.')) ? substr($s_query_table, $p+1) : $s_query_table);
        foreach ($ar_data as $i=>$row)
          if ($row['S_TABLE'] == $s_table)
          {
            $ar_tbl = array ($i=>$s_table);
            break;
          }
      }
      else
        $ar_tbl = array_keys($nar_f2p);

      // Suche starten
      foreach ($ar_tbl as $i=>$s_ftable)
      {
        $ar_matches = array ();
        $s_ptable = 'string'. (($s = $nar_f2p[$s_ftable]) ? '_'.$s : '');
        $s_like = " like '%". mysql_escape_string($s_query). "%'";
        if ($ar_str_fields)
        {
          $ar_tmp = array ();
          foreach ($ar_str_fields as $s_field)
            $ar_tmp[] = "$s_field$s_like";
          $lastresult = $db->querynow("select FK from $s_ptable
            where S_TABLE='$s_ftable' ". ($s_query_lang ? ' and BF_LANG='. $s_query_lang : '')
            . "and (". implode(' or ', $ar_tmp). ")");
#if (!$lastresult['rsrc']) echo ht(dump($lastresult));
          while (list($fk) = mysql_fetch_row($lastresult['rsrc']))
            $ar_matches[$fk] = $fk;
        }
        if ($ar_tbl_fields)
        {
          $ar_tmp = array ();
          foreach ($ar_tbl_fields as $s_field)
          {
            $n = strlen($s_ftable);
            if (!strncmp($s_ftable. '.', $s_field, $n+1))
            $ar_tmp[] = substr($s_field, $n+1). $s_like;
          }
          if (count($ar_tmp))
          {
            $lastresult = $db->querynow("select ID_". strtoupper($s_ftable). " from `$s_ftable`
              where ". implode(' or ', $ar_tmp));
#if (!$lastresult['rsrc']) echo ht(dump($lastresult));
            while (list($fk) = mysql_fetch_row($lastresult['rsrc']))
              $ar_matches[$fk] = $fk;
          }
        }
        $_SESSION['translate.matches.'. $s_ftable] = implode(',', array_values($ar_matches));
        if ($n = count($ar_matches))
          $ar_matchlist[] = array ('S_TABLE'=>$s_ftable, 'COUNT'=>$n, 'LABEL'=>$ar_data[$i]['LABEL']);
      }
    }
    if (count($ar_matchlist))
      $tpl_content->addlist('matches', $ar_matchlist, 'tpl/de/trans_index.matchrow.htm');
    elseif (count($err))
      $tpl_content->addvar('err_search', implode('<br />', $err));
    else
      $tpl_content->addvar('nomatch');
    $err = array ();
  }
  if (!$ar_str_fields) $ar_str_fields = array ();
  if (!$ar_tbl_fields) $ar_tbl_fields = array ();
  if (!$s_query_table) $s_query_table = '0';

  // Sprachen auswaehlen
  $langs = $db->fetch_table($db->lang_select('lang'). ' order by BITVAL desc', 'ID_LANG');

// String-Felder ---------------------------------------------------------------
  $ar_js = $ar_allfields = $ar_tables = array ();

  foreach ($ar_data as $row)
  {
    preg_match_all('/\w+\d+/', $row['FIELDS'], $match);
    foreach ($match[0] as $s) $ar_allfields[$s] = "'$s'";
    $ar_js[] = "\n  fields_". $row['S_TABLE']. " = new Array ('"
      . preg_replace('/,\s*'. '/', "', '", strtolower($row['FIELDS'])). "');";
    $ar_tables[] = $row['S_TABLE']; # fuer Suche in allen Tabellen
  }
  foreach ($ar_str_fields as $v)
    $tpl_content->addvar('chk_'. strtolower($v), true);
  $ar_js[] = "\n  allfields = new Array(". strtolower(implode(', ', $ar_allfields)). ");";
  $ar_js[] = "\n  alltables = new Array('". implode("', '", $ar_tables). "');";
  $tpl_content->addvar('js_fields', $ar_js);

// Tabellenfelder --------------------------------------------------------------
  $ar_fielddivs = $ar_count = $nar_tbl_fields = array ();
  foreach ($ar_data as $i=>$row)
  {
    $def = $db->getdef($s_ftable = $row['S_TABLE']);
    $ar_tmp = array ();
    foreach ($def as $field)
    {
      preg_match('/^\w+/', $field['Type'], $match); # /^(\w+)(\((.*)\))?(\s.*)$/
      $s_type = $match[0];
      $s_field = $field['Field'];
      $s_id = 'chk_'. $s_ftable. '__'. $s_field;
      if (preg_match('/(text|char)$/', $s_type))
      {
        $nar_tbl_fields[$s_ftable][] = $s_field;
        $ar_tmp[] = '
    <input type="checkbox" class="nob" name="tbl_fields[]" id="'. $s_id. '" '
          . (in_array ("$s_ftable.$s_field", $ar_tbl_fields) ? 'checked ' : '')
          . 'value="'. $s_ftable. '.'. $s_field. '" />&nbsp;<label for="'. $s_id. '">'. $s_field. '</label>';
      }
    }
    $ar_count[] = count($ar_tmp);
    $ar_fielddivs[] = (count($ar_tmp)
      ? '
    <div id="lbldiv_'. $s_ftable. '" style="display:none;font-weight:bold;">'. flabel($s_ftable). ':<br /></div>
    <div id="tbldiv_'. $s_ftable. '" style="display:none;">'. implode('<br />', $ar_tmp). '<br /></div>'
      : '<div id="lbldiv_'. $s_ftable. '" style="display:none"></div><div id="tbldiv_'. $s_ftable. '" style="display:none"></div>'
    );
  }

  for ($n_cols = 4, $k=1; $n_cols>1; $n_cols--, $k++)
  {
    $n_halbe = array_sum($ar_count)/$n_cols;
    $ar_tmp = array ();
    while (($n_halbe>=1) && count($ar_fielddivs))
    {
      $n_halbe -= array_shift($ar_count);
      $ar_tmp[] = array_shift($ar_fielddivs);
    }
    $tpl_content->addvar('tablefielddivs'. $k, $ar_tmp);
  }
  $tpl_content->addvar('tablefielddivs'. $k, $ar_fielddivs);

// Template-Finish -------------------------------------------------------------
  $tpl_content->addvar('qry', $s_query);
  $tpl_content->addvar('qry_tbl', $s_query_table);
  $nar = $db->fetch_nar($db->lang_select('lang', 'BITVAL,LABEL'). " order by BITVAL desc");
  $tpl_content->addvar('select_lang', nar2select('name="qry_lang"', $s_query_lang, $nar, '-- alle --'));
  $tpl_content->addvar('gotab', $n_tab);
?>
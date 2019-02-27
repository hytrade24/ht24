<?php
/* ###VERSIONSBLOCKINLCUDE### */


// Funktionen ------------------------------------------------------------------
  function nar_part($nar_src, $s_ereg = '')
  {
    $nar_trg = array ();
    if ($s_ereg)
    {
      foreach($nar_src as $k=>$v)
        if (preg_match($s_ereg, $k))
          $nar_trg[$k] = $v;
    }
    else
    {
      foreach($nar_src as $k=>$v)
        if (!is_array ($v) && !is_object($v))
          $nar_trg[$k] = $v;
    }
    return $nar_trg;
  }

  // Uebersicht
  function plabel($s_table)
  {
    static $nar_p = array (
      '' => 'Kernel',
      'app' => 'Anwendung',
      'c' => 'Content',
      'kat' => 'Kategorien'
    );
    return (($s=$nar_p[$s_table]) ? $s : $s_table);
  }

  function flabel($s_table)
  {
    static $nar_f = array (
      'country' => 'Länder',
      'kat_option' => 'Kategorie-Optionen',
      'lang' => 'Sprachen',
      'lookup' => 'Lookups',
      'nav' => 'Navigation',
      'faqkat' => 'FAQ-Kategorien',
      'message' => 'Skript-Meldungen',
      'anzeige' => 'Anzeigen',
      'faq' => 'FAQ',
      'news' => 'News',
      'news_key' => 'News / Schlüsselwörter',
      'attr' => 'Attribute',
      'attr_group' => 'Attributgruppen',
      'attr_option' => 'Attribut-Optionen',
      'kat' => 'Kategorien'
    );
    return (($s=$nar_f[$s_table]) ? $s : $s_table);
  }

// Parameter -------------------------------------------------------------------
  $do = $_REQUEST['do'];
  $n_tab = max(1, (int)$_REQUEST['tab']);

// POST (save)
if (count($_POST) && 'sv'==$do)
{
  $s_ftable = $_POST['tbl'];
  $bak = $langval;
  $langval = $_POST['editlang'];

	if(!isset($_POST['jumpToNextWithoutSave'])) {

		  $data = array (
			'ID_'. strtoupper($s_ftable) => $_POST['id'],
			'V1'=> $_POST['V1'],
			'V2'=> $_POST['V2'],
			'T1'=> $_POST['T1']
		  );

		$id = $db->update($s_ftable, $data);

	} else {
		$id = $_POST['id'];
	}
#die(ht(dump($data)));
  $langval = $bak;

	if((isset($_POST['JUMP_TO_NEXT']) && $_POST['JUMP_TO_NEXT'] == '1')) {
		$_SESSION['translate.jump_to_next'] = 1;
	} else {
		$_SESSION['translate.jump_to_next'] = 0;
	}


	if((isset($_POST['JUMP_TO_NEXT']) && $_POST['JUMP_TO_NEXT'] == '1') || isset($_POST['jumpToNextWithoutSave']) || isset($_POST['jumpToPrevWithoutSave'])) {
		$s_pk = 'ID_'. strtoupper($s_ftable);

		$compareOperator = '>';
		$orderOperator = '';
		if(isset($_POST['jumpToPrevWithoutSave'])) {
			$compareOperator = '<';
			$orderOperator = 'desc';
		}

		$nextId = $db->fetch_atom($a = "select $s_pk from `$s_ftable` where 1 = 1". ($s_fks ? "
		        and $s_pk in ($s_fks)" : ''). " AND $s_pk ".$compareOperator." $id
		        order by $s_pk $orderOperator ");

		forward('index.php?page=translate&do=edit&id='. $nextId);
	}

  forward('index.php?page=translate&do=edit&id='. $id);
}

// Parameter Teil 2-------------------------------------------------------------
  // Sprachen
  if ($id_lang0 = $_REQUEST['L0_ID_LANG'])
    ;
  else
    $id_lang0 = $_SESSION['translate.L0'];

  if ($id_lang1 = $_REQUEST['L1_ID_LANG'])
    ;
  else
    $id_lang1 = $_SESSION['translate.L1'];

  if (!$id_lang1)
    $id_lang1 = (int)$db->fetch_atom("select ID_LANG from lang
      order by B_PUBLIC desc, BITVAL desc limit 1");


  if ($id_lang1===$id_lang0)
    $id_lang0 = 0;

  $_SESSION['translate.L0'] = $id_lang0;
  $_SESSION['translate.L1'] = $id_lang1;

  // suchen / auflisten
  if ($s_query = $_REQUEST['qry'])
    $_SESSION['translate.qry'] = $s_query;
  else
    $s_query = $_SESSION['translate.qry'];

  if ('qry'==$do || 'show'==$do)
    $_SESSION['translate.listmode'] = 'qry';
  elseif ('list'==$do)
    $_SESSION['translate.listmode'] = 'tbl';

  if ('qry'==$_SESSION['translate.listmode'])
    if (strlen($s_query_table = $_REQUEST['tbl']))
      $_SESSION['translate.qrytbl'] = $s_query_table;
    else
      $s_query_table = $_SESSION['translate.qrytbl'];

  // Welche Felder durchsuchen?
  if ($ar_str_fields = $_REQUEST['str_fields'])
    $_SESSION['translate.str_fields'] = $ar_str_fields;
  elseif (count($_POST) && 'qry'==$do)
    $_SESSION['translate.str_fields'] = $ar_str_fields = false;
  else
    $ar_str_fields = $_SESSION['translate.str_fields'];
  if ($ar_str_fields)
    foreach ($ar_str_fields as $v)
      $tpl_content->addvar('chk_'. strtolower($v), true);
  else
    $ar_str_fields = array ();

  if ($ar_tbl_fields = $_REQUEST['tbl_fields'])
    $_SESSION['translate.qrytbl_fields'] = $ar_tbl_fields;
  elseif (count($_POST) && 'qry'==$do)
    $_SESSION['translate.qrytbl_fields'] = $ar_tbl_fields = false;
  else
    $ar_tbl_fields = $_SESSION['translate.qrytbl_fields'];
  if (!$ar_tbl_fields)
    $ar_tbl_fields = array ();

  if (strlen($s_qry_lang = $_REQUEST['qry_lang']))
    $_SESSION['translate.qry_lang'] = $s_qry_lang;
  else
    $s_qry_lang = (int)$_SESSION['translate.qry_lang'];

// Sprachen --------------------------------------------------------------------
  // Sprachen auswaehlen
  $langs = $db->fetch_table($db->lang_select('lang'). ' order by BITVAL desc', 'ID_LANG');
  if ($id_lang0) $tpl_content->addvars($langs[$id_lang0], 'L0_');
  if ($id_lang1) $tpl_content->addvars($langs[$id_lang1], 'L1_');

// Reiter 1: Uebersicht ========================================================
// Tabellen --------------------------------------------------------------------
  $lastresult = $db->querynow("show tables like 'string%'");
  $ar_ptables = $ar_tables = $nar_tmp = $nar_str_fields = array ();

  while (list($s_ptable) = mysql_fetch_row($lastresult['rsrc']))
    $ar_ptables[] = $s_ptable;

  $nar_head = array (
  #  'table'=> null,
    'S_TABLE'=> 'Tabelle',
    'LABEL' => 'Verwendung',
    'FIELDS' => 'Felder',
    'ANZ' => 'Datens&auml;tze gesamt'
  );
  $nar_f2p = array ();
  foreach($ar_ptables as $s_ptable)
  {
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
        if (!$i) $nar_head['LANG'.$tmp['ID_LANG']] = $tmp['LABEL'];
        $row['LANG'. $tmp['ID_LANG']] = $tmp['ANZ'];
#echo "<b>$s_ptable.$s_ftable</b> : $tmp[ANZ] = v1:$tmp[V1], v2:$tmp[V2], t1:$tmp[T1]<br />";
        $nar_str_fields[$s_ftable][0] = $s_ptable;
        if ($tmp['ANZ_V1']) { $nar_str_fields[$s_ftable][1] = $ar_fields['V1'] = 'V1'; $row['FIELDS_V1'] = true; }
        if ($tmp['ANZ_V2']) { $nar_str_fields[$s_ftable][2] = $ar_fields['V2'] = 'V2'; $row['FIELDS_V2'] = true; }
        if ($tmp['ANZ_T1']) { $nar_str_fields[$s_ftable][3] = $ar_fields['T1'] = 'T1'; $row['FIELDS_T1'] = true; }
      }
      $row['FIELDS'] = implode(', ', $ar_fields);
      $row['ptable'] = substr($s_ptable, 7);

      $ar_data[] = $row;
    }
  }

  $s_head = '<tr>
    <th>Aktionen</th>
    <th>'. implode('</th>
    <th>', $nar_head). '</th>
  </tr>';

  $s_tpl = '<tr class="zeile{even}">
    <td>{if ANZ>1}<a href="index.php?page={curpagealias}&do=list&tbl={ptable}.{S_TABLE}">{if ANZ==1}edit{else}list{endif}</a>{endif}'
      /*{if ANZ<26 && !FIELDS_T1 && (FIELDS_V1 || FIELDS_V2)} | editall{endif}*/.'</td>
    <td>{htm('. implode(')}</td>
    <td>{htm(', array_keys($nar_head)). ')}</td>
  </tr>';
  $assoc_loaded_templates['translate.tmprow'] = $s_tpl;
  $tpl_content->addvar('stat_head', $s_head);
  $tpl_content->addlist('stat_list', $ar_data, 'translate.tmprow');

  $tpl_content->addvar('select_table', nar2select('name="tbl" id="select_table"
    onChange="do_select(this);"', $s_query_table, $nar_tmp, '-- alle --'));

// Reiter 2: Suche =============================================================
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
    <div id="lbldiv_'. $s_ftable. '" style="display:none;font-weight:bold;">'. $s_ftable. ':<br /></div>
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

// Suche -----------------------------------------------------------------------
  $ar_matchlist = array ();
  if (count($_POST) && 'qry'==$do)
  {
    $n_tab = 2;
    if ('0'!=$s_query_table)
    {
      $s_table = substr(strstr($s_query_table, '.'), 1);
#echo $s_table;echo listtab($ar_data);die();
      foreach ($ar_data as $i=>$row)
        if ($row['S_TABLE'] == $s_table)
        {
          $ar_tables = array ($i=>$s_table);
          break;
        }
    }

    if ('qry'==$do) // suchen
    {
      $_SESSION['translate.listmode'] = 'qry';
      foreach ($ar_tables as $i=>$s_ftable)
      {
        $ar_matches = array ();
        $s_lang_table = $ar_data[$i]['table'];
        $s_like = " like '%". mysql_escape_string($s_query). "%'";
        if (count($ar_str_fields))
        {
          $ar_tmp = array ();
          foreach ($ar_str_fields as $s_field)
            $ar_tmp[] = "$s_field$s_like";
          $lastresult = $db->querynow("select FK from $s_lang_table
            where S_TABLE='$s_ftable' ". ($s_query_lang ? ' and BF_LANG='. $s_query_lang : '')
            . "and (". implode(' or ', $ar_tmp). ")");
#if (!$lastresult['rsrc']) echo ht(dump($lastresult));
          while (list($fk) = mysql_fetch_row($lastresult['rsrc']))
            $ar_matches[$fk] = $fk;
        }
        if (count($ar_tbl_fields))
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
  }
  elseif ('tbl'==$_SESSION['translate.listmode'])
  {
    if ('list'==$do && $s_ftable = $_REQUEST['tbl'])
      $_SESSION['translate.listtbl'] = $s_ftable;
    else
      $s_ftable = $_SESSION['translate.listtbl'];
    if ($s_ftable)
    {
      $_SESSION['translate.listmode'] = 'tbl';
#echo listtab($ar_data);
      foreach ($ar_data as $i=>$row) if ("$row[ptable].$row[S_TABLE]"==$s_ftable) $listmatch = array (
        'S_TABLE'=>$s_ftable,
        'COUNT'=>$ar_data[$i]['ANZ'],
        'LABEL'=>$ar_data[$i]['LABEL']
      );
    }
#echo ht(dump($ar_matchlist));
  }

  // Suchergebnisse
  if ('qry'!=$do)
  {
    if ('show'==$do)
    {
      $_SESSION['translate.listmode'] = 'qry';
      $_SESSION['translate.qrytbl'] = $_REQUEST['tbl'];
    }
    $s_ftable = $_SESSION['translate.qrytbl'];
    if ($s_matches = $_SESSION['translate.matches.'. $s_ftable])
    {
      $ar_matches = explode(',', $s_matches);
      if ($n = count($ar_matches))
        $ar_matchlist[] = array ('S_TABLE'=>$s_ftable, 'COUNT'=>$n, 'LABEL'=>$ar_data[$i]['LABEL']);
    }
  }

  // Reiter 3: Suchergebnisse auflisten
  $tpl_content->addvar('listmode_'. $_SESSION['translate.listmode'], true);
  if ($listmatch || count($ar_matchlist))
  {
    $tpl_content->addlist('matches', $ar_matchlist, 'tpl/de/translate.matchrow.htm');

    if ($listmatch)
      $s_ftable = $_SESSION['translate.listtbl'];
    else
      if (!($s_ftable = $_REQUEST['tbl']))
        if (1==count($ar_matchlist))
          $s_ftable = $ar_matchlist[0]['S_TABLE'];

    if ($s_ftable) // Reiter 3
    {
      if (false!==strpos($s_ftable, '.'))
        list($s_ptable, $s_ftable) = explode('.', $s_ftable);
      else
        $s_ptable = $nar_f2p[$s_ftable];

      $ar_data = array ();

      if ('qry'==$_SESSION['translate.listmode'] && $s_fks = $_SESSION['translate.matches.'. $s_ftable])
        $ar_fks = explode(',', $s_fks);
      else
        $s_fks = false;

      $n_tab = 3;

      if (false!==strpos($s_ftable, '.'))
        list($s_ptable, $s_ftable) = explode('.', $s_ftable);
      else
        $s_ptable = $nar_f2p[$s_ftable];
      $s_ptable = 'string'. ($s_ptable ? '_'. $s_ptable : '');

      $s_pk = 'ID_'. strtoupper($s_ftable);

      $lastresult = $db->querynow("select * from `$s_ftable`". ($s_fks ? "
        where $s_pk in ($s_fks)" : ''). "
        order by $s_pk"); #xxx todo: blaettern
      $res = $lastresult;

      $nar_head = $nar_langhead = array ();
      if ($nar_tmp = $nar_str_fields[$s_ftable])
        $s_ptable = array_shift($nar_tmp);

      $s_highlight = ('qry'==$_SESSION['translate.listmode'] ? $GLOBALS['s_query'] : '');


      for($i = 0; $row = mysql_fetch_assoc($res['rsrc']); $i++)
      {
        $ar_row = array ();
        foreach($row as $k=>$v) if (!preg_match('/^BF_LANG/', $k))
        {
          if (!$i) $nar_head[$k] = $k;
          $ar_row[$k] = val_convert($v, $s_highlight);
        }

        if ($nar_tmp && count($nar_tmp))
        {
          $lastresult = $db->querynow("select ifnull(g.ABBR, p.BF_LANG), p.BF_LANG, p.V1, p.V2, p.T1
            from `$s_ptable` p, lang g
            where S_TABLE='$s_ftable' and FK=$row[$s_pk] and g.BITVAL=p.BF_LANG
            order by g.BITVAL desc");
          $v = array ();
          while (list($s_abbr, $bf, $v['V1'], $v['V2'], $v['T1']) = mysql_fetch_row($lastresult['rsrc']))
          {
            foreach($v as $k=>$vv) if (in_array ($k, $nar_str_fields[$s_ftable]))
            {
/**/
              $s_k = str_replace('T', 'Z', $k). str_pad((99999-$bf), 5, '0', STR_PAD_LEFT);
              $ar_row[$s_k] = val_convert($v[$k], $s_highlight);
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
        $ar_table[] = '<tr class="zeile'. !($i&1). '">
  <td><a href="index.php?page=translate&do=edit&tbl='. $s_ftable. '&id='. $row[$s_pk]. '">edit</a></td>';
        foreach($nar_head as $k=>$v) $ar_table[] = '
  <td>'. $row[$k]. '</td>';
        $ar_table[] = '
</tr>';
      }
      $ar_table[] = '</table>';
      $tpl_content->addvar('results', $ar_table);
      $tpl_content->addvar('result_table', $s_ftable);

      if ((1 == count($ar_fks) && $fk=(int)$ar_fks[0]) || ('edit'==$do && $fk=(int)$_REQUEST['id']))
      {
        $tpl_content->addvar('edit_id', $fk);
        $tpl_content->addvar('edit_tbl', $s_ftable);
        $tpl_content->addvar('edit_lbl', flabel($s_ftable));
        $n_tab = 4;
        $ar_data = $ar_lang0 = $ar_lang1 = array ();

        $data = $db->fetch1($q = "select * from `$s_ftable` where $s_pk=$fk");
        foreach ($data as $k=>$v) if (!preg_match('/^BF_LANG/', $k))
          $ar_data[] = '<tr>
    <td><b>'. $k. '</b></td>
    <td>'. val_convert($v). '</td>
  </tr>';
        $tpl_content->addvar('itemdata', $ar_data);

        $lastresult = $db->querynow($a  = $db->lang_select('lang', 't.ID_LANG, t.BITVAL, LABEL, g.*'). "
          left join $s_ptable g on g.S_TABLE='$s_ftable' and g.FK=$fk and (g.BF_LANG & t.BITVAL)
          where ID_LANG in ($id_lang0, $id_lang1)");

        while ($row = mysql_fetch_assoc($lastresult['rsrc']))
          if ($row['ID_LANG']==$id_lang1)
            $tpl_content->addvars($row, 'E1_');
          else
          {
            $tpl_content->addvars($row, 'E0_');
            $ar_tmp = array_diff(array ('V1', 'V2', 'T1'), $nar_str_fields[$s_ftable]);
#echo ht(dump($ar_tmp));
            foreach ($ar_tmp as $v)
              $tpl_content->addvar('null_'. $v, true);
            $tpl_content->addvar('null_v1', is_null($row['V1']));
            $tpl_content->addvar('null_v2', is_null($row['V2']));
            $tpl_content->addvar('null_t1', is_null($row['T1']));
          }
      }
#echo ht(dump(nar_part($tpl_content->vars, '^item')));echo ht(dump(nar_part($tpl_content->vars, '^E0_')));echo ht(dump(nar_part($tpl_content->vars, '^E1_')));
#echo ht(dump(nar_part($GLOBALS)));
#echo ht(dump(nar_part(&$_SESSION, '^translate\.')));
#echo dump($s_ptable);
#echo ht(dump($row));
    }
  }
  else
    $tpl_content->addvar('nomatch', true);

  $nar = $db->fetch_nar($db->lang_select('lang', 'BITVAL,LABEL'). " order by BITVAL desc");
  $tpl_content->addvar('select_lang', nar2select('name="qry_lang"', $s_query_lang, $nar, '-- alle --'));
#$nar = array (); foreach ($_SESSION as $k=>$v) if (!strncmp('translate.', $k, 10)) $nar[$k] = $v; echo ht(dump($nar));

#if ($do

// Template-Finish -------------------------------------------------------------
  $tpl_content->addvar('qry', $s_query);
  $tpl_content->addvar('qry_tbl', $s_query_table);
  $tpl_content->addvar('gotab', $n_tab);

	$tpl_content->addvar('jump_to_next', $_SESSION['translate.jump_to_next']);
?>
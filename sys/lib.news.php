<?php
/* ###VERSIONSBLOCKINLCUDE### */


  include_once 'cache/option.php';
  function shownews($id_bereich=0, $id_thema=0, $uint_start=0, $int_limit = NULL)
  {
    global $db, $ar_dirs, $user, $ar_ident_path, $ar_nav_params, $id_nav, $nar_systemsettings;
    global $s_alter, $s_fulltext, $s_wholeword, $s_text, $s_ar_thema;

    static $uint_maxlen=NULL, $int_limit=NULL;//, $s_allowed=NULL;
    if (!is_array ($s_ar_thema))
      $s_ar_thema = array ();
    if (is_null($uint_maxlen))
    {
      $uint_maxlen = $nar_systemsettings['NEWS']['SHORTLEN'];
      if (!$int_limit)
        $int_limit = $nar_systemsettings['NEWS']['PERPAGE'];
//      $s_allowed = $db->fetch_atom("select VALUE from misctext where ID_MISCTEXT='allowedtags'");
    }
    if ($tmp = (int)$_REQUEST['ofs'])
      $uint_start = $tmp;
#echo "$bereich-$thema-$int_limit-$uint_start<br />";
    $fl_admin = ($id_nav>=9);#($user && 1==$user['FK_GROUP']); // xxx todo: $id_nav>9 ==> in_array (2, $roles)
    // Anzahl
    $ar_join = array ();
    if ($id_thema || $id_bereich || count($s_ar_thema)) $ar_join[] = "
      left join news2thema z on z.FK_NEWS=n.ID_NEWS";
#    if ($id_bereich) $ar_join[] = "";
    if (is_array ($tmp = $GLOBALS['ar_join']))
      $ar_join = array_merge($ar_join, $tmp);
    $ar_where = array (1);
    if (count($s_ar_thema))
      $ar_where[] = 'z.FK_THEMA in ('. implode(', ', $s_ar_thema). ')';
    if (is_array ($tmp = $GLOBALS['ar_where']))
      $ar_where = array_merge($ar_where, $tmp);
#echo '<hr />---',ht(dump($ar_where)),'<hr />';
    $tmp = array ();
    foreach($ar_where as $v)
      $tmp[] = trim($v);
    $ar_where = array_unique($tmp);
    $tmp = array ();
    foreach($ar_join as $v)
      $tmp[] = trim($v);
    $ar_join = array_unique($tmp);

    if ($s_text)
    {
      $sql_text = mysql_escape_string($s_text);
      $ar_where[] = ($s_wholeword
        ? ($s_fulltext
          ? "(n.LABEL regexp '[[:<:]]{$sql_text}[[:>:]]' or n.BODY regexp '[[:<:]]{$sql_text}[[:>:]]')"
          : "n.LABEL regexp '[[:<:]]{$sql_text}[[:>:]]'"
        )
        : ($s_fulltext
          ? "(n.LABEL like '%$sql_text%' or n.BODY like '%$sql_text%')"
          : "n.LABEL like '%$sql_text%'"
        )
      );
    }
#echo dump($fl_admin);

    $sql = " from news n
      left join news2thema z on FK_NEWS=ID_NEWS
      left join thema t on t.ID_THEMA=z.FK_THEMA". ($fl_admin ? '' : ' and t.F_AKTIV'). "
      left join lookup b on b.ID_LOOKUP=t.LU_BEREICH
      where ". implode(' and ', $ar_where). (!$fl_admin ? "
        and curdate() between PUBLISH and LASTDAY" : ''). ($s_alter ? "
        and date_add(PUBLISH, interval $s_alter day)>curdate()" : ''). "
        and (t.ID_THEMA". ($id_thema ? "=$id_thema"
          : ($id_bereich ? '>0' : '>0 or z.FK_THEMA=0')). ')'. ($id_bereich ? "
        and b.VALUE=$id_bereich" : '')
    ;
    $anz_gesamt = $db->fetch_atom("select count(distinct ID_NEWS)$sql");

    // Liste
    $ar_data = $db->fetch_table("select n.* $sql
      group by ID_NEWS
      order by PUBLISH desc, ID_NEWS desc". ($int_limit>=0 ? " limit $uint_start,$int_limit" : '')
    );

    $ar_liste = array ();
    foreach($ar_data as $i=>$row)
    {
      $tpl_tmp = new DirTemplate($ar_dirs, 'news.row');
      $tpl_tmp->addvar('i', $i);
      $tpl_tmp->addvars($row);

      // Bereiche
      $ar_tmp = $db->fetch_nar("select b.ID_LOOKUP, b.LABEL from
        news2thema z, thema t, lookup b
        where FK_NEWS=$row[ID_NEWS] and FK_THEMA=ID_THEMA and LU_BEREICH=ID_LOOKUP
        and b.art='BEREICH'". ($fl_admin ? '' : ' and t.F_AKTIV=1'));
      if ((int)$db->fetch_atom("select count(*) from news2thema where FK_NEWS=$row[ID_NEWS] and FK_THEMA=0"))
        array_unshift($ar_tmp, 'Startseite');
      $tpl_tmp->addvar('bereiche', implode(', ', $ar_tmp));

      // Kurz-Text
      $row['BODY'] = trim($row['BODY']);
      if (strlen($str_anfang = $row['BODY'])>$uint_maxlen)
      {
        list($line) = explode("\n", wordwrap($str_anfang, $uint_maxlen));
        $p = strpos($str_anfang, ' ', $uint_maxlen+1);
        $str_anfang = substr($str_anfang, 0, ($p===false ? $uint_maxlen : $p)). ' ...';
      }

      if (count($ar_nav_params))
        $tpl_tmp->addvar('params', '-.'. implode('-.', $ar_nav_params));

      $tpl_tmp->addvar('anfang', $str_anfang);
      $tpl_tmp->addvar('more', $row['IMG'] || ($str_anfang != $row['BODY']));
      $ar_liste[] = $tpl_tmp;
    }

    return array (
      'liste'=>$ar_liste,
      'anzahl'=>$anz_gesamt,
      'ofs'=>$uint_start,
      'len'=>count($ar_liste),
      'ofs_next'=>($int_limit>=0 && $anz_gesamt>$uint_start+$int_limit ? $uint_start+$int_limit : -1),
      'ofs_prev'=>($int_limit>=0 && $uint_start>0 ? $uint_start-$int_limit : -1),
    );

  }

  function news_table($bereich=0, $thema=0, $uint_start=0, $int_limit = NULL)
  {
    global $db, $ar_dirs, $id_nav;
    $tpl_news = new DirTemplate($ar_dirs, 'news.table');
    $tpl_news->addvars($data = shownews($bereich, $thema));
#echo listtab($data['liste']);
    $s_thema = ($thema ? "&thema=$thema" : '');
    if ($thema) $tpl_news->addvar('thema', $db->fetch_atom(
      "select LABEL from thema where ID_THEMA=$thema"
    ));
    if ($bereich) $tpl_news->addvar('bereich', $db->fetch_atom(
      "select LABEL from lookup where art='BEREICH' and VALUE=$bereich"
    ));
    if ($data['ofs_next']>=0)
      $tpl_news->addvar('ref_next', "index.php?nav=$id_nav$s_thema&ofs=$data[ofs_next]");
    if ($data['ofs_prev']>=0)
      $tpl_news->addvar('ref_prev', "index.php?nav=$id_nav$s_thema&ofs=$data[ofs_prev]");
    return $tpl_news;
  }

  function news_suchthemen($ar_selected, $lu_bereich=0)
  {
    global $db, $id_nav;
    $fl_admin = $id_nav>9;
    if (is_array ($lu_bereich))
      $nar_themen = $lu_bereich;
    elseif ($lu_bereich)
      $nar_themen = $db->fetch_nar("select ID_THEMA,
        if (F_AKTIV, LABEL, concat('*', LABEL)) as LABEL
        from thema
        where LU_BEREICH=$lu_bereich". ($fl_admin ? '' : ' and F_AKTIV'). "
        order by POS");
    else
      $nar_themen = array (0=>'Startseite') + $db->fetch_nar("select ID_THEMA,
          concat(b.LABEL, ' > ', if(t.F_AKTIV, t.LABEL, concat('*', t.LABEL)))
        from lookup b, thema t
        where LU_BEREICH=ID_LOOKUP". ($fl_admin ? '' : ' and t.F_AKTIV'). "
        order by LU_BEREICH, t.POS");
    if (!$ar_selected) $ar_selected = array ();
    $ar_liste = array ();
    foreach($nar_themen as $id=>$label) $ar_liste[] = '
      <option '. (in_array ($id, $ar_selected) ? 'selected ':''). 'value="'. $id. '">'
      . stdHtmlentities($label). '</option>';
    return $ar_liste;
  }

function getNewsCategoryJSONTree($preSelectedNodes = array()) {
        require_once 'sys/lib.nestedsets.php'; // Nested Sets

        global $db;

        $nest = new nestedsets('kat', 2, false, $db);

        return json_encode(getNewsCategoryArrayTreeRecursive(null, $nest, array(), $preSelectedNodes));
    }

    function getNewsCategoryArrayTreeRecursive($id, nestedsets $nest, $visitedNodes = array(), $preSelectedNodes = array()) {
        require_once 'sys/lib.shop_kategorien.php';

        global $db;
        $langval = 128;

        $root = 2;

        $rootrow = $db->fetch1("select t.*, s.V1, s.V2, s.T1 from `kat` t left join string_kat s on s.S_TABLE='kat' and s.FK=t.ID_KAT and s.BF_LANG='" . $langval . "' where LFT=1 and ROOT='" . $root . "' ");

        if (!($id = (int)$id)) {
            $id = $rootrow['ID_KAT'];
            $lft = 1;
            $rgt = $rootrow['RGT'];
        } else {
            $lastresult = $db->querynow('select LFT,RGT from kat where ID_KAT=' . $id);
            list($lft, $rgt) = mysql_fetch_row($lastresult['rsrc']);
        }

        // Ahnenreihe lesen
        if ($lft == 1) {
            $ar_path = array();
            $n_level = 0;
        } else {
            $ar_path = $db->fetch_table($nest->nestQuery('and (' . $lft . ' between t.LFT and t.RGT)', '', '1 as is_last,1 as kidcount,1 as is_first,t.LFT=' . $lft . ' as is_current,', false), 'ID_KAT');
            $n_level = $ar_path[$id]['level'];
            $ar_path = array_values($ar_path);
        }

        // Kinder lesen
        $s_sql = $nest->nestQuery(' and (t.LFT between ' . $lft . ' and ' . $rgt . ')', '', 't.RGT-t.LFT>1 as haskids,', true);
        $s_sql = str_replace(' order by ', ' having level=' . (1 + $n_level) . ' order by ', $s_sql);
        $res = $db->querynow($s_sql);
        #echo ht(dump($res));

        if (!(int)$res['int_result']) // keine Kinder da -> kidcount der aktuellen Zeile auf 0
        {
            if ($n = count($ar_path)) $ar_path[$n - 1]['kidcount'] = 0;
        } else while ($row = mysql_fetch_assoc($res['rsrc'])) // sonst Kinder an Baum anhaengen
        {
            $row['kidcount'] = 0;
            $ar_path[] = $row;
        }

        if (is_array($ar_path) && count($ar_path) > 0) {
            $treeArray = array();

            foreach ($ar_path as $key => $element) {
                if (!in_array($element['ID_KAT'], $visitedNodes)) {
                    $visitedNodes[] = $element['ID_KAT'];
                    $children = getNewsCategoryArrayTreeRecursive($element['ID_KAT'], $nest, $visitedNodes, $preSelectedNodes);

                    $treeArray[] = array('key' => $element['ID_KAT'], 'title' => $element['V1'], 'select' => in_array($element['ID_KAT'], $preSelectedNodes), 'children' => $children, 'expand' => true);
                }
            }

            return $treeArray;
        } else {
            return null;
        }
    }
?>
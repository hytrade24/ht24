<?php
/* ###VERSIONSBLOCKINLCUDE### */


  require_once "sys/lib.nestedsets.php";

  function katpath($id, $s_page='anzeigenmarkt', $s_delim=' &raquo; ')
  {
    global $db;
    $db->querynow($sql = $db->lang_select('kat', 't.ID_KAT, LABEL'). '
      left join kat x on x.ID_KAT='. $id. ' and x.ROOT=t.ROOT and x.LFT between t.LFT and t.RGT
      where x.ID_KAT='. $id);
    $sqlres = $GLOBALS['lastresult']['rsrc'];
    $ar_ret = array ();
    $ar_tmp = $GLOBALS['ar_params'];
    array_shift($ar_tmp); array_shift($ar_tmp); array_shift($ar_tmp);
    $s_more = (count($ar_tmp) ? ','. implode(',', $ar_tmp) : '');
#echo ht(dump(array_keys($GLOBALS)));
    while (list($id_kat, $s_label) = mysql_fetch_row($sqlres))
      $ar_ret[] = ($s_page
        ? '<a href="'. $s_page. ','. $id_kat. ','. chtrans($s_label)
          . $s_more. '.htm">'. stdHtmlentities($s_label). '</a>'
        : stdHtmlentities($s_label)
      );
    return implode($s_delim, $ar_ret);
  }

  function kat_browse($root=1, $kat=0, $subtpl=false, $cols=false, $maxkids=false)
  {
    global $db, $nar_systemsettings, $s_lang, $langval;
    static $nar_anzcount = NULL, $s_more = NULL;
$bak = $GLOBALS['ar_query_log'];$GLOBALS['ar_query_log']=array ();$t_start = microtime();
/**/
    if (is_null($s_more))
      $s_more = (($id_search = $GLOBALS['ar_params'][4]) ? ',,'. $id_search : '');
    if (is_null($nar_anzcount)) $nar_anzcount = $db->fetch_nar(
      "select FK_KAT, count(*) from anzeige
        where BF_VIS=3 and STAMP_END>=NOW() and STAMP_START<now()
        group by FK_KAT"
    );
/**/
    $nest = new nestedsets('kat', $root, false);
    $i=$lock=$k=0;
    $c=$level=1;
    $ar=$kids=$path=array ();
    if ($kat > 0)
    {
      $kat2 = $db->fetch1($db->lang_select("kat")." where ID_KAT=".$kat."  and t.ROOT=".$root);
      if (!empty($kat2))
        $where = " and ( u.LFT >= ".$kat2['LFT']." and u.RGT <= ".$kat2['RGT'].") ";
      $path = getKatPath2($kat2['LFT']);
    $nr = count($path);
#    $level = $nr-1;
    $aktuell = $path[$nr-1];
    }
    $sub = getKatOpts($subtpl,$kat,$cols,$maxkids);
    $subtpl=$sub['tpl'];
    $cols=$sub['cols'];
    $maxkids=$sub['kids'];
/** /
    $sql = $nest->nestQuery($where," left join string st on st.S_TABLE='kat' and st.FK=u.ID_KAT
      and u.BF_LANG_KAT=if (u.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(u.BF_LANG_KAT+0.5)/log(2)))
      left join anzeige anz on FK_KAT=t.ID_KAT and anz.BF_VIS=3 and STAMP_END >= NOW() and STAMP_START <= now()",'count(distinct anz.ID_ANZEIGE) as C_ADS,',true);
/*/
    $sql = $nest->nestQuery($where," left join string st on st.S_TABLE='kat' and st.FK=u.ID_KAT
      and u.BF_LANG_KAT=if (u.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(u.BF_LANG_KAT+0.5)/log(2)))",
      '',true);
/**/
    $sql = str_replace('count(*)', 'count(distinct u.ID_KAT)', $sql);
    $res = $nest->query($sql);
#echo ht(dump($GLOBALS['lastresult'])),listtab($db->fetch_table("explain $sql"));
    $all = mysql_num_rows($res);
    $count_kids=0;
$nar_count_path = array ();
#echo ht(dump($nar_anzcount));
    while ($row = mysql_fetch_assoc($res))
    {
$nar_count_path[$n_count_level = $row['level']] = count($ar);
  $row['C_ADS'] = (int)$nar_anzcount[$row['ID_KAT']];
  $row['s_more'] = $s_more;
#echo ht(dump($row));
for($nn=1; $nn<$n_count_level; $nn++)
  if (($idx = $nar_count_path[$nn])<count($ar))
    $ar[$idx]['C_ADS'] += $row['C_ADS'];
#echo '<b>', count($ar), '</b> / ', $n_count_level, '<br>', implode(', ', $nar_count_path), ht(dump($row));

      $k++;
      if ($row['B_VIS'] == 0)
        $lock = $row['RGT'];
      if ($row['LFT'] <= $lock)
        continue;
      $b_cont = $row['level'] != $level;
      if (($maxkids == 'all' || count($kids) < $maxkids) && $row['level']==$level+1 && $maxkids !== 0)
        $kids[] = array ('V1' => $row['V1'], 'ID_KAT' => $row['ID_KAT'], 'C_ADS'=>$row['C_ADS'], 's_more'=>$s_more);
      $nr = count($ar)-1;
#echo $row['level'],',';
      if (!empty($kids))
      {
        $tpl_tmp= new Template('tpl/'.$s_lang.'/browse.sub.htm');
#echo ht(dump($kids));
        $tpl_tmp->addlist('KIDS', $kids, 'tpl/'.$s_lang.'/'.$subtpl.'.kids.htm');
        $ar[$nr]['KIDS'] = $tpl_tmp;
      }
      if ($b_cont) continue;
      if ($k==$all && (($c+1)!=$cols) && (count($ar)+1) > $cols)
      {
        $z=$c;
        if($c+1 > $cols)
          $z=1;
        $addhtml = str_repeat('<td>&nbsp;</td>', ($cols-($z))).'</tr>';
      }
      $kids=array ();
      if ($i==0 || !($i%$cols))
      {
        $c=1;
        $row['newrow']=1;
      }
      else
        $c++;
      if (!(($i+1)%$cols))
        $row['endrow']=1;
      if (!$GLOBALS['katperms']) $GLOBALS['katperms'] = katperm_read();
      if(!isset($GLOBALS['katperms'][$row['ID_KAT']]))
        $row['notallowed']=1;
      else
        $row['notallowed']=0;
      $i++;
      $ar[]=$row;
    }
#echo listtab($ar);

    $tpl_return = new Template('tpl/'.$s_lang.'/'.$subtpl.'.htm');
    $tpl_return->addlist('kategorien', $ar, 'tpl/'.$s_lang.'/'.$subtpl.'.row.htm');
    $tpl_return->addvar('tableEnd', $addhtml);
    if ($path)
    {
      $tpl_return->addvars($aktuell);
      $tpl_return->addvar('CURRENT', $aktuell['ID_KAT']);
      $tpl_return->addlist('path', $path, 'tpl/'.$s_lang.'/browse.katpath.htm');
    }
#die(ht(dump($tpl_return)));
$t_end = microtime();$t = explode(' ', $t_start);$t_start = $t[0]+$t[1];$t = explode(' ', $t_end);$t_end = $t[0]+$t[1];
$t = $t_end - $t_start; $t_sql = 0; foreach($GLOBALS['ar_query_log'] as $row) $t_sql += $row['flt_runtime'];$GLOBALS['ar_query_log'] = array_merge($bak, $GLOBALS['ar_query_log']);
printf('kat_browse: <b>%.3f</b>s, davon <b>%0.3f</b>s SQL<br>', $t, $t_sql);
    if(!empty($ar))
      return $tpl_return;
    return false;
  } // end function kat_browse

  function getKatPath2($lft)
  {
    global $db;
echo "<b>getKatPath2($lft)</b><br />";
    $ar_tmp = $GLOBALS['ar_params']; array_shift($ar_tmp); array_shift($ar_tmp); array_shift($ar_tmp);
    $sql_more = (count($ar_tmp) ? ','. mysql_escape_string(implode(',', $ar_tmp)) : '');
    $ar =$db->fetch_table($db->lang_select('kat', "*, '$sql_more' as s_more")." where  ".$lft." between LFT and RGT
      and ROOT=1 order by LFT ");
#echo ht(dump($ar));
    return $ar;
  }

  function getKatOpts($tpl,$kat,$cols2,$maxkids)
  {
    $level=0;
    include 'cache/kat1.'. $GLOBALS['s_lang']. '.php';

    if (isset($ar_nav[$kat]['level']))
      $level = $ar_nav[$kat]['level'];

    $ar=array ();
    $tp = $GLOBALS['nar_systemsettings']['KATTPL'];
    $cols = $GLOBALS['nar_systemsettings']['KATCOLS'];
    $kids = $GLOBALS['nar_systemsettings']['KATKIDS'];
    if ($tpl)
      $ar['tpl']=$tpl;
    else
    {
      if (isset($tp[$level]))
        $ar['tpl']=$tp[$level];
      else
        $ar['tpl'] = max($tp);
    }
    if ($cols2 !== false)
      $ar['cols']=$cols2;
    elseif (isset($cols[$level]))
      $ar['cols']=$cols[$level];
    else
      $ar['cols'] = max($cols);
    if ($maxkids !== false)
      $ar['kids']=$maxkids;
    elseif (isset($kids[$level]))
      $ar['kids']=$kids[$level];
    else
      $ar['kids'] = max($kids);

#print_r($ar);
    return $ar;
  } // end function getKatOpts
?>

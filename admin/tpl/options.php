<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once "options_check.php";

#$row['format'] = 'select ID_USER, NAME from user order by VORNAME group by #1-3';
#    preg_match('/^\s*(\w+)(\s+(.*))?(\#((\d+)-)?(\d+))?\s*$/Ui', $row['format'], $match);
#die(ht(dump($match)));


if ($_REQUEST['selectplugin']=='')
	$_REQUEST['selectplugin']='MARKTPLATZ';

  $nar_art = $db->fetch_nar("select plugin,plugin from `option` group by plugin order by plugin");
  $ar = array ();
  /*
    foreach ($nar_art as $v) $ar[] = '
    <option '. ($v==$_REQUEST['selectplugin'] ? 'selected="selected" ' : ''). 'value="'. $v. '">'. $v. '</option>';
   */
    
        foreach ($nar_art as $v) $ar[] = '
    <div  '. ($v==$_REQUEST['selectplugin'] ? 'class="reiterAktiv" ' : 'class="reiterPassiv" '). '"> <img src="skin/e-navi-blau.gif" height="7" width="11">
 <a href="index.php?lang='.$s_lang.'&page=options&selectplugin='. $v. '" title="Bitte wählen ">'. $v. '</a> </div>';
    

  $tpl_content->addvar('opts_art', $ar);
  
  if($_REQUEST['selectplugin'] != "")
  {
    #if($_REQUEST['selectplugin'] == "*alle*")
    #$where = "";
    #else
    $where = "where plugin='".$_REQUEST['selectplugin']."'";

     if($_REQUEST['typ'] != "")
         $where =  $where." and typ='".$_REQUEST['typ']."'";

    $tpl_content->addvar('selectplugin', $_REQUEST['selectplugin']);
    $data = $db->fetch_table("select * from `option` ".$where." order by orderfeld, plugin, typ");
  }
  else
  {
    reset($nar_art);
	$where = current($nar_art);

    $data = $db->fetch_table("select * from `option` where plugin='".$where."' order by orderfeld, plugin, typ");
  }
#  $data = $db->fetch_table("select * from `option` order by plugin, typ");
  # noch nicht benutzte Felder: orderfeld, ifeld, sysfunction
  
  if (count($_POST) && $_POST['set'])
  {
    $msg = $err = array ();
    foreach($_POST['set'] as $plugin=>$sub)
      foreach($sub as $typ=>$value)
      {
        $value = checkOptionValue($plugin, $typ, $value);
        if (is_array ($value)) $value = implode(',', $value);
        for ($i=count($data); $i>=0; $i--)
          if ($plugin==$data[$i]['plugin'] && $typ==$data[$i]['typ'])
          {
#echo "$plugin:$typ: ". $data[$i][value]. " -&gt; $value<br />";
            if ($data[$i]['value'] != $value)
            {
              $db->querynow("update `option` set value='". mysql_escape_string($value)."'
                where plugin='$plugin' and typ='$typ'");
#echo ht(dump($lastresult));
              $data[$i]['value'] = $value;
              $msg[] = $msg_updated. ": $plugin:$typ -&gt; ". stdHtmlentities($value);
            }
            break;
          }
        if ($i<0) $err[] = $err_notfound. ": $plugin > $typ";
      }
    if (count($msg))
    {
      $data = $db->fetch_table("select * from `option` order by plugin, typ");
      $ar = array ();
      foreach ($data as $row)
        $ar[$row['plugin']][$row['typ']] = $row['value'];
      $s_code = '<?'. 'php $nar_systemsettings = '. php_dump($ar, 0). '; ?'. '>';
      $fp = fopen('../cache/option.php', 'w');
      fputs($fp, $s_code);
      fclose ($fp);
      
      $data = $db->fetch_table("select * from `option` ".$where." order by plugin, typ");
    }
    else
      $msg[] = $msg_nochange;
    $tpl_content->addvar('msg', implode('<br />', $msg));
    $tpl_content->addvar('err', implode('<br />', $err));
  }

  $liste = $ar_chk = array ();
  foreach($data as $i=>$row)
  {
    preg_match('/^\s*(\w+)(\s+(.*))?(\#((\d+)-)?(\d+))?\s*$/i', $row['format'], $match);
/*
  $1: Kommando
  $3: Parameterliste
  $6: von Zeichen
  $7: bis Zeichen
*/
    $b_multi = false;
    $s_name = 'set['. $row['plugin']. ']['. $row['typ']. ']';
    $s_subtpl = 'text';
    $row['minlen'] = (int)$match[6];
    $row['maxlen'] = (int)$match[7];
    switch($s_type = strtolower($match[1]))
    {
      // andere Felder:
//      case 'date':
      case 'check':
        $s_subtpl = $s_type;
        break;
      case 'list':
        $arOptions = array();
        foreach (explode(" ", $match[2]) as $optionIndex => $optionValue) {
          if (empty($optionValue)) {
            continue;
          }
          $arOptions[ $optionValue ] = $optionValue;
        }
        $row['input'] = nar2select('name="'.$s_name.'" id="opt'. $i. '"', $row['value'], $arOptions);
        $row['default_text'] = $row['default_value'];
        $s_subtpl = 'select';
        break;
      case 'mselect':
        $b_multi = true;
      case 'select':
        $nar_data = $db->fetch_nar(substr(trim($match[0]), $b_multi));
        $ar_tmp_v = array ();
        $ar_tmp_k = explode(',', $row['default_value']);
        foreach ($ar_tmp_k as $k) $ar_tmp_v[] = $nar_data[$k];

        $row['input'] = nar2select(
          ($b_multi ? 'multiple ' : ''). 'name="'. $s_name. ($b_multi ? '[]' : ''). '" id="opt'. $i. '"',
          ($b_multi ? explode(',', $row['value']): $row['value']), $nar_data
        );
        $row['default_text'] = implode('; ', $ar_tmp_v);
        $row['b_multi'] = $b_multi;
        $s_subtpl = 'select';
        break;
      // "freie" Eingabe:
      case 'num':
        if (preg_match('/^(-?)(\d*)(\.(\d*))?$/', $match[3], $submatch))
          if (strlen($submatch[2]) && (!strlen($submatch[3]) || strlen($submatch[4])))
            $row['maxlen'] = strlen($submatch[1])
              + ((int)$submatch[2])
              + (strlen($submatch[3]) ? 1 + (int)$submatch[4] : 0);
        // fallthrough
      case 'date':
      case 'url':
      case 'uri':
      case 'email':
      case 'iv':
        $ar_chk[] = "\n  if (t = chktxt('opt$i', '$s_type', '$match[3]', $row[minlen], $row[maxlen]))
    s += '\\n' + t;";
        break;
      case 'text':
      default:
        break;
    }
    $tpl_tmp = new Template('tpl/de/options.'. $s_subtpl. '.htm', 'option');
    $tpl_tmp->addvar('even', !($i&1));
    $tpl_tmp->addvar('i', $i);
    $tpl_tmp->addvars($row);
    $liste[] = $tpl_tmp;
  }
#echo ht(dump($ar_chk));
  $tpl_content->addvar('liste', $liste);
  $tpl_content->addvar('check', $ar_chk);

?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */


function attrib_opts_order($s_typ)
{
  static $ret = array ();
  global $s_sqlver;
  if (!$s_sqlver)
  {
    $tmp = $GLOBALS['db']->fetch1("show variables like 'version'");
    $s_sqlver = $tmp['Value'];
  }
  if (!$s_order = $res[$s_typ])
  {
    if ('FLT'==$s_typ && version_compare($s_sqlver, '5.0.8', '<'))
      $s_order = '1';
    elseif (version_compare($s_sqlver, '4.0.2', '<'))
      $s_order = 'length(VALUE), VALUE';
    elseif ('FLT'==$s_typ)
      $s_order = 'convert(VALUE,decimal)';
    elseif ('INT'==$s_typ)
      $s_order = 'convert(VALUE,signed)';
    else
      $s_order = 'POS';
    return $res[$s_typ] = $s_order;
  }
  else
    return $s_order;
}

  if ($id = (int)$_REQUEST['ID_ATTR'])
    $attr = $db->fetch1($db->lang_select('attr'). ' where ID_ATTR='. $id);
  else
    $attr = $db->fetch_blank('attr');

/*
  // Kategoriepfad
  $kat_lft = (($fk_kat = (int)$attr['FK_KAT'])
    ? $db->fetch_atom("select LFT from kat where ROOT=1 and ID_KAT=". $fk_kat)
    : 1
  );
  $s_pfad = implode(' &gt; ', $db->fetch_nar(
    $s = $db->lang_select('kat', 'ID_KAT, LABEL'). '
    where ROOT=1 and '. ($kat_lft>1
      ? 'LFT>1 and '. $kat_lft. ' between LFT and RGT order by LFT'
      : 'LFT=1'
    )
  ));
  $tpl_content->addvar('katpfad', $s_pfad);
*/

  // speichern
  if (count($_POST))
  {
    $err = array ();
    recurse($_POST, '$value=trim($value)');
#echo ht(dump($_POST));
    if (!$_POST['V1'])
      $err[] = 'Kein Label angegeben.';
    if ($_POST['param'])
      $_POST['PARAMS'] = serialize($_POST['param']);
    if (!count($err))
      if ($id = $db->update('attr', $_POST))
      {
        if (($newval = trim($_POST['newval'])) || ($newtext = trim($_POST['newtext'])))
        {
          if ($lu = (int)($attr ? $attr['LU_TYPE'] : $_POST['LU_TYPE']))
          {
            $s_typ = $db->fetch_atom("select VALUE from lookup where art='attr_type' and ID_LOOKUP=". $lu);
            // Optionen / Bereiche
            $put = array ('FK_ATTR'=>$id);
            if ($subid = (int)$_POST['ID_ATTR_OPTION'])
              $put['ID_ATTR_OPTION'] = $subid;
            if ('INT'==$s_typ || 'FLT'==$s_typ)
            {
              // Bereich
              if (!$newval)
                $err[] = 'Sie haben keinen Maximalwert angegeben.';
              else
                $put['VALUE'] = $newval;
              if (!$newtext)
                $err[] = 'Bitte geben Sie ein Label f&uuml;r den Bereich ein.';
              else
                $put['V1'] = $newtext;
            }
            elseif ('SEL'==$s_typ || 'MSEL'==$s_typ)
            {
              // Option: beim Bearbeiten nur Label, keine Aenderung des Werts!!!
              if (!$subid)
              {
                if (!$newval)
                  $err[] = 'Sie haben keinen Wert angegeben.';
                else
                  $put['VALUE'] = $newval;
              }
              if (!$newtext)
                $err[] = 'Bitte geben Sie ein Label f&uuml;r die Option ein.';
              else
                $put['V1'] = $newtext;
              $put['POS'] = 1+$db->fetch_atom("select max(POS) from attr_option
                where FK_ATTR=". $id);
            }
            if (count($put) && !count($err))
            {
              // Params setzen
              $db->update('attr_option', $put);
#die(ht(dump($lastresult)));
              // Bereiche neu durchsortieren
              if ('INT'==$s_typ || 'FLT'==$s_typ)
              {
                $nar = $db->fetch_nar("select ID_ATTR_OPTION, POS from
                  attr_option where FK_ATTR=". $id. " order by ". attrib_opts_order($s_typ));
                $p=1;
                foreach($nar as $subid=>$pos)
                  $db->query("update attr_option set POS=". ($p++). "
                    where ID_ATTR_OPTION=". $subid);
                $db->submit();
              }
            }
          }
        }
        if (!count($err))
          forward('index.php?page=attr_edit&ID_ATTR='. $id);
        else
          $attr = array_merge($attr, $_POST);
      }
      else
      {
        $err[] = 'Datenbankfehler: '. $lastresult['str_error'];
        $attr = array_merge($attr, $_POST);
      }
#echo ht(dump($attr));
    $tpl_content->addvar('err', implode('<br />', $err));
  }

  $tpl_content->addvars($attr);
  if ($attr['PARAMS'])
    $tpl_content->addvars(unserialize($attr['PARAMS']), 'param_');

  if ($lu = (int)$attr['LU_TYPE'])
  {
    $s_typ = $db->fetch_atom("select VALUE from lookup where art='attr_type' and ID_LOOKUP=". $lu);

    if ($subid = $_REQUEST['ID_ATTR_OPTION'])
    {
      switch ($do=$_REQUEST['do'])
      {
        case 'up':
        case 'dn':
          $pos = $db->fetch_atom("select POS from attr_option where ID_ATTR_OPTION=". $subid);
          $res = $db->querynow("select ID_ATTR_OPTION, POS from attr_option
            where FK_ATTR=". $id. " and POS". ('up'==$do ? '<' : '>'). $pos. "
            order by POS ". ('up'==$do ? 'desc ' : ''). "limit 0,1");
          list($xid, $xpos) = mysql_fetch_row($res['rsrc']);

#echo ht(dump($lastresult));die("$subid/$pos -- $xid/$xpos");
          if ($xpos)
          {
            $db->query("update attr_option set POS=". $pos. " where ID_ATTR_OPTION=". $xid);
            $db->query("update attr_option set POS=". $xpos. " where ID_ATTR_OPTION=". $subid);
            $db->submit();
          }
          forward('index.php?page=attr_edit&ID_ATTR='. $id);
          break;
        case 'rm':
          $db->delete('attr_option', $subid);
          forward('index.php?page=attr_edit&ID_ATTR='. $id);
      }

//      $n_reiter = 3;
      $subrow = $db->fetch1($db->lang_select('attr_option'). " where ID_ATTR_OPTION=$subid");
      $tpl_content->addvar('ID_ATTR_OPTION', $subid);
      $tpl_content->addvar('newval', $subrow['VALUE']);
      $tpl_content->addvar('newtext', $subrow['V1']);
//      echo ht(dump($subrow));
    }

    $tpl_content->addvar('typ_'. $s_typ, true);
    $tpl_content->addvar('div2', $b_div2 = in_array ($s_typ, array ('INT', 'FLT', 'VA', 'TXT')));
    $tpl_content->addvar('div3', $b_div3 = in_array ($s_typ, array ('SEL', 'MSEL', 'INT', 'FLT')));

    if ($id && $b_div3)
    {
      $optcount = (int)$db->fetch_atom("select count(*) from attr_option where FK_ATTR=". $id);
      $s_order = attrib_opts_order($s_typ);
      $ar_opts = $db->fetch_table($db->lang_select('attr_option', '*, 1 as typ_'. $s_typ. ', '. $optcount. ' as n'). '
        where FK_ATTR='. $id. '
        order by '. $s_order
      );
      $tpl_content->addlist('opts', $ar_opts, 'tpl/de/attr_edit.optrow.htm');
      $tpl_content->addvar('optcount', $optcount);
    }
  }
#$tpl_content->vars['div2'] = $tpl_content->vars['div3'] = $tpl_content->vars['ID_ATTR'] = 1;
#$tpl_content->addvar('typ_'. $s_typ, 1);
/*
INT     Einheit (multilang)
        Bereiche fuer Suche (min-Wert, Label (multilang))
FLT     Nachkommastellen (0..10)
        Einheit (multilang)
        Bereiche fuer Suche (min-Wert, Label (multilang))
VCH     max. Laenge (<=255)
TXT     max. Laenge (<=65535)
SEL     Optionen definieren (Value, Label (multilang))
*/
?>
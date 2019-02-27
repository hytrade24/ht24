<?php
/* ###VERSIONSBLOCKINLCUDE### */


$nar_attr_types = $db->fetch_nar("select ID_LOOKUP, VALUE from lookup where art='attr_type'");

function attrib_input($id_attr, $value)
{
  global $db;
  $attr = $db->fetch1($db->lang_select('attr'). ' where ID_ATTR='. $id_attr);
  $ar_params = unserialize($attr['PARAMS']);
#echo "<br /><b>attrib_input $id_attr</b> ($value) ", ht(dump($attr)), '<br />';
  $s_input = 'id="attr'. $id_attr. '" name="attr['. $id_attr. ']" ';
  switch ($s_typ = $GLOBALS['nar_attr_types'][$attr['LU_TYPE']])
  {
    case 'INT':
    case 'FLT':
      // type=text maxlen
      // Ziffern, evtl. punkt/komma
/**/
/*
$s_order = attrib_opts_order($s_typ);
if ('1'==$s_order)
      {
        $res = $db->querynow('select VALUE from attr_option where FK_ATTR='. $id_attr);
        $s_maxval = 0.0;
        while (list($v) = mysql_fetch_row($res['rsrc']))
          $s_maxval = max($maxval, (float)$v);
      }
else
*/
        $s_maxval = $db->fetch_atom('select VALUE from attr_option
          where FK_ATTR='. $id_attr. ' order by POS desc limit 1');
/*/
      $tmp = $db->fetch1("show variables like 'version'");
      if ('FLT'==$s_typ && version_compare($tmp['Value'], '5.0.8', '<'))
      {
        $res = $db->querynow('select VALUE from attr_option where FK_ATTR='. $id_attr);
        $maxval = 0.0;
        while (list($v) = mysql_fetch_row($res['rsrc']))
          $maxval = max($maxval, (float)$v);
      }
      else
      {
        if (version_compare($tmp['Value'], '4.0.2', '<'))
          $s_order = 'length(VALUE), VALUE';
        elseif ('FLT'==$s_typ)
          $s_order = (version_compare($tmp['Value'], '5.0.8', '<')
            ? 'convert(VALUE,signed), length(VALUE), VALUE'
            : 'convert(VALUE,decimal)'
          );
        else
          $s_order = 'convert(VALUE,signed)';
        $s_maxval = $db->fetch_atom('select VALUE from attr_option
          where FK_ATTR='. $id_attr. ' order by '. $s_order. ' limit 1');
      }
/**/
      if ('FLT'==$s_typ)
      {
        if ($tmp = (int)$ar_params['decimals'])
          $s_maxval = sprintf('%.'. $tmp. 'f', $s_maxval);
        elseif (!strpos('.', $s_maxval))
          $s_maxval .= '.';
      }
      return '<input type="text" '. $s_input. 'value="'. stdHtmlentities($value)
        . ('INT'==$typ ? '" maxlength="'. strlen($s_maxval) : '')
        . '" maxval="'. $s_maxval. '" />'. ($attr['V2'] ? ' '. $attr['V2'] : '');
      break;
    case 'VA':
      // type=text maxlen
      return '<input type="text" '. $s_input. 'value="'. stdHtmlentities($value)
        . '" maxlength="'. (($tmp = $ar_param['maxlen']) ? $tmp : 255). '" />';
      break;
    case 'TXT':
      // textarea
      return '<textarea '. $s_input. ' class="inputfull" rows="20" cols="55" maxlength="'
        . (($tmp = $ar_param['maxlen']) ? $tmp : 255). '">'. stdHtmlentities($value). '</textarea>';
      break;
    case 'MSEL':
      $s_input = str_replace(']"', '][]"', $s_input). ' multiple size="6"';
      $s_null = '<input type="hidden" name="attr['. $id_attr. ']" value="" />';
    case 'SEL':
      $data = $db->fetch_nar($db->lang_select(attr_option, 'ID_ATTR_OPTION,LABEL'). '
        where FK_ATTR='. $id_attr. ' order by POS');
      if ('SEL'==$s_typ)
      { // Schleife noetig, da array_unshift die keys schluckt
        $bak = $data;
        $tmp = get_messages('FORMS');
        $data = array (''=>'-- '.  $tmp['select_none']. ' --');
        foreach ($bak as $k=>$v) $data[$k] = $v;
      }
      // select (multiple)
      return nar2select($s_input, (is_array ($value) ? $value : explode(',', $value)), $data);
      // size=?
      break;
    default:
      return 'unknown field type';
      break;
  }
}

function attrib_print($id_attr, $value)
{
  global $db;
  $attr = $db->fetch1($db->lang_select('attr'). ' where ID_ATTR='. $id_attr);
  switch ($GLOBALS['nar_attr_types'][$attr['LU_TYPE']])
  {
    case 'INT':
    case 'FLT':
      // plain
      return $value. ($attr['V2'] ? ' '. $attr['V2'] : '');
      break;
    case 'VA':
      // htm()
      return stdHtmlentities($value);
      break;
    case 'TXT':
      // nl2br(htm())
      return nl2br(stdHtmlentities($value));
      break;
    case 'SEL':
    case 'MSEL':
      return $db->fetch_atom($db->lang_select('attr_option', 'LABEL'). '
        where ID_ATTR_OPTION='. $value);
      break;
    default:
      return 'unknown field type';
      break;
  }
}

function attrib_search($id_attr)
{
}

function attrib_store($id_attr, $value, $fk, $s_ftable='anzeige')
{
  global $db;
  $id_attr = (int)$id_attr;
  $attr = $db->fetch1('select * from attr where ID_ATTR='. $id_attr);
  if (!$attr)
    return array ('int_result'=>-1, 'str_error'=>'no such attribute');
  $s_typ = $GLOBALS['nar_attr_types'][$attr['LU_TYPE']];
  #echo "TYP: ".$s_typ."<br />";
  switch ($s_typ)
  {
    case 'VA':
    case 'TXT':
      $value = "'". mysql_escape_string($value). "'";
    case 'INT':
    case 'FLT':
	  $s_table = 'attr_val_'. strtolower($s_typ);
      $s_where = '
        where FK_ATTR='. $id_attr. " and S_TABLE='". $s_ftable. "' and FK=". (int)$fk;
      $n = $db->fetch_atom('select count(*) from '. $s_table. $s_where);
      $hm = $db->querynow($n
        ? 'update '. $s_table. ' set VALUE='. $value. $s_where
        : 'insert into '. $s_table. " (FK_ATTR, S_TABLE, FK, VALUE)
          values ($id_attr, '$s_ftable', $fk, $value)"
      );
	  #echo ht(dump($GLOBALS['lastresult']));
      break;
    case 'SEL':
    case 'MSEL':
      if (!is_array ($value))
        $value = ((''===$value) ? array () : array ($value));
      $db->query('delete from attr_val_sel
        where FK_ATTR='. $id_attr. " and S_TABLE='". $s_ftable. "' and FK=". (int)$fk);
      if (count($value))
        $db->query($s="insert into attr_val_sel (FK_ATTR, S_TABLE, FK, VALUE)
          select FK_ATTR, '". $s_ftable. "', ". $fk. ", ID_ATTR_OPTION from attr_option
            where ID_ATTR_OPTION in (". implode(', ', $value). ') order by POS');
      $db->submit();
      return $GLOBALS['lastresult'];
      break;
    default:
      return array ('int_result'=>-2, 'str_error'=>'unknown field type');
      break;
  }
  #echo ht(dump($GLOBALS['lastresult']));
}
?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */


#die("was wird das?");
  if (count($_POST) && 'sv'==$do)
  {
    $s_ftable = $_POST['tbl'];
    $bak = $langval;
    $langval = $_POST['editlang'];
    $data = array (
      'ID_'. strtoupper($s_ftable) => $_POST['id'],
      'V1'=> $_POST['V1'],
      'V2'=> $_POST['V2'],
      'T1'=> $_POST['T1']
    );
  #die(ht(dump($data)));
    $id = $db->update($s_ftable, $data);
    $langval = $bak;
    forward('index.php?frame=popup&page=trans_edit&tbl='. $s_ftable. '&id='. $id);
  }

  include 'tpl/trans_params.php'; // function flabel, $id_lang0, $id_lang1

# $nar_str_fields

  if (($s_ftable = $_REQUEST['tbl']) && ($fk=(int)$_REQUEST['id']))
  {
    $tpl_content->addvar('edit_id', $fk);
    $tpl_content->addvar('edit_tbl', $s_ftable);
    $tpl_content->addvar('edit_lbl', flabel($s_ftable));

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

    $ar_data = $ar_lang0 = $ar_lang1 = array ();

    $data = $db->fetch1("select * from `$s_ftable` where $s_pk=$fk");
    foreach ($data as $k=>$v) if (!preg_match('/^BF_LANG/', $k))
      $ar_data[] = '<tr>
    <td><b>'. $k. '</b></td>
    <td>'. val_convert($v). '</td>
  </tr>';
    $tpl_content->addvar('itemdata', $ar_data);

    $ar_null_fields = array_diff(array ('V1', 'V2', 'T1'), getstrfields($s_ptable, $s_ftable));

    $lastresult = $db->querynow($db->lang_select('lang', 't.ID_LANG, t.BITVAL, LABEL, g.*'). "
      left join $s_ptable g on g.S_TABLE='$s_ftable' and g.FK=$fk and (g.BF_LANG & t.BITVAL)
      where ID_LANG in ($id_lang0, $id_lang1)");

    while ($row = mysql_fetch_assoc($lastresult['rsrc']))
      if ($row['ID_LANG']==$id_lang1)
        $tpl_content->addvars($row, 'E1_');
      else
      {
        $tpl_content->addvars($row, 'E0_');
        foreach ($ar_null_fields as $v)
          $tpl_content->addvar('null_'. $v, true);
        $tpl_content->addvar('null_v1', is_null($row['V1']));
        $tpl_content->addvar('null_v2', is_null($row['V2']));
        $tpl_content->addvar('null_t1', is_null($row['T1']));
      }
    $tpl_content->addvar('L0_ID_LANG', $id_lang0);
    $tpl_content->addvar('L1_ID_LANG', $id_lang1);
  }
?>
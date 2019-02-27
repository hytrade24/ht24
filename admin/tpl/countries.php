<?php
/* ###VERSIONSBLOCKINLCUDE### */



// _POST
  if ($_POST['V1']!="")
  {
    $_POST['ID_COUNTRY'] = $db->update('country', $_POST);
  }

// Kopfzeile
  if (!($order = $_REQUEST['order']))
  {
    $order = 'ID_COUNTRY';
    $dir =  'asc';
  }
  else
    $dir = $_REQUEST['dir'];
  $nar_headfields = array (
    'ID_COUNTRY'=>'ID',
    'CODE'=>'CODE',
    'V1'=>'Name',
  );
  $ar = array ();
  $cols=0;
  foreach ($nar_headfields as $field=>$label)
  {
    $cols++;
  $fieldid = preg_replace('[^a-z]', '', $field);
    $ar[] = '
      <th><strong>'. $label. ' <img src="gfx/dir.'. ($field==$order ? $dir : 'none'). '.gif" width="14" height="22" border="0" align="absmiddle" usemap="#Map'. $fieldid. '" /></strong>
                        <map name="Map'. $fieldid. '" id="Map'. $fieldid. '">
                          <area shape="rect" coords="0,0,14,11" href="index.php?nav='. $id_nav. '&order='. $field
          . '&dir=asc" alt="Aufsteigend sortieren" />
                          <area shape="rect" coords="0,11,14,22" href="index.php?nav='. $id_nav. '&order='. $field
          . '&dir=desc" alt="Absteigend sortieren"/>
                        </map></th>';
  }
  $tpl_content->addvar('head', $ar);
  $tpl_content->addvar('cols', $cols+1);

  // Liste
  $ar = $db->fetch_table("select t.ID_COUNTRY,t.CODE, s.V1
							from `country` t
							left join string s
							on s.S_TABLE='country' and s.FK=t.ID_COUNTRY and s.BF_LANG=if(t.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG+0.5)/log(2)))
							order by ".$order." ".$dir."");

  $ar_liste = array ();
  foreach ($ar as $i=>$row)
  {
    $fl_this = ($_REQUEST['ID_COUNTRY']==$row['ID_COUNTRY']);
    $tpl_row = new Template('tpl/de/countries.'. ($fl_this ? 'inputrow' : 'row'). '.htm');
    $tpl_row->addvars(($fl_this && count($_POST) ? $row = array_merge($row, $_POST) : $row));
    if ($fl_this)
    {
      $tpl_row->addvar('order_hid', '
  <input type="hidden" name="order" value="'. $order. '" />
  <input type="hidden" name="dir" value="'. $dir. '" />');
    }
    $tpl_row->addvar('i', $i);
    $tpl_row->addvar('even', 1-($i&1));
#    $tpl_row->addvar('pagepath', $str_ident_path);
    $tpl_row->addvar('order_ref', "&order=$order&dir=$dir");
    $ar_liste[] = $tpl_row;
  }
  $tpl_content->addvar('liste', $ar_liste);
  
?>
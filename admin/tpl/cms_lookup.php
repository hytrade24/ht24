<?php
/* ###VERSIONSBLOCKINLCUDE### */


if ($_REQUEST['DEL']=='del_lookup')
{
    echo "delete";
    $db->querynow("delete from string where S_TABLE ='lookup' and FK = ".$_REQUEST['ID_LOOKUP']);
    $db->querynow("delete from lookup where ID_LOOKUP = ".$_REQUEST['ID_LOOKUP']);

    die(forward( "index.php?page=cms_lookup" ));
}



// _POST
if ($_POST['V1']!="")
{
    $_POST['ID_LOOKUP'] = $db->update('lookup', $_POST);
}





if ($_REQUEST['selectart']=='')
    $_REQUEST['selectart']='ANREDE';


// Kopfzeile
if (!($order = $_REQUEST['order']))
{
    $order = 'F_ORDER';
    $dir =  'asc';
}
else
    $dir = $_REQUEST['dir'];

$nar_headfields = array (
    'ID_LOOKUP'=>'ID',
    'f_system'=>'System?',
    'art'=>'Art',
    'VALUE'=>'Wert',
    'V1'=>'Label',
    'F_ORDER'=>'Sortierung'
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
        . '&dir=asc'. ($_REQUEST['selectart']!="" ? '&selectart='.$_REQUEST['selectart'].'' : '') .'" alt="Aufsteigend sortieren" />
                      <area shape="rect" coords="0,11,14,22" href="index.php?nav='. $id_nav. '&order='. $field
        . '&dir=desc'. ($_REQUEST['selectart']!="" ? '&selectart='.$_REQUEST['selectart'].'' : '') .'" alt="Absteigend sortieren"/>
                    </map></th>';
    /*
      '. $label. ' <a href="index.php?nav='. $id_nav. '&order='. $field
          . '&dir='. $newdir. '"> <img align="absmiddle" src="gfx/dir.'. ($field==$order ? $dir : 'none'). '.gif" border="0"></a>&nbsp;</th>';
    */
}
$tpl_content->addvar('head', $ar);
$tpl_content->addvar('cols', $cols+1);

// new ...

$nar_art = $db->fetch_nar("select art,art from lookup group by art order by art");
$ar = array ();
foreach ($nar_art as $v)
    $ar[] = '<option '. ($v==$_REQUEST['selectart'] ? 'selected="selected" ' : ''). 'value="'. $v. '">'. $v. "</option>\n";
$tpl_content->addvar('opts_art', $ar);

if($_REQUEST['selectart'] == "*alle*")
    $where = "";
else
    $where = "where art='".$_REQUEST['selectart']."'";

// Liste
if($_REQUEST['selectart'] != "") {
    $ar = $db->fetch_table("select t.*, s.V1, s.V2, s.T1
	   from `lookup` t left join string s on s.S_TABLE='lookup' and s.FK=t.ID_LOOKUP and s.BF_LANG=if(t.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG+0.5)/log(2)))
	   ".$where."
	   order by ".$order." ".$dir."");
}
else
{
    reset($nar_art);
    $where = current($nar_art);
    $ar = $db->fetch_table("select t.*, s.V1, s.V2, s.T1 from `lookup` t left join string s on s.S_TABLE='lookup' and s.FK=t.ID_LOOKUP and s.BF_LANG=if(t.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG+0.5)/log(2))) where art='".$where."' order by ".$order." ".$dir."");
}
#echo $db->lang_select('lookup'). "order by $order $dir";
/*
  if (!$_REQUEST['ID_LOOKUP'])
    array_unshift($ar, $data = array (count($_POST) ? $_POST : $db->fetch_blank('lookup')));
*/
$ar_liste = array ();
foreach ($ar as $i=>$row)
{
    $fl_this = ($_REQUEST['ID_LOOKUP']==$row['ID_LOOKUP']);
    $tpl_row = new Template('tpl/de/cms_lookup.'. ($fl_this ? 'inputrow' : 'row'). '.htm');
    $tpl_row->addvars(($fl_this && count($_POST) ? $row = array_merge($row, $_POST) : $row));
    if ($fl_this)
    {
        $ar = array ();
        foreach ($nar_art as $v) $ar[] = '
        <option '. ($v==$row['art'] ? 'selected ' : ''). 'value="'. $v. '">'. $v. '</option>';
        $tpl_row->addvar('opts_art', $ar);
        $tpl_row->addvar('selectart', $_REQUEST['selectart']);
        $tpl_row->addvar('order_hid', '
  <input type="hidden" name="order" value="'. $order. '" />
  <input type="hidden" name="dir" value="'. $dir. '" />');
    }
    $tpl_row->addvar('i', $i);
    $tpl_row->addvar('even', 1-($i&1));
#    $tpl_row->addvar('pagepath', $str_ident_path);
    $tpl_row->addvar('order_ref', "&order=$order&dir=$dir".($_REQUEST['selectart']!="" ? '&selectart='.$_REQUEST['selectart'].'' : '')."");
    $ar_liste[] = $tpl_row;
}
$tpl_content->addvar('liste', $ar_liste);
?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */


define (PATHICOWITHGD, false);
function pathicon($path)
{
    global $ab_baseurl;

  if (PATHICOWITHGD)
  {
    $items = array ('up', 'dn', 'lt', 'rt', 'folder', 'ofolder', 'root', 'minus', 'plus');
    $tmp = array ();
    for ($i=0; $path; $i++)
    {
      if ($path & 1) $tmp[] = $items[$i];
      $path = $path >> 1;
    }
    $src = "path.php?path=". implode('-', $tmp);
  }
  else
    $src = "path.$path.png";
  return '
  <td width="19" height="17"><img src="'.$ab_baseurl.'gfx/'. $src. '" width="19" height="17"></td>';
}

function tree_fetch(&$ar_data, $s_table = 'nav', $s_where='1', $s_pos='`POS`', $s_fields='', $s_joins='')
{
#echo "Aufruf mit ID: ".$s_where."<hr />";
  global $db, $langval,
    $ar_ids, $nar_children, $ar_nokids;
  #echo ht(dump($ar_ret));
  if ($ar_ret)
    return;
  // go
  $ar_data = $ar_ids = $nar_children = array ();
  $s_pkey = '`ID_'.strtoupper($s_table). '`';
  if (0!==strpos('`', $s_pos)) 
    $s_pos = '`'. $s_pos. '`';
  /*
  $ar_tmp = $db->fetch_table($db->lang_select($s_table). "
	where ". $s_where. "
    group by ". $s_pkey. "
    order by PARENT,". $s_pos, $s_pkey
  );
  */
  $ar_tmp=array();
  #die($db->lang_select($s_table));
  $res = $db->querynow($query = $db->lang_select($s_table,"*".$s_fields).$s_joins."
	where ". $s_where. "
    group by ". $s_pkey. "
    order by PARENT,". $s_pos, $s_pkey
  );
  #die($query);
  if(!$res['rsrc'])
    ;//die(ht(dump($res)));
  while($row = mysql_fetch_assoc($res['rsrc']))
  {
    $ar_tmp[$row['ID_COMMENT']] = $row;
  }
#echo "query: ".ht(dump($GLOBALS['lastresult']))."<hr />";  
#echo "Ergebnis: ".ht(dump($ar_tmp))."<hr />";
#echo '<h2>', $s_table, '</h2>';
#echo ht(dump($GLOBALS['lastresult']));
  foreach($ar_tmp as $id=>$row)
  {
#echo $id."<br />";
	$ar_data[$id] = $row;
    $nar_children[$row['PARENT']][] = $id;
    $ar_ids[] = $id;
  }
  $ar_nokids = array_diff($ar_ids, array_keys($nar_children));
#echo ht(dump($ar_nokids));  
} // function tree_fetch

$maxlevel = 0;
function tree_show(&$ar_data, $parent=0, $level=0, $s_rowtpl='tpl/de/nav_edit.row.htm',
  $t_ref='index.php?frame=content&page={curpagealias}&id_nav=$id&')
{
  global $nar_children, $maxlevel;
  static $leveldone = array (false), $even=true;
  if ($level>$maxlevel)
    $maxlevel = $level;
  $ret = array ();
  $count = count($nar_children[$parent]);
#echo listtab($ar_data),'<hr /><hr />';
#  $s_dir =
  if ($nar_children[$parent])
  {
#echo "hallllllo????"; 
  foreach($nar_children[$parent] as $i=>$id)
  {
#echo "hallo: ".$i;
#$ar_data[$id]['vis1'] = !!((int)$ar_data[$id]['POS']);$ar_data[$id]['noparent'] = !$parent;
#if ($parent)  $ar_data[$id]['parent-vis'] = $data[$parent]['visible'];
$ar_data[$id]['visible'] =  ((int)$ar_data[$id]['POS']) && (!$parent ||  $ar_data[$parent]['visible']);
    $row = $ar_data[$id];
#echo ht(dump($row));echo ht(dump($ar_data[$parent])),'<hr />';
#ob_end_flush();echo "<hr /><b>$id</b>", ht(dump($leveldone)), '<hr />';ob_start();
    $ar_path = array ();
    for ($x=0; $x<$level; $x++)
      $ar_path[] = pathicon(($leveldone[$x] ? 0 : 3));
    $ar_path[] = pathicon(($i<$count-1 ? 1+2+8 : 1+8));
    $ar_path[] = pathicon(($nar_children[$id] ? 2+4+16 : 4+16));
#echo ht(dump($row));
    eval ('$href = "$t_ref";');
    $tpl_tmp = new Template($s_rowtpl);
    $tpl_tmp->addvars($row);
	$tpl_tmp->addvar("ID_NEWS", ($GLOBALS['smode'] == 'newscomment' ? $GLOBALS['id'] : NULL));
    $tpl_tmp->addvar('level', $level);
    $tpl_tmp->addvar('path', $ar_path);
#    $tpl_tmp->addvar('id_group', $GLOBALS['id_group']);
    $tpl_tmp->addvar('even', (int)$even);
    $tpl_tmp->addvar('isfirst', !$i);
    $tpl_tmp->addvar('islast', $i+1==$count);
    $tpl_tmp->addvar('kidcount', $nar_children[$id] && count($nar_children[$id])>0);
    $even = !$even;
    $ret[] = $tpl_tmp;

    $leveldone[$level] = ($i==$count-1);
    if ($nar_children[$id])
      $ret = array_merge($ret, tree_show($ar_data, $id, $level+1, $s_rowtpl, $t_ref));
  }
  }
  if (!$level)
    for ($i=0; $i<count($ret); $i++)
      $ret[$i]->addvar('maxlevel', $maxlevel);
  return $ret;
} // function tree_show

function tree_select(&$ar_data, $parent=0, $level=0, $id_selected=NULL, $id_disabled=-1,
  $fl_showdisabled = true)
{
  global $nar_children, $maxlevel;
  static $leveldone = array (false), $even=true;
  if ($level>$maxlevel)
    $maxlevel = $level;
  $ret = array ();
  $count = count($nar_children[$parent]);
  if (is_array ($id_disabled))
    $ar_disabled = $id_disabled;
  else
  {
    $ar_disabled = array ();
    if ($id_disabled>0)
      $ar_disabled[] = $id_disabled;
  }
  foreach($nar_children[$parent] as $i=>$id)
  {
    if ($fl_disabled = in_array ($parent, $ar_disabled))
      $ar_disabled[] = $id;
    else
      $fl_disabled = in_array ($id, $ar_disabled);
    if (!$fl_disabled || $fl_showdisabled)
    $ret[] = '
      <option '. ($id==$id_selected ? 'selected ':''). 'value="'. $id. '"'
      . ($fl_disabled ? ' class="disabled"':''). '>'
      . str_repeat('-', $level). ' '
      . stdHtmlentities($ar_data[$id]['LABEL']). '</option>';

    $leveldone[$level] = ($i==$count-1);
    if ($nar_children[$id] && (!$fl_disabled || $fl_showdisabled))
      $ret = array_merge($ret, tree_select($ar_data, $id, $level+1, $id_selected, $ar_disabled, $fl_showdisabled));
  }
  return $ret;
}
?>
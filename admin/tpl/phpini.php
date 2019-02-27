<?php
/* ###VERSIONSBLOCKINLCUDE### */



/*
  $ar = ini_get_all();
  $ar2 = $liste = array ();
  foreach($ar as $key=>$data)
  {
    $tmp = explode('.', $key);
    if (count($tmp)<2) array_unshift($tmp, '');
    $data['module'] = $tmp[0];
    $data['setting'] = $tmp[1];
    if ($data['access']==7)
      $data['perms'] = 'ALL';
    else
    {
      $tmp2 = array ();
      if ($data['access']&4) $tmp2[] = 'SYSTEM';
      if ($data['access']&2) $tmp2[] = 'PERDIR';
      if ($data['access']&1) $tmp2[] = 'USER';
      $data['perms'] = implode(', ', $tmp2);
    }
    $data['equal'] = $data['global_value']==$data['local_value'];
    $ar2[implode('.', $tmp)] = $data;
  }
  ksort($ar2);
  $thismod = '';
  foreach($ar2 as $k=>$v)
  {
    if ($thismod==$v['module'])
      $ar2[$k]['module'] = '';
    else
      $thismod=$v['module'];
  }
#die(listtab($ar2));
  $i = 0;
  $tpl_content->addlist('liste', array_values($ar2), 'tpl/de/phpini.row.htm');

*/
?>
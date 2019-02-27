<?php
/* ###VERSIONSBLOCKINLCUDE### */



require_once 'sys/lib.nestedsets.php';


function nav_reduce($parent=0, $n_level=0){
#  global $ar_nav, $nar_pageallow;

include_once "../cache/nav1.de.php";
include_once "../cache/pageperm.3.php";

  $ar_ret = array ();
  //echo ht(dump($ar_nav));
  foreach($ar_nav[$parent]['KIDS'] as $id)
  {
    $row = $ar_nav[$id];
    if ($b_vis = $row['B_VIS'])
    {
      $ar_sub = array ();
      if ($row['IDENT'])
      {
        if ($b_vis = $nar_pageallow[$row['IDENT']])
          $ar_sub = nav_reduce($id, $n_level+1);
      }
      else
      {
        $ar_sub = nav_reduce($id, $n_level+1);
        $b_vis = count($ar_sub);
      }
      if ($b_vis)
      {
        $row['kidcount']=count($ar_sub);
        foreach($ar_sub as $sub)
          if ($sub['level']==$n_level+1)
            $row['kidcount']++;
        $ar_ret[] = $row;
        $n_subcount = count($ar_sub);
        while ($row = array_shift($ar_sub))
          $ar_ret[] = $row;
      }
    }
  }
  if ($n = count($ar_ret))
    $ar_ret[$n-1-$n_subcount]['is_last'] = true;
  return $ar_ret;
}

$ar_trg = nav_reduce();

$tpl_content->addvar('rootlabel', $ar_nav[0]['V1']);
$tpl_content->addvar('baum', tree_show_nested(array_values($ar_trg), 'tpl/de/editor_sitemap.row.htm',
  NULL, false)); 

?>

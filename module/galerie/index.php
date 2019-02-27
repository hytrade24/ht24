<?php
/* ###VERSIONSBLOCKINLCUDE### */



 ### Modul Galerie ####
 ### Bilder werden der ID_NAV zugeordnet, und dementsprechend auch ausgelesen
 
# echo ht(dump($nar_systemsettings['GALERIE']));

 $_REQUEST['npage']=($ar_params[1] > 0 ? $ar_params[1] : 1);
 
 $ar_gal = $db->fetch1("select * from modul2nav 
    left join galerie on ID_GALERIE=FK
	where FK_NAV=".$id_nav." and S_MODUL='galerie'");
  $tmp=array();
#  foreach($GLOBALS as $k=>$v) echo "$k: $v<br>";
if($ar_gal['ID_GALERIE'])
{
  
  $c_bilder = $db->fetch_atom("select count(*) from img where OK=1 and FK_GALERIE=".$ar_gal['ID_GALERIE']);
  $limit=(($_REQUEST['npage']-1)*$ar_gal['IMG_PAGE']);
  $tpl_modul->addvar("pager", htm_browse($c_bilder, 
    $_REQUEST['npage'], $s_page.",", $ar_gal['IMG_PAGE']));
  
  $ar_img = $db->fetch_table("select * from img where FK_GALERIE=".$ar_gal['ID_GALERIE']." 
  and OK = 1
  order by DATUM DESC
  LIMIT ".$limit.", ".$ar_gal['IMG_PAGE']);
  
  $k=1;

  for($i=0; $i<$all=count($ar_img); $i++)
  {
    if($k==($ar_gal['IMG_ROW']+1))
      $k=1;
    $tpl_tmp = new Template("module/tpl/".$s_lang."/galerie.row.htm");
    $tpl_tmp->addvar("k", $k);
    $tpl_tmp->addvars($ar_img[$i]);
    $tpl_tmp->addvars($ar_gal);      
    if($i == $all-1)
      $tpl_tmp->addvar("end", 1);
    $tmp[] = $tpl_tmp;
    $k++;
  }
} 

$tpl_modul->addvar("bilder", $tmp); 
 #$tpl_modul->addlist("bilder", $ar_img, "module/galerie/".$s_lang."/galerie.row.htm");

?>
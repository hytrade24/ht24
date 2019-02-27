<?php
/* ###VERSIONSBLOCKINLCUDE### */


  $perpage = 10;
  
  
### Artikel nur aus einer Kategorie?
$id_kat = ($_REQUEST['FK_KAT'] ? $_REQUEST['FK_KAT'] : $db->fetch_atom("select ID_KAT  from kat where LFT=1  AND ROOT = 3"));

### Sortierung bestimmen und in $orderby ablegen
if(!isset($_REQUEST['orderby']))
  $_REQUEST['orderby']='STAMP_DESC';

$tpl_content->addvar("orderby", $_REQUEST['orderby']);
$orderby = str_replace("_", " ", $_REQUEST['orderby']);


### Falls name (adname_) gesucht wird
if ($_REQUEST['adname_']) {
	$where= " and adname like '".$_REQUEST['adname_']."%' ";
}

### Falls name (adname_) gesucht wird
if ($_REQUEST['banner_']) {
	$where.= " and banner like '%".$_REQUEST['banner_']."%' ";
}

### Falls nach ID gesucht wird
if ($_REQUEST['ID_ADS_']) {
	$where= " and ID_ADS= ".$_REQUEST['ID_ADS_'];
}

### Falls name (top_) gesucht wird
if ($_REQUEST['top_']) {
	$where.= " and top=1 ";
}


### Falls name (top_) gesucht wird
if ($_REQUEST['LU_BANNER']) {
	$where.= " and LU_BANNER=".$_REQUEST['LU_BANNER'];
}


### TPL Vars für Sortierung,  aktuelle Seite und Kategorie  
$tpl_content->addvar("FK_KAT", $id_kat);
$tpl_content->addvar("orderby", $_REQUEST['orderby']);
$tpl_content->addvar("npage", ($_REQUEST['npage'] ? $_REQUEST['npage'] : 1));

  if ('setok'==$_REQUEST['do'] && !empty($_REQUEST['save']))
  {

    $tmp = $_REQUEST['OK1'];
    $aktiv = ($tmp ? implode(', ', $tmp) : '0');

	$tmp = $_REQUEST['Okall'];
	$okall = implode(', ', $tmp);

    $db->querynow("update ads set aktiv=if(ID_ADS in ($aktiv),1,0) where ID_ADS in ($okall)");
  }

  if ('DEL'==$_REQUEST['DELME']) {
    $db->querynow("delete from ads_stats where FK_ADS =".$_REQUEST['ID_ADS']); //statistiken loeschen
    $db->delete('ads', $_REQUEST['ID_ADS']);  //banner loeschen
  }
	/*
	echo "select count(*) from news  t
  	left join kat start on start.ID_KAT=".$id_kat."
	left join kat k on FK_KAT=k.ID_KAT
	".$join."
	where (k.LFT >=start.LFT and k.RGT <= start.RGT) ".$where;
	*/
  $all = $db->fetch_atom("select count(*) from ads  t
  	left join kat start on start.ID_KAT=".$id_kat."
	left join kat k on FK_KAT=k.ID_KAT
	where (k.LFT >=start.LFT and k.RGT <= start.RGT) ".$where);


  $limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);
  
	$ar_data = $db->fetch_table('select ID_ADS,FK_KAT,V1,area,aktiv,views,stamp,adname,LU_BANNER,l.VALUE,t.banner,top, DATE_START, DATE_END,DATEDIFF(DATE_END,now()) as ADRUNTIME from ads t
left join kat start on start.ID_KAT='.$id_kat.'
left join kat k on FK_KAT=k.ID_KAT 
left join lookup l on l.ID_LOOKUP=LU_BANNER
left join string_kat m on m.S_TABLE="kat" and m.FK=k.ID_KAT and m.BF_LANG=if(k.BF_LANG_KAT & '.$langval.', '.$langval.',1 << floor(log(k.BF_LANG_KAT+0.5)/log(2))) 
where (k.LFT >=start.LFT and k.RGT <= start.RGT) '.$where.' order by '.$orderby.' LIMIT '.$limit.','.$perpage);



  $tpl_content->addvar("summenews",$db->fetch_atom("select count(*) from ads"));
  $tpl_content->addvar("summeshow",$all);
  $tpl_content->addvar("adname_",$_REQUEST['adname_']); // name der banners
  $tpl_content->addvar("banner_",$_REQUEST['banner_']); // im der bannertag
  $tpl_content->addvar("ID_ADS_",$_REQUEST['ID_ADS_']); // im der bannertag  
  $tpl_content->addvar("preview",$_REQUEST['preview']); // im der bannertag
  $tpl_content->addvar("top_",$_REQUEST['top_']); // im der bannertag    
  $tpl_content->addvar("getcode",$_REQUEST['getcode']); // Codegenerator anzeigen  
  $tpl_content->addvar("LU_BANNER",$_REQUEST['LU_BANNER']); // im der bannertag 
  $tpl_content->addvar("codetouse","{adserver(".$_REQUEST['top_'].",".$id_kat.",".$_REQUEST['LU_BANNER'].")}"); // im der TOP,KAT,TYP  
  $tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&preview=".$_REQUEST['preview']."&LU_BANNER=".$_REQUEST['LU_BANNER']."&FK_KAT=".$id_kat."&top_=".$_REQUEST['top_']."&adname_=".rawurlencode($_REQUEST['adname_'])."&banner_=".rawurlencode($_REQUEST['banner_'])."&getcode=1&npage=", $perpage));

  $tpl_content->addlist('liste', $ar_data, 'tpl/de/modul_adserver.row.htm');

?>
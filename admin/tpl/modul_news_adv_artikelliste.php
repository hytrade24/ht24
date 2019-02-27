<?php
/* ###VERSIONSBLOCKINLCUDE### */


$perpage = 20; // Anzahl der Einträge pro Seite
$isSearch = false;


### Artikel nur aus einer Kategorie?
$id_kat = ($_REQUEST['FK_KAT'] ? $_REQUEST['FK_KAT'] : $db->fetch_atom("select ID_KAT  from kat where LFT=1 AND ROOT = 2"));

### Sortierung bestimmen und in $orderby ablegen
if(!isset($_REQUEST['orderby']))
$_REQUEST['orderby']='STAMP_DESC';

$tpl_content->addvar("orderby", $_REQUEST['orderby']);
$orderby = str_replace("_", " ", $_REQUEST['orderby']);


### Falls Autor (NAME_) gesucht wird
if ($_REQUEST['NAME_']) {
	$where= " and u.NAME like '".$_REQUEST['NAME_']."%' ";
	$join = " left join user u on FK_AUTOR=u.ID_USER ";
	$isSearch = true;
}

### Falls suche in Titel gewuenscht  (V1) gesucht wird
if ($_REQUEST['V1_']) {
	$where.= " and s.V1 like '%".$_REQUEST['V1_']."%' ";
	$join .= "  left join string_c s on s.S_TABLE='news' and s.FK=t.ID_NEWS and s.BF_LANG=if(t.BF_LANG_C & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))";
	$isSearch = true;
}

### Falls suche in Titel gewuenscht  (V1) gesucht wird
if ($_REQUEST['FG_']==1)
{
	if ($_REQUEST['RED_'] or $_REQUEST['ADM_']) {
		$ok_ = $_REQUEST['RED_'] + $_REQUEST['ADM_'];
		$where.= " and OK  = $ok_";
	}
	else
	$where.= " and OK  = 0";
	$isSearch = true;
}

### Falls nach ID gesucht wird
if ($_REQUEST['ID_NEWS_']) {
	$where= " and t.ID_NEWS= ".$_REQUEST['ID_NEWS_'];
	$join='';
	$isSearch = true;
}

### Falls nach Kostenpflichtig gesucht wird
if ($_REQUEST['WITH_COSTS'] == 1) {
	$where= " and t.WITH_COSTS=1 ";
	$join='';
	$isSearch = true;
}


## falls nach Datum gesucht wird
date_implode ($_REQUEST,'STAMP_');
if ($_REQUEST['STAMP_'] and ($_REQUEST['STAMP_'] != date('Y-m-d') )) {
	$where.= " and STAMP ='".$_REQUEST['STAMP_']."' ";
	$isSearch = true;
}

### TPL Vars für Sortierung,  aktuelle Seite und Kategorie
$tpl_content->addvar("FK_KAT", $id_kat);
$tpl_content->addvar("orderby", $_REQUEST['orderby']);
$tpl_content->addvar("npage", ($_REQUEST['npage'] ? $_REQUEST['npage'] : 1));


if ('setok'==$_REQUEST['do'] && !empty($_REQUEST['save']))
{
echo "ok";
	#die(ht(dump($_REQUEST)));
	$tmp = $_REQUEST['OK1'];
	$ok1 = ($tmp ? implode(', ', $tmp) : '0');
	#die(ht(dump($ok1)));
	$tmp = $_REQUEST['OK2'];
	$ok2 = ($tmp ? implode(', ', $tmp) : '0');

	$tmp = $_REQUEST['Okall'];
	$okall = implode(', ', $tmp);
	$db->querynow("update news set OK=if(ID_NEWS in ($ok1),1,0)+if(ID_NEWS in ($ok2),2,0) where ID_NEWS in ($okall)");
	$db->querynow("update news set NEWSNUMBER = 0");
	$db->querynow("set @counter := 0");
	$db->querynow("update news set NEWSNUMBER = @counter := @counter + 1 where ok = 3 ORDER BY STAMP DESC ,ID_NEWS DESC;");
}

if ('DEL'==$_REQUEST['DELME'])
{
	// coins gutschreiben
	if (!empty($_GET['coinback'])) {
		$result_purchased_news = $db->querynow("SELECT `FK_USER`, `NUMBER_OF_COINS`, `NEWSTITLE` FROM `purchased_news` WHERE `FK_NEWS`='".mysql_escape_string($_REQUEST['ID_NEWS'])."'");
		while ($row = mysql_fetch_assoc($result_purchased_news['rsrc'])) {
			$db->querynow("INSERT INTO `transaction` (`ID_TRANSACT`, `FK_USER`, `NUMBER_OF_COINS`, `REASON_FOR_TRANSFER`, `TYPE`, `STAMP`)
				   VALUES ('', '".$row['FK_USER']."',
				   '".$row['NUMBER_OF_COINS']."',
				   '".$row['NEWSTITLE']."',
				   'in',
				   '".date('Y-m-d H:i:s')."')");
		}
	}

	$db->querynow("DELETE FROM `purchased_news` WHERE `FK_NEWS`='".$_REQUEST['ID_NEWS']."'");


	// coins ende

	// Kommentare löschen
	require_once $ab_path."sys/lib.comment.php";
	$cmNews = CommentManagement::getInstance($db, 'news');
	$cmNews->deleteAllComments($_REQUEST['ID_NEWS']);

	$db->delete('news', $_REQUEST['ID_NEWS']);

	$db->querynow("update news set NEWSNUMBER = 0");
	$db->querynow("set @counter := 0");
	$db->querynow("update news set NEWSNUMBER = @counter := @counter + 1 where ok = 3 ORDER BY STAMP DESC ,ID_NEWS DESC;");


	//suchindex updaten
	require_once ("sys/lib.search.php");
	$search = new do_search('de',false);
	$search->delete_article_from_searchindex($_REQUEST['ID_NEWS'],'news');
}

/*
echo "select count(*) from news  t
left join kat start on start.ID_KAT=".$id_kat."
left join kat k on FK_KAT=k.ID_KAT
".$join."
where (k.LFT >=start.LFT and k.RGT <= start.RGT) ".$where;
*/
$all = $db->fetch_atom("select count(*) from news  t
  	left join kat start on start.ID_KAT=".$id_kat."
	left join kat k on FK_KAT=k.ID_KAT
	".$join."
	where (k.LFT >=start.LFT and k.RGT <= start.RGT) ".$where);

$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

/*$ar_data = $db->fetch_table($db->lang_select('news', '*, if (OK&1,1,0) OK1, if (OK&2,1,0) OK2, s.V1, u.NAME as AUTOR')
. 'left join kat start on start.ID_KAT='.$id_kat.'
left join kat k on FK_KAT=k.ID_KAT
left join user u on t.FK_AUTOR=u.ID_USER
where k.LFT >=start.LFT and k.RGT <= start.RGT
order by '.$orderby.'
LIMIT '.$limit.','.$perpage);*/

// NOU = Number of User

$ar_data = $db->fetch_table('select
		COUNT(pn.FK_USER) as NOU, t.ID_NEWS,t.FK_AUTOR,t.STAMP,t.BF_LANG_C, t.WITH_COSTS, s.V1,
		if (OK&1,1,0) OK1, if (OK&2,1,0) OK2, u.NAME as AUTOR,m.V1  as V1KAT ,FK_KAT,
		(SELECT count(*) FROM `comment` WHERE `TABLE`="news" AND FK=t.ID_NEWS) as kommentare,
		(SELECT STAMP FROM `comment` WHERE `TABLE`="news" AND FK=t.ID_NEWS ORDER BY STAMP DESC LIMIT 1) as LAST_COMMENT,
		MD5(u.PASS) as SIG_OWNER
	from news t
     left join string_c s on s.S_TABLE="news" and s.FK=t.ID_NEWS and s.BF_LANG=if(t.BF_LANG_C & '.$langval.', '.$langval.', 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))left join kat start on start.ID_KAT='.$id_kat.'
	 left join kat k on FK_KAT=k.ID_KAT
	 left join user u on t.FK_AUTOR=u.ID_USER
  	 left join string_kat m on m.S_TABLE="kat" and m.FK=k.ID_KAT and m.BF_LANG=if(k.BF_LANG_KAT & '.$langval.', '.$langval.', 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))
  	 left join purchased_news pn ON (pn.FK_NEWS=t.ID_NEWS)
	where (k.LFT >=start.LFT and k.RGT <= start.RGT) '.$where.'
	group by t.ID_NEWS
	order by '.$orderby.'
	LIMIT '.$limit.','.$perpage);


/*
echo $langval;

$ar_data = $db->fetch_table($db->lang_select('news', '*, if (OK&1,1,0) OK1, if (OK&2,1,0) OK2, s.V1, u.NAME as AUTOR')
. 'left join kat start on start.ID_KAT='.$id_kat.'
left join kat k on FK_KAT=k.ID_KAT
left join user u on t.FK_AUTOR=u.ID_USER
where k.LFT >=start.LFT and k.RGT <= start.RGT
order by '.$orderby.'
LIMIT '.$limit.','.$perpage);


select  t.FK_AUTOR,t.STAMP,t.BF_LANG_C, s.V1, if (OK&1,1,0) OK1, if (OK&2,1,0) OK2, u.NAME as AUTOR,m.V1 from news t
left join string_c s on s.S_TABLE='news' and s.FK=t.ID_NEWS
and s.BF_LANG=if(t.BF_LANG_C & 128, 128, 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))left join kat start on start.ID_KAT=1
left join kat k on FK_KAT=k.ID_KAT
left join user u on t.FK_AUTOR=u.ID_USER
left join string_kat m on m.S_TABLE='kat' and m.FK=k.ID_KAT and m.BF_LANG=if(k.BF_LANG_KAT & 128, 128, 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))
where k.LFT >=start.LFT and k.RGT <= start.RGT
order by STAMP DESC
LIMIT 0,25

*/

if ($isSearch) {
	$tpl_content->addvar("SEARCH_RESULT", 1);
}

$tpl_content->addvar("summenews",$db->fetch_atom("select count(*) from news"));
$tpl_content->addvar("summeshow",$all);
$tpl_content->addvar("STAMP_",$_REQUEST['STAMP_']);

$tpl_content->addvar("RED_",$_REQUEST['RED_']);
$tpl_content->addvar("ADM_",$_REQUEST['ADM_']);
$tpl_content->addvar("FG_",$_REQUEST['FG_']);

$tpl_content->addvar("V1_",$_REQUEST['V1_']);  //News Titel
$tpl_content->addvar("NAME_",$_REQUEST['NAME_']); // autor
$tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&FK_KAT=".$id_kat."&NAME_=".$_REQUEST['NAME_']."&STAMP_=".$_REQUEST['STAMP_']."&V1_=".rawurlencode($_REQUEST['V1_'])."&RED_=".$_REQUEST['RED_']."&ADM_=".$_REQUEST['ADM_']."&FG_=".$_REQUEST['FG_']."&npage=", $perpage));

$ar = $ar_tmp = array();
for($i=0; $i<count($ar_data); $i++)
{
  $tmp = new Template("tpl/de/modul_news_adv_artikelliste.row.htm");

  $ar_liste[$i]['even'] = $i%2;

	$sql = "select l.ABBR from string_c s
	 left join lang l on l.BITVAL=s.BF_LANG
	 where
	  S_TABLE='news' and FK=".$ar_data[$i]['ID_NEWS'];
  $ar = $db->fetch_table($sql);
  $ar_data[$i]['langs']='';
  for($k=0; $k<count($ar); $k++) {
	  $ar_data[$i]['langs'] .= '<img src="'.$tpl_content->tpl_uri_baseurl('/gfx/lang.'.$ar[$k]['ABBR'].'.gif').'"> ';
  }
  $tmp->addvars($ar_data[$i]);
  $ar_tmp[] = $tmp;
}
$tpl_content->addvar("liste", $ar_tmp);
#$tpl_content->addlist('liste', $ar_data, 'tpl/de/modul_news_adv_artikelliste.row.htm');

?>
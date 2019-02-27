<?php
/* ###VERSIONSBLOCKINLCUDE### */



$id_kat = ($_REQUEST['FK_KAT'] ? $_REQUEST['FK_KAT'] : $db->fetch_atom("select ID_KAT
  from kat where LFT=1"));
  
$tpl_content->addvar("FK_KAT", $id_kat);

  if ('setok'==$_REQUEST['do'] && !empty($_REQUEST['save']))
  {
#die(ht(dump($_REQUEST)));
    $tmp = $_REQUEST['OK1'];
    $ok1 = ($tmp ? implode(', ', $tmp) : '0');
#die(ht(dump($ok1)));
    $tmp = $_REQUEST['OK2'];
    $ok2 = ($tmp ? implode(', ', $tmp) : '0');
	
	$tmp = $_REQUEST['Okall'];
	$okall = implode(', ', $tmp);
    $up=$db->querynow("update news set OK=if(ID_NEWS in ($ok1),1,0)+if(ID_NEWS in ($ok2),2,0) where ID_NEWS in ($okall)");
    #echo ht(dump($up));
  }

  if ('DEL'==$_REQUEST['DELME'])
    $db->delete('news', $_REQUEST['ID_NEWS']);

  $all = $db->fetch_atom("select count(*) from news 
  left join kat start on start.ID_KAT=".$id_kat."
	left join kat k on FK_KAT=k.ID_KAT
	where k.LFT >=start.LFT and k.RGT <= start.RGT");
  
  $perpage = 20;
  $limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);
  
  $ar_data = $db->fetch_table($db->lang_select('news', '*, if (OK&1,1,0) OK1, if (OK&2,1,0) OK2, s.V1')
    . 'left join kat start on start.ID_KAT='.$id_kat.'
	left join kat k on FK_KAT=k.ID_KAT
	where k.LFT >=start.LFT and k.RGT <= start.RGT
	order by ID_NEWS desc
	LIMIT '.$limit.','.$perpage);
  
  $tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], 
     "index.php?page=news&FK_KAT=".$id_kat."&npage=", $perpage));

/**/
  $tpl_content->addlist('liste', $ar_data, 'tpl/de/news.row.htm');
/*/
  $ar_liste = array ();
  foreach($ar_data as $i=>$row)
 {
  $tpl_tmp = new DirTemplate($ar_dirs, 'news.row', $_SESSION['tbl']);
#echo ht(dump($row));
  $tpl_tmp->addvars($row);
  $tpl_tmp->addvar('even', !($i&1));
  $ar_liste[] = $tpl_tmp;
  }
  $tpl_content->addvar('liste', $ar_liste);
/**/

/*
DROP TABLE IF EXISTS `news`;
CREATE TABLE `news` (
  `ID_NEWS` bigint(20) unsigned NOT NULL auto_increment,
  `FK_USER` bigint(20) unsigned NOT NULL default '0',
  `OK` tinyint(1) unsigned NOT NULL default '0',
  `STAMP` date default NULL,
  `SUBJECT` varchar(255) NOT NULL default '',
  `TEASE` text NOT NULL,
  `BODY` mediumtext NOT NULL,
  `FK_IMG` bigint(20) unsigned default NULL,
  `BF_LANG_C` tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`ID_NEWS`),
  KEY `FK_USER` (`FK_USER`),
  KEY `OK` (`OK`)
) TYPE=MyISAM AUTO_INCREMENT=26 ;

#
# Daten für Tabelle `news`
#

INSERT INTO `news` VALUES (24, 1, 3, '2005-01-04', 'Update auf Version 1.2', 'Noch besser, noch effektiver - ebiz-stats V1.2a. Update Information finden Sie hier.', '<UL style="FONT-SIZE: 11px; MARGIN-LEFT: 55px; FONT-FAMILY: Arial, Helvetica, sans-serif">\r\n<LI>Optimierung und Fix der logit()-Routine, die in Ausnahmef Optimierung und Fix der logit()-Routine, die in Ausnahmefä \r\n<LI>Datei namens "webmastercookie.php" im Ordner "/tools" hinzugefügt, mit der man per Hand das Webmaster-Cookie setzen lassen kann und hierbei nicht auf das ebiz-stats Interface angewiesen ist. \r\n<LI>Fix der Suchwort-Erkennung im Zusammenhang mit Google\'s (TM) Bildersuche \r\n<LI>Anzeige der "Impressions seit Beginn" in der Website-Konfiguration korrigiert. \r\n<LI>Fix der Suchwort-Erkennung bei der Weiterleitung eines Besuchers durch eine Suchmaschine. \r\n<LI>Fix in der Cronjob-Datei für die automatische Zusendung von Statistik-E-Mails \r\n<LI>Javascript-Fehler auf Login-Seite behoben, der bei manchen Nutzern auftreten konnte. \r\n<LI>Fix der automatischen E-Mail-Zusendung, die bei einigen Anwendern nicht ausgelöst wurde bzw. die Mails unpünktlich kamen. \r\n<LI>Die Zahl der Impressions für das aktuelle Jahr wird bei der Darstellung der potentiellen Werbeeinnahmen korrekt angezeigt. \r\n<LI>Fix der Webmaster-Cookie Funktion - bei einigen Anwendern konnte kein Cookie gesetzt werden. \r\n<LI>Korrektur der Archivroutine, die nun die richtige Gesamtzahl der archivierten Daten wiedergibt \r\n<LI>Anpassung der Archivroutine, um sehr große Datenbestände archivieren zu können. \r\n<LI>Anpassung der Archiv-Datentabelle, um eine größere Anzahl von Daten speichern zu können. \r\n<LI>Englisches Sprachpaket inkl. Icons hinzugefügt, Installationsanleitung und Dokumentation werden mit einem der kommenden Updates folgen. \r\n<LI>Bei der Änderung eines Nutzeraccounts, z.B. abändern des Passwortes, wird nun nicht mehr fehlerhafterweise erneut ein Websitename zugewiesen. \r\n<LI>Die Zahl der Pageimpressions wird jetzt bei einem Seitenaufruf durch einen Roboter/Spider nicht mehr erhöht und in den Übersichten korrekt dargestellt. \r\n<LI>Farbliche Anpassung des rechten Scrollbalkens im Interface (graue Farbe, nur für IE) - Update der Suchmaschinen- sowie der Robot/Spider-Listen. \r\n<LI>Aktualisierte IP2Country-Datei </LI></UL>', NULL, 128);
INSERT INTO `news` VALUES (25, 0, 3, NULL, 'qwqwee', 'qwqwqwe', '<IMG style="WIDTH: 64px; HEIGHT: 21px" alt=sadasd hspace=0 src="http://server/phpres2/img/0/425f8f6c27a19.adobephotoshop.gif" align=baseline border=0>qwqw', NULL, 128);
*/
?>
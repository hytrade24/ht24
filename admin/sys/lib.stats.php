<?php
/* ###VERSIONSBLOCKINLCUDE### */


/*
  $ID=Welcher Banner
  Es wird immer der angegebene Monat komplett ausgeben
*/

$month[1]='Januar';
$month[2]='Februar';
$month[3]='M&auml;rz';
$month[4]='April';
$month[5]='Mai';
$month[6]='Juni';
$month[7]='Juli';
$month[8]='August';
$month[9]='September';
$month[10]='Oktober';
$month[11]='November';
$month[12]='Dezember';


function stats_getdata ($ID, $month,$year)
{
  global $db;

	$t = mktime(1,1,1,$month,1,$year);
	$from =date("Y-m-d", $t);
	$n = date('t', $t);
	$to =date("Y-m-d", mktime(1,1,1,$month,$n,$year));
	if ($ID) 
		$where ="FK_ADS = $ID and";
	$nar_data = $db->fetch_table("select DATUM, sum(VIEWS) VIEWS, sum(CLICKS) CLICKS,
        if (sum(VIEWS)>0, truncate(sum(CLICKS)/sum(VIEWS),4), '--') as CLICKRATIO,
        dayofmonth(DATUM) as tag
      from ads_stats
      where  $where DATUM between '$from' and '$to'
      group by DATUM", 'DATUM');
    // fuellen
	$max_views=$max_click=$sumviews=0;
    for($k = $from; $k<=$to; $k=date('Y-m-d', $t=$t+86400)) {
	  $nar_data[$k]['tag'] = date('d', $t);
	  $sumviews=$sumviews+$nar_data[$k]['VIEWS'];
	  if ($nar_data[$k]['VIEWS'] > $max_views ) { $max_views=$nar_data[$k]['VIEWS']; $day=$nar_data[$k]['tag']; }
	  }
	ksort($nar_data);
 foreach ($nar_data as $key => $vaule) {
	$hight=$vaule['VIEWS'] ? round(100*$vaule['VIEWS']/$max_views) : 1;
	$button="blue";
	if ($vaule['tag']==$day)
		 $button="brown";
	 $zeile.='<td align="middle" width="13" class="btm"><img height="'.$hight.'" src="gfx/'.$button.'_dot.gif" width="11" border="0" title="'.$vaule['VIEWS'].'"/></td>';
	 $zeiledate.='<td vAlign="bottom" align="middle" width="13" style="font-size : 6pt; font-family : arial,helvetica;">'.$vaule['tag'].'</td>';
 }
 
 
 
 return array(
			"tbl_ads_aktmonth" 	=> '   <tr valign="bottom">
      <td vAlign="top" align="right" bgcolor="#e1e1e1">'
        . round($max_views). '<img height="12" src="gfx/dot.gif" width="1" /><br />'
        . round($max_views/2,2). '<img height="48" src="gfx/dot.gif" width="1" /><br /><img height="1" src="gfx/dot.gif" width="35" /></td>
      <td><img height="110" src="gfx/blank_dot.gif" width="3" /></td>
	'.$zeile.'
	</tr>
	<tr>
        <td vAlign="top" bgcolor="#e1e1e1"></td>
        <td></td>
	'.$zeiledate.'
	</tr>',
			"sum_ads_aktmonth" 	=> $sumviews,
			"from_ads_aktmonth" 	=> $from,
			"to_ads_aktmonth" 		=> $to
			);
}



function news_getdata ($month = null, $year = null)
{
	global $db; // Datenbankwrapper

	// Falls kein Monat/Jahr Ã¼bermittelt wurden, hier aktuelles Datum nehmen
	if(empty($month))
		$month = (int)date('m');
	
	if(empty($year))
		$year = (int)date('Y');

	$tn = mktime(1,1,1,$month,1,$year);
	$n = date("t", $tn);
	$to = date("Y-m-d", mktime(23,59,59,$month,$n,$year)); // Zeitstring erzeugen, BIS wann Statistik erzeugt werden soll
	$returnto = substr($to, 0,7); // wird return()ed
	$year--;
	$t = mktime(0,0,0,$month,1,$year);
	$year++;
	$from = date("Y-m-d", $t); // Zeitstring erzeugen, AB wann Statistik erzeugt werden soll
	$returnfrom = substr($from, 0,7); // wird return()ed

	$nar_data = $db->fetch_table("	select count(ID_NEWS) as anzahl, month(STAMP) as monat , year(STAMP) as jahr , CONCAT(year(STAMP),'-',month(STAMP)) as indexes
									from news 
									where STAMP between '".$from."' and '".$to."'
									group by Year(STAMP), month(STAMP)
									order by Year(STAMP) DESC, month(STAMP) DESC limit 0,12", "indexes"); // Daten aus Tabelle holen	

	$maxanzahl = $sumanzahl = 0;

	for ($i = 0; $i < 12; $i++) {
 
		$in = date("Y-m", mktime(0, 0, 0, $month-$i, 1, $year));
		$in = str_replace ('-0','-',$in);
 
		if (!array_key_exists($in, $nar_data)) {
			$nar_data[$in]['anzahl']='0';
			$nar_data[$in]['monat']=(int)date("m", mktime(0, 0, 0, $month-$i, 1, $year));
			$nar_data[$in]['jahr']=(int)date("Y", mktime(0, 0, 0, $month-$i, 1, $year));
		}
 
	$nar_data[$in]['jahr']=substr($nar_data[$in]['jahr'], -2, 2);
	$sumanzahl=$sumanzahl+$nar_data[$in]['anzahl'];
	if ($nar_data[$in]['anzahl'] > $maxanzahl ) { $maxanzahl=$nar_data[$in]['anzahl']; $monat=$nar_data[$in]['monat']; } // Monat mit meisten News ermitteln
	}

	for ($i = 11; $i > -1; $i--) {
		$in = date("Y-m", mktime(0, 0, 0, $month-$i, 1, $year));
		$in = str_replace ('-0','-',$in);
		$height=$nar_data[$in]['anzahl'] ? round(100*$nar_data[$in]['anzahl']/$maxanzahl) : 1;
		$button="blue"; // Standard: Hellblaue Balken
		if ($nar_data[$in]['monat']==$monat) // Spitzen-Monat: Dunkelblauer Balken
		  $button="brown";
		$zeile.='<td align="middle" width="13" class="btm" style="font-size : 6pt; font-family : arial,helvetica;"><img height="'.$height.'" src="gfx/'.$button.'_dot.gif" width="11" border="0" title="('.$nar_data[$in]['anzahl'].')"/></td>';
	 	$zeiledate.='<td vAlign="bottom" align="middle" width="13" style="font-size : 6pt; font-family : arial,helvetica;">'.$nar_data[$in]['monat'].'<br>
		'.$nar_data[$in]['jahr'].'</td>';
	}

	return array(
			"tbl_news_year" 	=> "  <tr valign=\"bottom\">
      									<td vAlign=\"top\" align=\"right\" bgcolor=\"#e1e1e1\">"
        									.round($maxanzahl). "<img height=\"12\" src=\"gfx/dot.gif\" width=\"1\" /><br />"
       										.round($maxanzahl/2,2). "<img height=\"48\" src=\"gfx/dot.gif\" width=\"1\" /><br /><img height=\"1\" src=\"gfx/dot.gif\" width=\"35\" /></td>
      									<td><img height=\"110\" src=\"gfx/blank_dot.gif\" width=\"3\" /></td>
										".$zeile."
									</tr>
									<tr>
        								<td vAlign=\"top\" bgcolor=\"#e1e1e1\"></td>
        								<td></td>
										".$zeiledate."
									</tr>",
			"sum_news_year" 	=> $sumanzahl,
			"from_news_year" 	=> $returnfrom,
			"to_news_year" 		=> $returnto
			);

}



function newsdozen_getdata ($year = null)
{
	global $db;

	if(empty($year))
		$year = (int)date('Y');

	$to = date("Y-m-d", mktime(23,59,59,12,31,date('Y'))); // Zeitstring erzeugen, BIS wann Statistik erzeugt werden soll
	$returnto = substr($to, 0,4); // wird return()ed
	$t = mktime(0,0,0,1,1,date('Y')-12);
	$from = date("Y-m-d", $t); // Zeitstring erzeugen, AB wann Statistik erzeugt werden soll
	$returnfrom = substr($from, 0,4); // wird return()ed

	$nar_data = $db->fetch_table("	select count(ID_NEWS) as anzahl, year(STAMP) as jahr
									from news 
									where STAMP between '".$from."' and '".$to."'
									group by Year(STAMP)
									order by Year(STAMP) DESC limit 0,12", "jahr"); // Daten aus Tabelle holen	

	$maxanzahl = $sumanzahl = 0;

	for ($i = 0; $i < 12; $i++) {
 
		$in = date("Y", mktime(0, 0, 0, 1, 1, date('Y')-$i));
 
		if (!array_key_exists($in, $nar_data)) {
			$nar_data[$in]['anzahl']='0';
			$nar_data[$in]['jahr']=(int)date("Y", mktime(0, 0, 0, 1, 1, date('Y')-$i));
		}
 
	$nar_data[$in]['jahr']=substr($nar_data[$in]['jahr'], -2, 2);
	$sumanzahl=$sumanzahl+$nar_data[$in]['anzahl'];
	if ($nar_data[$in]['anzahl'] > $maxanzahl ) { $maxanzahl=$nar_data[$in]['anzahl']; $jahr=$nar_data[$in]['jahr']; } // Jahr mit meisten News ermitteln
	}

	for ($i = 11; $i > -1; $i--) {
		$in = date("Y", mktime(0, 0, 0, 1, 1, date('Y')-$i));
		$height=$nar_data[$in]['anzahl'] ? round(100*$nar_data[$in]['anzahl']/$maxanzahl) : 1;
		$button="blue"; // Standard: Blaue Balken
		if ($nar_data[$in]['jahr']==$jahr) // Spitzen-Monat blauer Balken
		  $button="brown";
		if('20'.$nar_data[$in]['jahr']==$year)
			$button="red";
		$zeile.='<td align="middle" width="13" class="btm" style="font-size : 6pt; font-family : arial,helvetica;"><a href="index.php?page=modul_news_adv_stat&jahr='.$in.'"><img height="'.$height.'" src="gfx/'.$button.'_dot.gif" width="11" border="0" title="('.$nar_data[$in]['anzahl'].')" /></a></td>';
	 	$zeiledate.='<td vAlign="bottom" align="middle" width="13" style="font-size : 6pt; font-family : arial,helvetica;">'.$nar_data[$in]['jahr'].'</td>';
	}
#echo ht(dump($nar_data));

	return array(
			"tbl_news_dozen" 	=> " <tr valign=\"bottom\">
      									<td vAlign=\"top\" align=\"right\" bgcolor=\"#e1e1e1\">"
        									.round($maxanzahl). "<img height=\"12\" src=\"gfx/dot.gif\" width=\"1\" /><br />"
       										.round($maxanzahl/2,2). "<img height=\"48\" src=\"gfx/dot.gif\" width=\"1\" /><br /><img height=\"1\" src=\"gfx/dot.gif\" width=\"35\" /></td>
      									<td><img height=\"110\" src=\"gfx/blank_dot.gif\" width=\"3\" /></td>
										".$zeile."
									</tr>
									<tr>
        								<td vAlign=\"top\" bgcolor=\"#e1e1e1\"></td>
        								<td></td>
										".$zeiledate."
									</tr>",
			"sum_news_dozen" 	=> $sumanzahl,
			"from_news_dozen" 	=> $returnfrom,
			"to_news_dozen" 		=> $returnto
			);

}



function comment_getdata ($month = null, $year = null)
{
	global $db;

	// Falls kein Monat/Jahr Ã¼bermittelt wurden, hier aktuelles Datum nehmen
	if(empty($month))
		$month = (int)date('m');
	
	if(empty($year))
		$year = (int)date('Y');

	$tn = mktime(1,1,1,$month,1,$year);
	$n = date("t", $tn);
	$to = date("Y-m-d", mktime(23,59,59,$month,$n,$year)); // Zeitstring erzeugen, BIS wann Statistik erzeugt werden soll
	$returnto = substr($to, 0,7); // wird return()ed
	$year--;
	$t = mktime(0,0,0,$month,1,$year);
	$year++;
	$from = date("Y-m-d", $t); // Zeitstring erzeugen, AB wann Statistik erzeugt werden soll
	$returnfrom = substr($from, 0,7); // wird return()ed

	$nar_data = $db->fetch_table("	select count(ID_COMMENT) as anzahl, month(STAMP) as monat , year(STAMP) as jahr , CONCAT(year(STAMP),'-',month(STAMP)) as indexes
									from comment
									where STAMP between '".$from."' and '".$to."'
									group by Year(STAMP), month(STAMP)
									order by Year(STAMP) DESC, month(STAMP) DESC limit 0,12", "indexes"); // Daten aus Tabelle holen

	$maxanzahl = $sumanzahl = 0;

	for ($i = 0; $i < 12; $i++) {
 
		$in = date("Y-m", mktime(0, 0, 0, $month-$i, 1, $year));
		$in = str_replace ('-0','-',$in);
 
		if (!array_key_exists($in, $nar_data)) {
			$nar_data[$in]['anzahl']='0';
			$nar_data[$in]['monat']=(int)date("m", mktime(0, 0, 0, $month-$i, 1, $year));
			$nar_data[$in]['jahr']=(int)date("Y", mktime(0, 0, 0, $month-$i, 1, $year));
		}
 
	$nar_data[$in]['jahr']=substr($nar_data[$in]['jahr'], -2, 2);
	$sumanzahl=$sumanzahl+$nar_data[$in]['anzahl'];
	if ($nar_data[$in]['anzahl'] > $maxanzahl ) { $maxanzahl=$nar_data[$in]['anzahl']; $monat=$nar_data[$in]['monat']; } // Monat mit meisten News ermitteln
	}

	for ($i = 11; $i > -1; $i--) {
		$in = date("Y-m", mktime(0, 0, 0, $month-$i, 1, $year));
		$in = str_replace ('-0','-',$in);
		$height=$nar_data[$in]['anzahl'] ? round(100*$nar_data[$in]['anzahl']/$maxanzahl) : 1;
		$button="blue"; // Standard: Hellblaue Balken
		if ($nar_data[$in]['monat']==$monat) // Spitzen-Monat: Dunkelblauer Balken
		  $button="brown";

		$zeile.='<td align="middle" width="13" class="btm" style="font-size : 6pt; font-family : arial,helvetica;"><img height="'.$height.'" src="gfx/'.$button.'_dot.gif" width="11" border="0" title="('.$nar_data[$in]['anzahl'].')"/></td>';
	 	$zeiledate.='<td vAlign="bottom" align="middle" width="13" style="font-size : 6pt; font-family : arial,helvetica;">'.$nar_data[$in]['monat'].'<br>
		'.$nar_data[$in]['jahr'].'</td>';
	}

	return array(
			"tbl_comment_year" 		=> "  <tr valign=\"bottom\">
	      									<td vAlign=\"top\" align=\"right\" bgcolor=\"#e1e1e1\">"
	        									.round($maxanzahl). "<img height=\"12\" src=\"gfx/dot.gif\" width=\"1\" /><br />"
	       										.round($maxanzahl/2,2). "<img height=\"48\" src=\"gfx/dot.gif\" width=\"1\" /><br /><img height=\"1\" src=\"gfx/dot.gif\" width=\"35\" /></td>
	      									<td><img height=\"110\" src=\"gfx/blank_dot.gif\" width=\"3\" /></td>
											".$zeile."
										</tr>
										<tr>
	        								<td vAlign=\"top\" bgcolor=\"#e1e1e1\"></td>
	        								<td></td>
											".$zeiledate."
										</tr>",
			"sum_comment_year" 		=> $sumanzahl,
			"from_comment_year" 	=> $returnfrom,
			"to_comment_year" 		=> $returnto
			);

}



function commentdozen_getdata ($year = null)
{
	global $db;

	if(empty($year))
		$year = (int)date('Y');

	$to = date("Y-m-d", mktime(23,59,59,12,31,date('Y'))); // Zeitstring erzeugen, BIS wann Statistik erzeugt werden soll
	$returnto = substr($to, 0,4); // wird return()ed
	$t = mktime(0,0,0,1,1,date('Y')-12);
	$from = date("Y-m-d", $t); // Zeitstring erzeugen, AB wann Statistik erzeugt werden soll
	$returnfrom = substr($from, 0,4); // wird return()ed

	$nar_data = $db->fetch_table("	select count(ID_COMMENT) as anzahl, year(STAMP) as jahr
									from comment
									where STAMP between '".$from."' and '".$to."'
									group by Year(STAMP)
									order by Year(STAMP) DESC limit 0,12", "jahr"); // Daten aus Tabelle holen	

	$maxanzahl = $sumanzahl = 0;

	for ($i = 0; $i < 12; $i++) {
 
		$in = date("Y", mktime(0, 0, 0, 1, 1, date('Y')-$i));
 
		if (!array_key_exists($in, $nar_data)) {
			$nar_data[$in]['anzahl']='0';
			$nar_data[$in]['jahr']=(int)date("Y", mktime(0, 0, 0, 1, 1, date('Y')-$i));
		}
 
	$nar_data[$in]['jahr']=substr($nar_data[$in]['jahr'], -2, 2);
	$sumanzahl=$sumanzahl+$nar_data[$in]['anzahl'];
	if ($nar_data[$in]['anzahl'] > $maxanzahl ) { $maxanzahl=$nar_data[$in]['anzahl']; $jahr=$nar_data[$in]['jahr']; } // Jahr mit meisten News ermitteln
	}

	$year = substr($year, -2,2); // Damit in for Schleife ueberpruefung auf angeklicktes Jahr erfolgen kann, Jahrzent extrahieren
	
	for ($i = 11; $i > -1; $i--) {
		$in = date("Y", mktime(0, 0, 0, 1, 1, date('Y')-$i));
		$height=$nar_data[$in]['anzahl'] ? round(100*$nar_data[$in]['anzahl']/$maxanzahl) : 1;
		$button="blue"; // Standard: Blaue Balken
		if ($nar_data[$in]['jahr']==$jahr) // Spitzen-Monat blauer Balken
		  $button="brown";
		if($nar_data[$in]['jahr']==$year)
			$button="red";
		$zeile.='<td align="middle" width="13" class="btm" style="font-size : 6pt; font-family : arial,helvetica;"><a href="index.php?page=modul_news_adv_stat&jahr='.$in.'"><img height="'.$height.'" src="gfx/'.$button.'_dot.gif" width="11" border="0" title="('.$nar_data[$in]['anzahl'].')" /></a></td>';
	 	$zeiledate.='<td vAlign="bottom" align="middle" width="13" style="font-size : 6pt; font-family : arial,helvetica;">'.$nar_data[$in]['jahr'].'</td>';
	}
#ht(dump($nar_data));

	return array(
			"tbl_comment_dozen" 	=> " <tr valign=\"bottom\">
	      									<td vAlign=\"top\" align=\"right\" bgcolor=\"#e1e1e1\">"
	        									.round($maxanzahl). "<img height=\"12\" src=\"gfx/dot.gif\" width=\"1\" /><br />"
	       										.round($maxanzahl/2,2). "<img height=\"48\" src=\"gfx/dot.gif\" width=\"1\" /><br /><img height=\"1\" src=\"gfx/dot.gif\" width=\"35\" /></td>
	      									<td><img height=\"110\" src=\"gfx/blank_dot.gif\" width=\"3\" /></td>
											".$zeile."
										</tr>
										<tr>
	        								<td vAlign=\"top\" bgcolor=\"#e1e1e1\"></td>
	        								<td></td>
											".$zeiledate."
										</tr>",
			"sum_comment_dozen" 	=> $sumanzahl,
			"from_comment_dozen" 	=> $returnfrom,
			"to_comment_dozen" 		=> $returnto
			);

}




function stats_getdata_month ($month = null, $year = null,$FK_ADS=null)
{
	global $db;
	
  if ($FK_ADS>0) {
	$where = " and FK_ADS=".$FK_ADS;
	}

	if(empty($month))
		$month = (int)date('m');
	
	if(empty($year))
		$year = (int)date('Y');

	$tn = mktime(11,59,59,$month,1,$year);
	$n = date("t", $tn);
	$to = date("Y-m-d", mktime(23,59,59,$month,$n,$year)); // Zeitstring erzeugen, BIS wann Statistik erzeugt werden soll
	$t = mktime(11,59,59,$month-12,1,$year);
	$from = date("Y-m-d", $t); // Zeitstring erzeugen, AB wann Statistik erzeugt werden soll
	$nar_data = $db->fetch_table("select sum(VIEWS) as anzahl, month(DATUM) as monat , year(DATUM) as jahr , CONCAT(year(DATUM),'-',month(DATUM)) as indexes
from ads_stats where DATUM between '".$from."' and '".$to."' ".$where."
group by Year(DATUM), month(DATUM)	order by Year(DATUM) DESC, month(DATUM) DESC", "indexes"); // Daten aus Tabelle holen

			
	$maxanzahl = $sumanzahl = 0;

	for ($i = 0; $i < 13; $i++) {
 
		$in = date("Y-m", mktime(23, 59, 59, $month-$i, 1, $year));
		$in = str_replace ('-0','-',$in);
 
		if (!array_key_exists($in, $nar_data)) {
			$nar_data[$in]['anzahl']='0';
			$nar_data[$in]['monat']=(int)date("m", mktime(0, 0, 0, $month-$i, 1, $year));
			$nar_data[$in]['jahr']=(int)date("Y", mktime(0, 0, 0, $month-$i, 1, $year));
		}
 
	$nar_data[$in]['jahr']=substr($nar_data[$in]['jahr'], -2, 2);
	$sumanzahl=$sumanzahl+$nar_data[$in]['anzahl'];
	if ($nar_data[$in]['anzahl'] > $maxanzahl ) { $maxanzahl=$nar_data[$in]['anzahl']; $monat=$nar_data[$in]['monat']; } // Monat mit meisten News ermitteln
	}

	for ($i = 12; $i > -1; $i--) {
		$in = date("Y-m", mktime(0, 0, 0, $month-$i, 1, $year));
		$in = str_replace ('-0','-',$in);
		$height=$nar_data[$in]['anzahl'] ? round(100*$nar_data[$in]['anzahl']/$maxanzahl) : 1;
		$button="blue"; // Standard: Blaue Balken
		if ($nar_data[$in]['monat']==$monat) // Spitzen-Monat blauer Balken
		  $button="brown";
		$zeile.='<td align="middle" width="13" class="btm" style="font-size : 6pt; font-family : arial,helvetica;"><img height="'.$height.'" src="gfx/'.$button.'_dot.gif" width="11" border="0" title="('.$nar_data[$in]['anzahl'].')"/></td>';
	 	$zeiledate.='<td vAlign="bottom" align="middle" width="13" style="font-size : 6pt; font-family : arial,helvetica;">'.$nar_data[$in]['monat'].'<br>
		'.$nar_data[$in]['jahr'].'</td>';
	}

	return array(
			"tbl_ads_12month" => "  <tr valign=\"bottom\">
      								<td vAlign=\"top\" align=\"right\" bgcolor=\"#e1e1e1\">"
        								. round($maxanzahl). "<img height=\"12\" src=\"gfx/dot.gif\" width=\"1\" /><br />"
       									. round($maxanzahl/2,2). "<img height=\"48\" src=\"gfx/dot.gif\" width=\"1\" /><br /><img height=\"1\" src=\"gfx/dot.gif\" width=\"35\" /></td>
      								<td><img height=\"110\" src=\"gfx/blank_dot.gif\" width=\"3\" /></td>
									".$zeile."
								</tr>
								<tr>
        							<td vAlign=\"top\" bgcolor=\"#e1e1e1\"></td>
        							<td></td>
									".$zeiledate."
								</tr>",
			"sum_ads_12monthr" => $sumanzahl,
			"from_ads_12month" => $from,
			"to_ads_12month" => $to
			);

}




function stats_getdata_year($FK_ADS)
{
  global $db;

  if ($FK_ADS>0) {

	$where = "where FK_ADS=".$FK_ADS;
	
}
  
  	//max Wert auslesen
  	$max = $db->fetch1("select sum(VIEWS) as counter , Year(DATUM) as mdatum from ads_stats  ".$where." group by Year(DATUM) order by counter DESC Limit 1");
	$nar_data = $db->fetch_table("select sum(VIEWS) as anzahl ,year(DATUM) as dyear from ads_stats ".$where." group by Year(DATUM) order by DATUM", 'dyear');

	$anzahl_jahre=count($nar_data);//mysql_num_rows($nar_data['rsrc']);
	$sumviews=0;

	foreach ($nar_data as $key => $vaule) {
		$hight=$vaule['anzahl'] ? round(100*$vaule['anzahl']/$max['counter']) : 1;
		$button="blue";
		$sumviews=$sumviews+$vaule['anzahl'];
		if ($vaule['dyear']==$max['mdatum'])
		 	$button="brown";
	 	$zeile.='<td align="middle" width="13" class="btm"><img height="'.$hight.'" src="gfx/'.$button.'_dot.gif" width="11" border="0" title="'.$vaule['anzahl'].'"/></td>';
	 	$zeiledate.='<td vAlign="bottom" align="middle" width="13" style="font-size : 6pt; font-family : arial,helvetica;">'.substr($vaule['dyear'], -2, 2).'<br>
&nbsp;</td>';
 	}
 
return array( 
		"tbl_ads_years" => '<tr valign="bottom">
      <td vAlign="top" align="right" bgcolor="#e1e1e1">'
        . round($max['counter']). '<img height="12" src="gfx/dot.gif" width="1" /><br />'
        . round($max['counter']/2,2). '<img height="48" src="gfx/dot.gif" width="1" /><br /><img height="1" src="gfx/dot.gif" width="35" /></td>
      <td><img height="110" src="gfx/blank_dot.gif" width="3" /></td>
	'.$zeile.'
	</tr>
	<tr>
        <td vAlign="top" bgcolor="#e1e1e1"></td>
        <td></td>
	'.$zeiledate.'
	</tr>',
	"sum_ads_years" => $sumviews
			);
	  
}
?>
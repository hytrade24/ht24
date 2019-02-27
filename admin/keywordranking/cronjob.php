<?php
/* ###VERSIONSBLOCKINLCUDE### */


$classpath = str_replace ( "cronjob.php", "class.php", __FILE__ );
include_once ($classpath);
$proxy = new proxy ();
$sql = "select * from keyword ";
$result = mysql_query ( $sql );
while ( $row = mysql_fetch_assoc ( $result ) ) {
	#echo "<pre>".print_r($proxy,TRUE)."<pre>";
	$keyword = $row ["keyword"];
	$keywordurl = preg_replace ( "/^http:\/\//", "", $row ["url"] );
	
	$url = 'http://www.google.de/search?num=100&hl=de&q=' . $keyword . '&btnG=Suche&meta=lr%3Dlang_de&start=0';
	$req = $proxy->request ( $url, '<label for=lgr> Seiten auf Deutsch </label>' );
	if (preg_match_all ( '/<!--m-->(<(link|li|table).*<h3 class=r><a[^>]+>(.*)<\/a><\/h3>.*<(cite|span class=a)>(.*)<\/(cite|span)>.*)<!--n-->/sU', $req, $preg, PREG_SET_ORDER )) {
		$i = 0;
		foreach ( $preg as $arr ) {
			$i ++;
			$title = utf8_decode ( strip_tags ( $arr [3] ) );
			$url = preg_replace ( '/\s.*/s', '', strip_tags ( trim ( $arr [5] ) ) );
			$keywordurlarray = parse_url ( "http://" . $keywordurl );
			if (preg_match ( "/".$keywordurlarray["host"]."/i", $url )) {
				$rankingnr = $i;
				echo "Treffer $keyword  $rankingnr $keywordurlarray[host] \n";
				$treffer = "1";
				break;
			}
		
		}
	}
	
	if ($treffer == "") {
		echo "Keyword nicht gefunden";
	} else {
		#sql zum abspeichern
		$sql = "insert into keywordranking (user ,keyword,url,datum,ranking) values('$row[user]','$row[keyword]','$row[url]',NOW(),'$rankingnr')";
		echo $sql . "<br>\n";
		$result_insert = mysql_query ( $sql );
	
	#echo mysql_error();
	}
	unset ( $treffer, $rankingnr );
}
?>
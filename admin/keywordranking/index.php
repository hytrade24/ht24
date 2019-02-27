<?php
/* ###VERSIONSBLOCKINLCUDE### */


$classpath = str_replace ( "index.php", "class.php", __FILE__ );
include_once ($classpath);

echo "<form action=$_SERVER[PHP_SELF]>";
echo "Keyword: <input type=text name=keyword value=\"$_REQUEST[keyword]\"><br>";
echo "URL: <input type=text name=url value=\"$_REQUEST[url]\">";
echo "<input type=submit value=anzeigen>";
echo "</form>";
if ($_REQUEST ["keyword"] >= " ") {
	$proxy = new proxy ();
	#echo "<pre>".print_r($proxy,TRUE)."<pre>";
	$keyword = $array ["keyword"];
	$keywordurl = preg_replace ( "/^http:\/\//", "", $array ["url"] );
	# debug angaben
	$keyword = $_REQUEST [keyword];
	$keywordurl = $_REQUEST [url];
	# debug angaben
	$url = 'http://www.google.de/search?num=100&hl=de&q=' . $keyword . '&btnG=Suche&meta=lr%3Dlang_de&start=0';
	$req = $proxy->request ( $url, '<label for=lgr> Seiten auf Deutsch </label>' );
	if (preg_match_all ( '/<!--m-->(<(link|li|table).*<h3 class=r><a[^>]+>(.*)<\/a><\/h3>.*<(cite|span class=a)>(.*)<\/(cite|span)>.*)<!--n-->/sU', $req, $preg, PREG_SET_ORDER )) {
		$i = 0;
		foreach ( $preg as $arr ) {
			$title = utf8_decode ( strip_tags ( $arr [3] ) );
			$url = preg_replace ( '/\s.*/s', '', strip_tags ( trim ( $arr [5] ) ) );
			$keywordurlarray = parse_url ( "http://" . $keywordurl );
			if (preg_match( "/".$keywordurlarray["host"]."/i", $url )) {
				$rankingnr = $i;
				echo "Treffer $keyword  $rankingnr $keywordurlarray[host] \n";
				$treffer = "1";
				break;
			}
			++ $i;
		}
	}
	unset ( $rankingnr );
	if ($treffer == "") {
		echo "Keyword nicht gefunden";
	}
}

?>
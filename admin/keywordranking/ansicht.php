<?php
/* ###VERSIONSBLOCKINLCUDE### */


include_once ('charts/php-ofc-library/open-flash-chart-object.php');
$classpath = str_replace ( "ansicht.php", "class.php", __FILE__ );
include_once ($classpath);
$proxy = new proxy ();
$username = "grisu";
#holen der gefunden Keywordranking nach User 
$sql = "select * from keyword where user='" . mysql_real_escape_string ( $username ) . "'";
#echo $sql."<br>\n";
$result = mysql_query ( $sql );
#jeder User kann x Keywords suche lassen
while ( $row = mysql_fetch_assoc ( $result ) ) {
	#echo 'charts/chart-data.php?keyword='.urlencode($row[keyword])."&user=".urlencode($row[user])."&url=".urlencode($row[url])."&title=".urlencode("user: $row[user]")."<br>\n";
	#anzeige der flash grafik
	open_flash_chart_object ( 600, 250, 'charts/chart-data.php?keyword=' . urlencode ( $row [keyword] ) . "&user=" . urlencode ( $row [user] ) . "&url=" . urlencode ( $row [url] ) . "&title=" . urlencode ( "user: $row[user]" ), false );
}

?>
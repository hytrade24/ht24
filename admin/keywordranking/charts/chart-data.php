<?php
/* ###VERSIONSBLOCKINLCUDE### */


include_once ('php-ofc-library/open-flash-chart.php');

include_once ("../class.php");
$proxy = new proxy ();
$g = new graph ();

$title = $_REQUEST ["title"] . " " . $_REQUEST [url] . " " . $_REQUEST [keyword];
$xlabel = array ();

$sql = "select * from keywordranking where keyword='" . mysql_real_escape_string ( $_REQUEST [keyword] ) . "'";
#echo $sql."<br>";
$result = mysql_query ( $sql );
while ( $row = mysql_fetch_assoc ( $result ) ) {
	echo "<pre>" . print_r ( $row, TRUE ) . "<pre>";
	$data [$row [datum]] = $row [ranking];
	$xlabel [] = $row [datum];
	if ($max < $row ["ranking"]) {
		$max = $row ["ranking"];
	}

}
$max = $max + 10;

$g->title ( $title, '{font-size: 16px; color: #4192D9}' );
$g->bg_colour = '#FFFFFF';
$g->line ( 2, '#000000', 18 );
#$g->set_inner_background( '#4192D9', 90 );
$g->x_axis_colour ( '#000000', '#FFFFFF' );
$g->y_axis_colour ( '#000000', '#FFFFFF' );
//


$g->set_data ( $data );
// label each point with its value
$g->set_x_labels ( $xlabel );
$g->set_x_label_style ( 10, '#4192D9', 2 );

// set the Y max
$g->set_y_max ( $max );
// label every 20 (0,20,40,60)
$g->y_label_steps ( $max / 10 );

$g->set_y_label_style ( 10, '#4192D9', 0 );

// display the data
echo $g->render ();

?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */



global $ab_path;

$data = $data_2 = $label = $label_2 = array();

// Array auffüllen
for ($i = 6; $i >= 0; $i--) {
	$data[date("Y-n",mktime(0, 0, 0, date("m")-$i, 1, date('Y')))]="0";
	$label[date("Y-n",mktime(0, 0, 0, date("m")-$i, 1, date('Y')))]=date("Y-n",mktime(0, 0, 0, date("m")-$i, 1, date('Y')));
}

$data_2 = $data;
$label_2 = $label;

$max =  $max_2 = 0;

$ar = $db->fetch_table("
select sum(CREATES) as views ,concat(YEAR(ID_DATE),'-',MONTH(ID_DATE)) as STAMP from ad_log group by YEAR(ID_DATE),MONTH(ID_DATE) order by ID_DATE DESC");

$show = false;

if(!empty($ar))
{
	$show = true;
	for ($i = 0; $i < count($ar); $i++)
	{
		if ($max < $ar[$i]['views'])
		{
			$max = $ar[$i]['views'];
		}

		$data[$ar[$i]['STAMP']]=$ar[$i]['views'];
		$label[$ar[$i]['STAMP']]=$ar[$i]['STAMP'];
	}
}

//$max = ceil($max*1.1);

$ar = $db->fetch_table("
	select sum(SOLD) as views ,concat(YEAR(ID_DATE),'-',MONTH(ID_DATE)) as STAMP from ad_log group by YEAR(ID_DATE),MONTH(ID_DATE) order by ID_DATE DESC");

if(!empty($ar))
{
	$show = true;
	for ($i = 0; $i < count($ar); $i++)
	{
		if ($max_2 < $ar[$i]['views'])
		{
			$max_2 = $ar[$i]['views'];
		}

		$data_2[$ar[$i]['STAMP']]=$ar[$i]['views'];
		$label_2[$ar[$i]['STAMP']]=$ar[$i]['STAMP'];
	}
}

$ab_max = $max;
//$max_2 = ceil($max_2*1.1);

if($show == true)
{
	include_once( $ab_path.'lib/open-flash-chart.php' );

	// generate some random data
	srand((double)microtime()*1000000);
	$g = new graph();
    $g->title( 'Anzeigen / Transaktionen ('.date('d.m.y h:i').')', '{font-size: 10px; color: #736AFF}' );
	$g->set_data( $data );
	$g->set_data( $data_2 );
	$g->attach_to_y_right_axis(2);

	$g->line_dot( 2, 5, '0xf88c00', 'Anzeigenschaltungen', 10 );
	$g->line_dot( 2, 5, '0x000000', 'Transaktionen', 10 );

	$g->set_x_labels( $label );
	$g->set_x_label_style( 10, '0x000000', 0, 2 );

	$g->set_y_max( $ab_max );
	$g->set_y_right_max( $max_2 );

	$g->y_label_steps( 5 );
	$g->set_y_legend( 'Neue Anzeigen', 10, '#f88c00' );

	$g->set_y_right_legend('Transaktionen', 10, '0x000000');

	$g->set_width( '100%' );
	$g->set_height( '200' );

	$g->set_output_type('js');

	$tpl_tmp->addvar("FLASHDATA", $g->render());
}
if(empty($ar) && empty($ar_sales))
{
	$tpl_tmp->addvar("NOT_FOUND", 1);
} // nicht gefunden
?>
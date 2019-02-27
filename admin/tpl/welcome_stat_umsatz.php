<?php
/* ###VERSIONSBLOCKINLCUDE### */



// Array auffüllen
for ($i = 0; $i < 14; $i++) {
	$data[date("Y-n",mktime(0, 0, 0, date("m")-$i, 1, date('Y')))]="0";
	$label[date("Y-n",mktime(0, 0, 0, date("m")-$i, 1, date('Y')))]=date("Y-n",mktime(0, 0, 0, date("m")-$i, 1, date('Y')));
}

$max = 0;

$ar = $db->fetch_table("
	select
		concat(YEAR(STAMP_CREATE), '-', MONTH(STAMP_CREATE)) as STAMP,
		sum(BRUTTO) as views
	from
		invoice
	where
		invoice.STAMP_CREATE >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL 13 MONTH)
		and PAY_STATUS=1
	group by
		YEAR(STAMP_CREATE),
		MONTH(STAMP_CREATE)
	order by
		STAMP_CREATE DESC");

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

$ab_max = $max;

if($show == true)
{
	include_once( $ab_path.'lib/open-flash-chart.php' );

	// generate some random data
	srand((double)microtime()*1000000);
	$g = new graph();

	$g->set_data( $data );

	$g->line( 2, '0x000099', 'Umsatz', 10 );
	//$g->line_hollow( 2, 4, '0x80a033', 'Bounces', 10 );

	$g->set_x_labels( $label );
	$g->set_x_label_style( 10, '0x000000', 0, 2 );

	$g->set_y_max( $ab_max );

	$g->y_label_steps( 5 );
	$g->set_y_legend( 'Umsatz', 12, '#736AFF' );

	$g->set_width( '100%' );
	$g->set_height( '200' );

	$g->set_output_type('js');


	$tpl_content->addvar("FLASHDATA", $g->render());

}
if($show)
{
	$tpl_content->addvar("NOT_FOUND", 1);
} // nicht gefunden

?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */



global $ab_path;

$data = $data_2 = $label = $label_2 = array();

// Array auffüllen
for ($i = 0; $i < 7; $i++) {
	$data[date("Y-n",mktime(0, 0, 0, date("m")-$i, 1, date('Y')))]="0";
	$label[date("Y-n",mktime(0, 0, 0, date("m")-$i, 1, date('Y')))]=date("Y-n",mktime(0, 0, 0, date("m")-$i, 1, date('Y')));
}

$data_2 = $data;
$label_2 = $label;

$max =  $max_2 = 0;

$ar = $db->fetch_table("
	select
		concat(YEAR(user.STAMP_REG), '-', MONTH(user.STAMP_REG)) as STAMP,
		count(user.ID_USER) as views
	from
		user
	WHERE
		STAMP_REG >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL 6 MONTH)
	GROUP BY
		YEAR(user.STAMP_REG),
		MONTH(user.STAMP_REG)
	order by
		user.STAMP_REG DESC");

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

$max = ceil($max*1.1);

$ar = $db->fetch_table("
	select
		concat(YEAR(i.STAMP_CREATE), '-', MONTH(i.STAMP_CREATE)) as STAMP,
		SUM(it.QUANTITY*it.PRICE*(1+(IFNULL(tax.TAX_VALUE, 0)/100))) as views
	from
		billing_invoice i
    LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
            LEFT JOIN tax tax ON tax.ID_TAX = it.FK_TAX
	where
		i.STAMP_CREATE >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL 6 MONTH)
	group by
		YEAR(i.STAMP_CREATE),
		MONTH(i.STAMP_CREATE)
	order by
		i.STAMP_CREATE DESC");

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
$max_2 = ceil($max_2*1.1);

if($show == true)
{
	include_once( $ab_path.'lib/open-flash-chart.php' );

	// generate some random data
	srand((double)microtime()*1000000);
	$g = new graph();

	$g->set_data( $data );
	$g->set_data( $data_2 );
	$g->attach_to_y_right_axis(2);

	$g->line_dot( 2, 5, '0xf88c00', 'Neue Benutzeranmeldungen', 10 );
	$g->line_dot( 2, 5, '0x000000', 'Umsatz im Markt', 10 );

	$g->set_x_labels( $label );
	$g->set_x_label_style( 10, '0x000000', 0, 2 );

	$g->set_y_max( $ab_max );
	$g->set_y_right_max( $max_2 );

	$g->y_label_steps( 5 );
	$g->set_y_legend( 'Neue Benutzer', 10, '#f88c00' );

	$g->set_y_right_legend('Umsatz', 10, '0x000000');

	$g->set_width( '100%' );
	$g->set_height( '200' );

	$g->set_output_type('js');

	$tpl_content->addvar("FLASH_1", $g->render());
}

/*
 *
 * User / Transaktionen
 */
// Array auffüllen
for ($i = 0; $i < 7; $i++) {
	$data[date("Y-n",mktime(0, 0, 0, date("m")-$i, 1, date('Y')))]="0";
	$label[date("Y-n",mktime(0, 0, 0, date("m")-$i, 1, date('Y')))]=date("Y-n",mktime(0, 0, 0, date("m")-$i, 1, date('Y')));
}

$data_2 = $data;
$label_2 = $label;

$max =  $max_2 = 0;

$ar = $db->fetch_table("
	select
		concat(YEAR(user.STAMP_REG), '-', MONTH(user.STAMP_REG)) as STAMP,
		count(user.ID_USER) as views
	from
		user
	WHERE
		STAMP_REG >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL 6 MONTH)
	GROUP BY
		YEAR(user.STAMP_REG),
		MONTH(user.STAMP_REG)
	order by
		user.STAMP_REG DESC");

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

$max = ceil($max*1.1);

$ar = $db->fetch_table("
	select
		concat(YEAR(ad_sold.STAMP_BOUGHT), '-', MONTH(ad_sold.STAMP_BOUGHT)) as STAMP,
		count(ad_sold.ID_AD_SOLD) as views
	from
		ad_sold
	where
		ad_sold.STAMP_BOUGHT >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL 6 MONTH)
	group by
		YEAR(ad_sold.STAMP_BOUGHT),
		MONTH(ad_sold.STAMP_BOUGHT)
	order by
		ad_sold.STAMP_BOUGHT DESC");

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
$max_2 = ceil($max_2*1.1);

if($show == true)
{
	include_once( $ab_path.'lib/open-flash-chart.php' );

	// generate some random data
	srand((double)microtime()*1000000);
	$g = new graph();

	$g->set_data( $data );
	$g->set_data( $data_2 );
	$g->attach_to_y_right_axis(2);

	$g->line_dot( 2, 5, '0xf88c00', 'Neue Benutzeranmeldungen', 10 );
	$g->line_dot( 2, 5, '0x000000', 'Transaktionen im Markt', 10 );

	$g->set_x_labels( $label );
	$g->set_x_label_style( 10, '0x000000', 0, 2 );

	$g->set_y_max( $ab_max );
	$g->set_y_right_max( $max_2 );

	$g->y_label_steps( 5 );
	$g->set_y_legend( 'Neue Benutzer', 10, '#f88c00' );

	$g->set_y_right_legend('Transaktionen', 10, '0x000000');

	$g->set_width( '100%' );
	$g->set_height( '200' );

	$g->set_output_type('js');

	$tpl_content->addvar("FLASH_2", $g->render());
}
?>
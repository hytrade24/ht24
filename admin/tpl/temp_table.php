<?php
/* ###VERSIONSBLOCKINLCUDE### */



if(count($_POST))
{
	$ar_use = array();
	foreach($_POST['feld'] as $feld => $value)
	{
		if(!empty($value))
		{
			$ar_use[$value][] = $feld;
		}
	}
	$_SESSION['IMP'] = array('FIELDS' => $ar_use);
	forward('index.php?page=read_tmp');
	die(1);
}

function makefields(&$row, $i)
{
	global $example;
	$felder = array();
	foreach($row as $key => $value)
	{
		if(empty($example[$key]))
		{
			$example[$key] = $value;
		}
		$felder[] = '<td>'.stdHtmlentities($value)."</td>";
	}
	$row['felder'] = implode("\n", $felder);
}

$example = array();
$npage = ($_REQUEST['npage'] ? $_REQUEST['npage'] : 1);
$perpage = 20;
$limit = ($npage*$perpage)-$perpage;
$orderby = ($_REQUEST['orderby'] ? $_REQUEST['orderby'] : 'ID_P_IMPORT');
$updown = ($_REQUEST['updown'] ? $_REQUEST['updown'] : 'ASC');

$using = array
	(
		'FK_MAN' => 'Hersteller',
		'GRP' => 'Warengruppe',
		'ART' => 'Geräteart',
		'V1' => 'Herstellernummer',
		'V2' => 'Gerätename',
		'T1' => 'Beschreibung',
		'HOEHE' => 'Höhe',
		'BREITE' => 'Breite',
		'TIEFE' => 'Tiefe',
	);

$select = array();
foreach($using as $key => $value)
{
	$select[] = '<option value="'.$key.'">'.stdHtmlentities($value).'</option>';
}
$select = implode("\n", $select);


$all = $db->fetch_atom("
	SELECT
		COUNT(*)
	FROM
		p_import");

$query = "
	SELECT
		*
	FROM
		p_import
	ORDER BY
		".$orderby." ".$updown."
	LIMIT
		".$limit.", ".$perpage;
$ar = $db->fetch_table($query);
$tpl_content->addlist("liste", $ar, "tpl/de/temp_table.row.htm", "makefields");

/**
 * Get Table Fields
 */
$res = $db->querynow("
	SHOW FIELDS
		from
	p_import");

$felder = $th = array();
while($row = mysql_fetch_assoc($res['rsrc']))
{
	$felder[] = array
		(
			'field' => $row['Field'],
			'opts' => $select,
			'EXAMPLE' => $example[$row['Field']],
		);
	$th[] = '<th>'.$row['Field'].'</th>';
}

$tpl_content->addvar('th', '<tr>'.implode("\n", $th).'</tr>');

$tpl_content->addlist("felder", $felder, "tpl/de/tmp_table.field.htm");

$tpl_content->addvar("pager", htm_browse($all, $npage, 'temp_table&orderby='.$orderby.'&updown='.$updown."&npage=", $perpage));

?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */



$konf = $_SESSION['IMP']['FIELDS'];

$use = array();

foreach($konf as $key => $value)
{
	for($i=0; $i<count($value); $i++)
	{
		$use[$value[$i]] = $key;
	}
}

$res = $db->querynow("
	SELECT
		*
	FROM
		p_import
	ORDER BY
		ID_P_IMPORT ASC
	LIMIT 200");
$del = array();
while($row = mysql_fetch_assoc($res['rsrc']))
{
	$prod = array();

	foreach($row as $key => $value)
	{
		if($use[$key])
		{
			if(!empty($value))
			{
				$prod[$use[$key]][] = trim($value);
			}
		}	// use this key
	}	// foreach $row
	foreach($prod as $key => $value)
	{
		$prod[$key] = implode("\n", $value);
	}
	if($prod['FK_MAN'])
	{
		$fk = $db->fetch_atom("
			SELECT
				ID_MAN
			FROM
				manufacturers
			WHERE
				`NAME`='".sqlString($prod['FK_MAN'])."'");
		if(!$fk)
		{
			$ar_man = array('NAME' => $prod['FK_MAN']);
			$fk = $db->update('manufacturers', $ar_man);
		}
		$prod['FK_MAN'] = (int)$fk;

		$id = $db->fetch_atom("
			SELECT
				FK
			FROM
				string_product
			WHERE
				V1='".sqlString($prod['V1'])."'
				AND BF_LANG=".$langval);
		if($id)
		{
			$prod['ID_PRODUCT'] = $id;
		}
		$db->update("product", $prod);
		#echo ht(dump($prod));
	}
	$del[] = $row['ID_P_IMPORT'];
}	// while

$db->querynow("
	DELETE
		FROM
		p_import
	WHERE
		ID_P_IMPORT IN (".implode(",", $del).")");

$all = $db->fetch_atom("
	SELECT
		COUNT(*)
	FROM
		p_import");
if($all == 0)
{
	$tpl_content->addvar("ready", 1);
}
else
{
	$tpl_content->addvar("data", $all);
}

?>
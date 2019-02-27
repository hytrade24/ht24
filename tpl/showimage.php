<?php
/* ###VERSIONSBLOCKINLCUDE### */


	if (isset($ar_params[1]) && $ar_params[1] != "")
	{
		$img = str_replace("~", "/", $ar_params[1]);
		$query = 'select SRC, WIDTH, HEIGHT, ALT, TITLE, IMG_TEXT from img where SRC = "'.$img.'"';
				
		$res = $db->fetch1($query);
				
		if ($res)
		{
			$tpl_content->addvars($res);
		}
		else
		{
			$tpl_content->addvar("err", 1);
		}
	}
	else
	{
		$tpl_content->addvar("err", 1);
	}

?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */



$step = ($_REQUEST['STEP'] ? $_REQUEST['STEP'] : 1);
$tpl_content->addvar("STEP", $step);

switch($step)
{
	case 1:
		if(!empty($_FILES['csv']['tmp_name']))
		{
			$time = microtime();
			$filename = str_replace(' ', '', $time).'.csv';
			move_uploaded_file($_FILES['csv']['tmp_name'], $ab_path.'cache/'.$filename);
			chmod($ab_path.'cache/'.$filename, 0777);
			forward('index.php?page=prod_import&STEP=2&file='.$filename);
			die(1);
		}	// files
		elseif(count($_POST))
		{
			if($_POST['DATEI'])
			{
				system($str = "mv ".$ab_path.'import/csv/'.$_POST['DATEI'].' '.$ab_path.'cache/'.$_POST['DATEI']);
				#echo $str; die();
				forward('index.php?page=prod_import&STEP=2&file='.urlencode($_POST['DATEI']));
				die(1);
			}
			else
			{
				$tpl_content->addvar("err", "Bitte w&auml;hlen Sie eine Datei aus, oder laden eine von Ihrer Festplatte");
			}
		}
		else
		{
			$res = opendir($ab_path.'import/csv');
			$ar_files = array();
			while (false !== ($file = readdir($res)))
			{
				if(strstr($file, '.csv'))
				{
					$ar_files[] = '<option value="'.$file.'">'.$file.'</option>';
				}
			}
			$tpl_content->addvar("files", implode("\n", $ar_files));
		}
		break; // step 1
	case 2:
		$file = urldecode($_REQUEST['file']);
		$tpl_content->addvar("file", $file);
		if(count($_POST))
		{
			$found = array();
			foreach($_POST['feld'] as $key => $value)
			{
				if($value == 'USE')
				{
					$found[$key] = $value;
				}
			}
			if(!count($found))
			{
				$tpl_content->addvar("err", 'Bitte verwenden Sie mindestens ein Feld');
			}
			else
			{
				if($_POST['USE_TOP'])
				{
					$file = file($ab_path.'cache/'.$file);
					$tmp = array_shift($file);
					unset($file);
					$fields = array('ID_P_IMPORT BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
					$fieldnames = explode(";", $tmp);
					for($i=0; $i<count($fieldnames); $i++)
					{
						$str = trim(strtolower($fieldnames[$i]));
						$str = str_replace(array('ä', 'ö', 'ü', 'ß'), array('ae', 'oe', 'ue', 'ss'), $str);
						$str = preg_replace("/[^a-z0-9]/si", '_', $str);
						$str = preg_replace("/(_{2,})/si", '_', $str);
						$str = strtoupper($str);
						$fields[] = "`".$str."`";
					}
				}	// überschriften
				else
				{
					$i=1;
					$fields = array('ID_P_IMPORT BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
					foreach($found as $key => $x)
					{
						$fields[] = 'FELD'.$i;
						$i++;
					}
				}
				$res = $db->querynow("drop table if exists p_import");
				#echo ht(dump($lastresult));
				$query = $query_f = array();
				$query[] = "create table p_import (";
				for($i=0; $i<count($fields); $i++)
				{
					$query_f[] = $fields[$i]." ".($i>0 ? "varchar(255)" : "");
				}
				$query[] = implode(",\n", $query_f);
				$query[] = ",PRIMARY KEY(`ID_P_IMPORT`)";
				$query[] = ")";
				$res = $db->querynow(implode("\n", $query));
				$_SESSION['IMPORT'] = array
					(
						'SETTINGS' => $found,
						'USE_TOP' => $_REQUEST['USE_TOP'],
					);
				die(forward("index.php?page=prod_import&file=".$_REQUEST['file']."&STEP=3&RUN=1"));
			}
		}
		$file = file($ab_path.'cache/'.$file);
		$ar = array();
		for($i=0; $i<11; $i++)
		{
			$tmp = array_shift($file);
			if(!empty($tmp))
			{
				$ar[] = $tmp;
			}
		}
		unset($file);
		if(!empty($ar))
		{
			$opts = '<option value="USE">Feld verwenden</option>
				<option value="">Feld NICHT verwenden</option>';

			$cols = explode(";", $ar[0]);
			$n = count($cols);
			if($n < 2)
			{
				$tpl_content->addvar("err", "Die CSV Datei muss mind. 3 Spalten enthalten!");
			}
			else
			{
				$tpl_content->addvar('CSPAN', ($n-1));
				$lines = array();
				$select = array();
				for($i=0; $i<$n; $i++)
				{
					$select[] = '<select name="feld['.$i.']">
						'.$opts.'
						</select>';
				}
				$lines[] = '<td>'.implode('</td><td>', $select).'</td>';
				for($i=0; $i<count($ar); $i++)
				{
					$cols = explode(";", $ar[$i]);
					$lines[] = '<td>'.implode('</td><td>', $cols).'</td>';
				}
				$lines[0] = str_replace('td>', 'th>', $lines[0]);
				$lines = '<tr>'.implode('</tr><tr>', $lines).'</tr>';
				$tpl_content->addvar("lines", $lines);
			}
		}
		else
		{
			$tpl_content->addvar("err", "CSV Datei konnte nicht gelesen werden!");
		}
		break; // step 2
	case 3:
		$file = file($path = $ab_path.'cache/'.urldecode($_REQUEST['file']));

		$start_line = ($_SESSION['IMPORT']['USE_TOP'] && $_REQUEST['RUN'] == '1' ? 1 : 0);
		$start_line_no = $_SESSION['IMPORT']['IMPORTED'] + 1;
		//echo "start: ".$start_line;
		$ar_man = array();
		$settings = $_SESSION['IMPORT']['SETTINGS'];
		$use_name = $_SESSION['IMPORT']['USE_NUM_AS_NAME'];
		$ar_feld = array();
		foreach($settings as $key => $value)
		{
			$ar_feld[$value] = $key;
		}
		$all=0;
		$inserts = array();
		for($i=0; $i<101; $i++)
		{
		#echo "i = ". $i."<br />";
			if($i > count($file))
			{
				/*echo ht(dump($file));
				echo 'start bei: '.$start_line." count:: ".count($file);
				die("<br />halt ma"); */
				if(empty($inserts))
				{
					die(forward('index.php?page=prod_import_ready'));
				}
				else
				{
				#die(ht(dump($inserts)));
					break;
				}
			}
			$line = array_shift($file);
			if($start_line == 1 && $i == 0)
			{
				continue;
			}
			$values = explode(";", $line);
			$insert = array("''");
			for($k=0; $k<count($values); $k++)
			{
				$values[$k] = trim($values[$k]);
				$insert[] = "'".sqlString($values[$k])."'";
			}
			if(!empty($insert))
			{
				$inserts[] = "(".implode(", ", $insert).")";
				$all++;
			}
		}
		if (!empty($file)) {
			$res = $db->querynow("INSERT INTO
				p_import
				VALUES
				".implode(",\n", $inserts));
			if(!empty($res['str_error']))
			{
				var_dump($inserts);
				echo("Fehler in Zeilen ".$start_line_no." bis ".($start_line_no+101)."!<br />\n");
				die("MySQL-Fehler: ".$res['str_error']);
			}
		} else {
			die(forward('index.php?page=prod_import_ready'));
		}
		$_SESSION['IMPORT']['IMPORTED'] += $all;
		$tpl_content->addvar("N_PRODS", $_SESSION['IMPORT']['IMPORTED']);
		file_put_contents($ab_path.'cache/'.$_REQUEST['file'], implode("", $file));
		chmod($ab_path.'cache/'.$_REQUEST['file'], 0777);
		$tpl_content->addvar("FILE", $_REQUEST['file']);
		$tpl_content->addvar('RUN', time());
		//die(forward("index.php?page=prod_import&file=".$_REQUEST['file']."&STEP=3&RUN=1"));
		break; // step 3
}	// steps

?>
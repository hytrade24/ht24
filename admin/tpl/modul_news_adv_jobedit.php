<?php
if ($n_tab = (int)$_REQUEST['tab'])
$tpl_content->addvar('tab', $n_tab);

$id = (int)$_REQUEST['ID_JOB'];
$err = array ();
if(isset($_REQUEST['do']) && $_REQUEST['do'] == 'delimg')
{
	$db->update("job", array ('IMG' => NULL,'IMGW' => NULL, 'IMGH' => NULL,'ID_JOB' => $_REQUEST['ID_JOB']));
}
if (count($_POST))
{
	date_implode($_POST,'STAMP');
    date_implode($_POST,'STAMPEND');
	$tpl_content->addvar('IMGFLOAT_'.$_POST['IMGFLOAT'], 1);
	$_POST['B_TOP'] = (!empty($_POST['B_TOP']) ? $_POST['B_TOP'] : NULL);
	recurse($_POST, '$value=trim($value)');
	if (is_array ($_POST['OK']))
	$_POST['OK'] = array_sum($_POST['OK']);
	if (!$_POST['V1'])
	$err[] = 'Titel fehlt.';
	#  if (!$_POST['V2'])
	#    $err[] = 'Kurztext fehlt.';
	elseif (255<strlen($_POST['V2']))
	$err[] = 'Kurztext zu lang (max 255 Zeichen).';
	if (!$_POST['T1'])
	$err[] = 'Inhalt fehlt.';
	if (!empty($_POST['WITH_COSTS']) && !empty($_POST['NUMBER_OF_COINS']) && !preg_match("/^[0-9]{1,}$/", $_POST['NUMBER_OF_COINS'])) {
		$err[] = 'Die Anzahl Coins muss eine Ganzzahl sein.';
	}
	if (!empty($_POST['WITH_COSTS']) && empty($_POST['NUMBER_OF_COINS'])) {
		$err[] = 'Bitte Anzahl Coins angeben!';
	}

	if ($_POST['link'])
	{
		$ar_links = array ();
		$n = 0;
		foreach($_POST['link'] as $i=>$ar)
		{
			$ar_tmp = array ();
			if ($ar['href'])
			{
				#die(ht(dump($_SERVER)));
				$ar_tmp[] = '<a';
				if (preg_match('/^[a-z]+:\/\//', $ar['href']))
				{
					//echo "http ist drin";
					$s_href = $ar['href'];
					$ar_tmp[] = ' target="_blank"';
				}
				else
				$s_href = 'http://'. $_SERVER['HTTP_HOST']
				. (preg_match('/^\//', $ar['href']) ? '' : '/'). $ar['href'] ;
				#echo $s_href, '<br>';
				#$s_href .= $ar['href'];
				echo $s_href."<br />";
				if (!validate_url($s_href))
				$err[] = 'Link '. $i. ' ung&uuml;ltig';
				$ar_tmp[] = ' href="'. $ar['href']. '">';
			}
			elseif ($ar['label'])
			$ar_tmp[] = '<!-- a -->';
			if ($ar['label'])
			$ar_tmp[] = $ar['label'];
			if ($ar['href'])
			$ar_tmp[] = '</a>'. ($ar['label'] ? ' ' : '');
			elseif ($ar['label'])
			$ar_tmp[] = '<!-- /a -->';
			if (count($ar_tmp))
			{
				$ar_links[] = '<!-- Link '. $i. ' -->';
				while ($v = array_shift($ar_tmp))
				$ar_links[] = $v;
				$ar_links[] = '<br />';
			}
		}
		$ar_links[] = (count($ar_links) ? '<!-- Link 9999 -->' : NULL);
		$_POST['LINKS'] = implode('', $ar_links);
		#die(ht(dump($_POST['LINKS'])));
	}
	else
	$_POST['link'] = array ();

	if (!count($err))
	{
		$_POST['T1']=stripslashes ($_POST['T1']);

		if (!$id)
		{
			$_POST['FK_USER'] = $uid;
			//$_POST['STAMP']=date("Y-m-d");
		}

		if(empty($_POST['FK_AUTOR']))
		$_POST['FK_AUTOR']=$uid;

		if (empty($_POST['WITH_COSTS'])) {
			$_POST['WITH_COSTS'] = 0;
		}

		$id = $db->update('job', $_POST);


        $db->querynow("set @counter := 0");
       	$db->querynow("update job set JOBNUMBER = @counter := @counter + 1 where ok = 3 ORDER BY STAMP DESC ,ID_JOB DESC;");

		//suchindex updaten
		#require_once ("sys/lib.search.php");
		#$search = new do_search($s_lang,false);
		#$search->add_new_text($_POST['T1'].' '.$_POST['V1'],$id,'job');


		#die(ht(dump($lastresult)));
		if (!$id)
		  $err[] = 'Fehler beim Speichern.';
		else
		 {
		  $db->querynow("update job 
                   set
                   STAMPEND=DATE_ADD(now(),INTERVAL ".$nar_systemsettings['jobs']['runtime']." DAY) 
                   where ok = 3 and STAMPEND IS NULL");
		  forward('index.php?frame=content&nav='. $id_nav. '&ID_JOB='. $id. '&tab='. $n_tab);
            }
	}
	else // Links wieder fuers Formular aufbereiten
	{
		#echo listtab($_POST['link']);
		foreach($_POST['link'] as $i=>$ar)
		foreach($ar as $k=>$v)
		$_POST['link'. $i. '_'. $k] = $v;
	}
}
if ($id) {
	$data = $db->fetch1($db->lang_select('job',"*,uu.NAME as NAME_ "). '
    left join user uu on t.FK_AUTOR = uu.ID_USER
   where ID_JOB='. $id. ($db->perm_check('job_all', PERM_EDIT) ? '' : ' and t.FK_USER='. $uid));
	// Links auseinander nehmen
	#$data['LINKS'] = '<!-- Link 0 -->Quelle: <!-- Link 1 --><a target="_blank" href="http://www.heise.de/newsticker/meldung/61589">heise.de</a> <!-- Link 2 --><!-- a -->nixlinkabertext<!-- /a --><!-- Link 3 --><!-- a -->keine url, aber label<!-- /a -->und text <!-- Link 4 --><a href="url.htm"></a>aber kein label <!-- Link 5 --><a href="nururl.htm"></a>';
	$ar = preg_split('/(\<br \/\>)?<!-- Link \d+ --\>/', $data['LINKS'], -1,
	PREG_SPLIT_NO_EMPTY);

	$i=1;
	foreach($ar as $s)
	{
		if (preg_match('/^\<a\s.*\bhref="(.*)"\>(.*)\<\/a\>\s*(.*)\s*$/U', $s, $ar_match))
		{
			list($dummy, $s_href, $s_label, $s_text) = $ar_match;
			#echo '<hr>A', $i, ht(dump($ar_match)), "<b>href</b> $s_href<br><b>labl</b> $s_label<br><b>text</b> $s_text";
			#      $i = max($i, 1);
		}
		elseif (preg_match('/^\<!-- a --\>(.*)\<!-- \/a --\>\s*(.*)\s*$/U', $s, $ar_match))
		{
			list($dummy, $s_label, $s_text) = $ar_match;
			$s_href = '';
			#echo '<hr>B', $i, ht(dump($ar_match)), "<b>href</b> $s_href<br><b>labl</b> $s_label<br><b>text</b> $s_text";
			#      $i = max($i, 1);
		}
		else
		{
			$s_href = $s_label = '';
			$s_text = $s;
			#echo '<hr>C', $i, ht(dump($s_text)), "<b>href</b> $s_href<br><b>labl</b> $s_label<br><b>text</b> $s_text";
		}
		$tpl_content->addvar('link'. $i. '_href', trim($s_href));
		$tpl_content->addvar('link'. $i. '_label', trim($s_label));
		$tpl_content->addvar('link'. $i. '_text', trim($s_text));
		$i++;
	}
}

if (!$data)
	$data = $db->fetch_blank('job');
else
	$tpl_content->addvar('IMGFLOAT_'.$data['IMGFLOAT'], 1);
	
if (count($err))
{
	$tpl_content->addvar('err', implode('<br />', $err));
	$data = ($data ? array_merge($data, $_POST) : $_POST);
}
$tpl_content->addvars($data);
$tpl_content->addvar("npage", $_REQUEST['npage']);

// Alternative fuer {if OK & 2}3{else}1{endif} : {=1+{={OK}&2}}
?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */


if ($n_tab = (int)$_REQUEST['tab'])
$tpl_content->addvar('tab', $n_tab);

/** /
$nar_trans = array (
  'country' => "LABEL, NULL, NULL",
  'lookup' => "LABEL, NULL, NULL",
  'nav' => "LABEL, NULL, NULL",
  'navadmin' => "LABEL, NULL, NULL",
  'news' => "SUBJECT, TEASE, BODY"
);
foreach($nar_trans as $s_table=>$s_trans)
{
  $db->query("insert into string (S_TABLE, FK, BF_LANG, V1, V2, T1)
    select '$s_table', ID_". strtoupper($s_table). ", 128, ". $s_trans. "
  from `$s_table`");
  $db->query("alter table $s_table add BF_LANG tinyint unsigned not null");
  $db->query("update $s_table set BF_LANG=128");
}
echo implode(';<br />', $db->q_queries), ';';
$db->rollback;
/**/

$id = (int)$_REQUEST['ID_NEWS'];
$err = array ();
if(isset($_REQUEST['do']) && $_REQUEST['do'] == 'delimg')
{
	include_once "sys/lib.cache.php";
	$db->update("news", array ('IMG' => NULL,'IMGW' => NULL, 'IMGH' => NULL,'ID_NEWS' => $_REQUEST['ID_NEWS']));
}
if (count($_POST))
{
	date_implode($_POST,'STAMP');
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
					$ar_tmp[] = ' target="_blank" rel="nofollow"';
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
				$ar_links[] = '<li>';
				while ($v = array_shift($ar_tmp))
				$ar_links[] = $v;
				$ar_links[] = '</li>';
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
		if ( $_POST["STREET"] != "" || $_POST["ZIP"] != "" || $_POST["CITY"] != "" || $_POST["FK_COUNTRY"] != "" ) {
			$mapsLanguage = $s_lang;

			$q_country = 'SELECT s.V1
					FROM country c
					INNER JOIN string s
					ON c.ID_COUNTRY = '.$_POST["FK_COUNTRY"].'
					AND s.S_TABLE = "COUNTRY"
					AND s.FK = c.ID_COUNTRY
					INNER JOIN lang l
					ON l.ABBR = "'.$s_lang.'"
					AND s.BF_LANG = l.BITVAL';

			$geoCoordinates = Geolocation_Generic::getGeolocationCached(
				$_POST["STREET"],
				$_POST["ZIP"],
				$_POST["CITY"],
				$db->fetch_atom($q_country),
				$mapsLanguage
			);
			$_POST["LATITUDE"] = $geoCoordinates['LATITUDE'];
			$_POST["LONGITUDE"] = $geoCoordinates['LONGITUDE'];
		}

		$id = $db->update('news', $_POST);

        $db->querynow("update news set NEWSNUMBER = 0");
        $db->querynow("set @counter := 0");
        $db->querynow("update news set NEWSNUMBER = @counter := @counter + 1 where ok = 3 ORDER BY STAMP DESC ,ID_NEWS DESC;");

		//suchindex updaten
		require_once ("sys/lib.search.php");
		$search = new do_search($s_lang,false);
		$search->add_new_text($_POST['T1'].' '.$_POST['V1'],$id,'news');


		#die(ht(dump($lastresult)));
		if (!$id)
		$err[] = 'Fehler beim Speichern.';
		else
		forward('index.php?frame=content&nav='. $id_nav. '&ID_NEWS='. $id. '&tab='. $n_tab);
	}
	else // Links wieder fuers Formular aufbereiten
	{
		#echo listtab($_POST['link']);
		foreach($_POST['link'] as $i=>$ar)
		foreach($ar as $k=>$v)
		$_POST['link'. $i. '_'. $k] = $v;
	}
}
#echo dump($db->perm_check('news_all', PERM_EDIT));echo ht(dump($lastresult));die();
if ($id)
{
	$data = $db->fetch1($db->lang_select('news',"*,uu.NAME as NAME_ "). '
    left join user uu on t.FK_AUTOR = uu.ID_USER
   where ID_NEWS='. $id. ($db->perm_check('news_all', PERM_EDIT) ? '' : ' and t.FK_USER='. $uid));
	// Links auseinander nehmen
	#$data['LINKS'] = '<!-- Link 0 -->Quelle: <!-- Link 1 --><a target="_blank" href="http://www.heise.de/newsticker/meldung/61589">heise.de</a> <!-- Link 2 --><!-- a -->nixlinkabertext<!-- /a --><!-- Link 3 --><!-- a -->keine url, aber label<!-- /a -->und text <!-- Link 4 --><a href="url.htm"></a>aber kein label <!-- Link 5 --><a href="nururl.htm"></a>';
	$ar = preg_split('/(\<br \/\>)?<!-- Link \d+ --\>/', $data['LINKS'], -1,
	PREG_SPLIT_NO_EMPTY);
	#echo ht($data['LINKS']);
	#echo ht(dump($ar));
	$i=1;
	foreach($ar as $s)
	{
		$s = str_replace(array('<li>', '</li>'), '', $s);
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
    $tpl_content->addvar('link_count', $i-1);
}
#echo ht(dump($lastresult));
#echo dump($db->perm_check('news_all'));
if (!$data)
{
$data = $db->fetch_blank('news');
} else {
$tpl_content->addvar('IMGFLOAT_'.$data['IMGFLOAT'], 1);
}
if (count($err))
{
	$tpl_content->addvar('err', implode('<br />', $err));
	$data = ($data ? array_merge($data, $_POST) : $_POST);
}

$vorlagen_temp = scandir('vorlagen');
$vorlagen = array();
foreach($vorlagen_temp as $vorlage) {
    if(substr($vorlage, -3) == "htm") {
        $name = substr($vorlage, 0, -4);
        $image = 'vorlagen/'.$name.".png";
        if(!file_exists($image)) {
            $image = 'vorlagen/no_image_vorlage.gif';
        }
        $vorlagen[] = array('name' => $name, 'content' => file_get_contents('vorlagen/'.$vorlage), 'image' => $image);
    }
}
$tpl_content->addlist("vorlagen", $vorlagen, "tpl/".$s_lang."/modul_news_adv_edit.vorlage.htm");

$tpl_content->addvars($data);
$tpl_content->addvar("npage", $_REQUEST['npage']);

// Alternative fuer {if OK & 2}3{else}1{endif} : {=1+{={OK}&2}}
?>
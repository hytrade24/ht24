<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.cache.adapter.php';
$cacheAdapter = new CacheAdapter();

$id=(int)$_REQUEST['ID_LANG'];

if (count($_POST))
{
  $err = array ();
  if (!preg_match('/^[a-z]{2}$/', $s_abbr = $_POST['ABBR'] = strtolower($_POST['ABBR'])))
    $err[] = 'Bitte geben Sie zwei Buchstaben [a-z] als Abk&uuml;rzung an';
  elseif ((int)$db->fetch_atom("select count(*) from lang where ABBR='$s_abbr'"
    . ($id ? " and ID_LANG<>$id" : '')))
    $err[] = "Das K&uuml;rzel '$s_abbr' ist bereits vergeben.";
  if (!($_POST['V1']=trim($_POST['V1'])))
    $err[] = 'Bitte geben Sie einen Namen an';

  if (!count($err))
  {
    if ($id)
    {
      $s_abbr_old = $db->fetch_atom("select ABBR from lang where ID_LANG=$id");
      if (strcmp($s_abbr_old, $s_abbr))
      {
        // Verzeichnisse umbe nennen
         /* @rename('tpl/' . $s_abbr_old, 'tpl/' . $s_abbr);

          require_once $ab_path . 'sys/lib.template.design.php';
          $designManagement = TemplateDesignManagement::getInstance($db);

          foreach ($designManagement->fetchAllTemplates() as $key => $design) {
              rename('../design/'.$design['ident'].'/'.$s_abbr_old, '../design/'.$design['ident'].'/'. $s_abbr);
          }*/
      }
    }
    else
    {
      #mkdir('tpl/'. $s_abbr);
      //mkdir('../tpl/'. $s_abbr);


        $sql = "
            CREATE TABLE `searchdb_badword_".$s_abbr."` (
              `ID_BADWORD` int(10) unsigned NOT NULL auto_increment,
              `badword` char(50) NOT NULL default '',
              PRIMARY KEY  (`ID_BADWORD`),
              UNIQUE KEY `badwort` (`badword`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
           	";
		$db->querynow($sql);

        $sql = "CREATE TABLE `searchdb_index_".$s_abbr."` (
              `FK_WORDS` bigint(10) unsigned NOT NULL default '0',
              `FK_ID` bigint(20) unsigned NOT NULL default '0',
              `S_TABLE` char(15) NOT NULL default '',
              `SCORE` tinyint(3) unsigned NOT NULL default '1',
              `DIR` varchar(100) NOT NULL default '',
              `FILE` varchar(100) NOT NULL default '',
              PRIMARY KEY  (`FK_ID`,`FK_WORDS`,`S_TABLE`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
            ";
		$db->querynow($sql);

        $sql = "CREATE TABLE `searchdb_words_".$s_abbr."` (
              `ID_WORDS` bigint(10) unsigned NOT NULL auto_increment,
              `wort` char(50) NOT NULL default '',
              PRIMARY KEY  (`ID_WORDS`),
              UNIQUE KEY `wort` (`wort`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;

        ";
        $db->querynow($sql);

    }
    $id = $db->update('lang', $_POST);

    $liste = $db->fetch_table($db->lang_select('lang'). 'where B_PUBLIC=1 order by BITVAL desc', 'ABBR');
    $s_code = '<?'. 'php $lang_list = '. php_dump($liste). '; ?'. '>';
    $fp = fopen($c_file = '../cache/lang.'. $s_lang. '.php', 'w');
    fputs($fp, $s_code);
    fclose($fp);
    chmod($c_file, 0777);

	$cacheAdapter->cacheContent();

#die(ht(dump($liste)));
    $tmp = array_values($liste);



    $s_code = '<?'. 'php
if (($s_lang = $_REQUEST["lang"]) || (SESSION && ($s_lang = $_SESSION["lang"])))
{
  @include "cache/lang.$s_lang.php";
  if (!$lang_list)
    $s_lang = false;
}
if (!$s_lang)
{
  $s_lang = "'. $tmp[0]['ABBR']. '";
  @include "cache/lang.$s_lang.php";
}
$langval = $lang_list[$s_lang]["BITVAL"];
if (SESSION)
  $_SESSION["lang"] = $s_lang;
else
  $ar_urlrewritevars["lang"] = $s_lang;
?'. '>';
    $fp = fopen($c_file = '../cache/lang.php', 'w');
    fputs($fp, $s_code);
    fclose($fp);
    chmod($c_file, 0777);
    forward('index.php?nav='. $id_nav. '&ID_LANG='. $id);
#    str_set('lang', $id, $langval, $_POST);

  }


  $tpl_content->addvar('err', implode('<br />', $err));
}

/**/
//$string = $db->lang_select('lang'). 'order by BITVAL desc');
$liste = $db->fetch_table($db->lang_select('lang'). 'order by BITVAL desc');
/*/
$liste = $db->fetch_table("select g.*, s.V1 from lang g
  left join string s on s.S_TABLE='lang' and s.FK=g.ID_LANG
    and s.BF_LANG=if(g.BF_LANG & $langval, $langval, 1 << floor(log2(g.BF_LANG+0.5)))
  order by g.BITVAL desc");
/**/
$ar_liste = array ();

if (!$id && count($liste)<8)
{
  $ar_tmp = $ar_opts = array ();
  foreach($liste as $dummy=>$row)
    $ar_tmp[$row['BITVAL']] = $row['BITVAL'];
  for ($n=128; $n>=1; $n>>=1) if (!$ar_tmp[$n])
    $ar_opts[] = '
    <option '. ($_REQUEST['BITVAL']==$n ? 'selected ':''). 'value="'. $n. '">'. $n. '</option>';
  $_POST['opts_bitval'] = $ar_opts;
  array_unshift($liste, $_POST);
}
else
  array_unshift($liste, array (
    'V1'=>'neue Sprache'
  ));

foreach($liste as $i=>$row)
{
  $tpl_tmp = new Template('tpl/de/'.
    ($id==$row['ID_LANG'] ? 'sprachen.editrow' : 'sprachen.row'). '.htm', 'sprachen'
  );
  $tpl_tmp->addvars($row);
  $tpl_tmp->addvar('i', $i);
  $ar_liste[] = $tpl_tmp;
}
$tpl_content->addvar('sprachen', $ar_liste);
$tpl_content->addvar('aktdomain',str_replace("http://", "", $nar_systemsettings['SITE']['SITEURL']));

?>
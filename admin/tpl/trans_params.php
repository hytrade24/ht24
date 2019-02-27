<?php
/* ###VERSIONSBLOCKINLCUDE### */


// Funktionen ------------------------------------------------------------------
  function plabel($s_table)
  {
    static $nar_p = array (
      '' => 'Kernel',
      'app' => 'Anwendung',
      'c' => 'Content',
      'kat' => 'Kategorien'
    );
    return (($s=$nar_p[$s_table]) ? $s : $s_table);
  }

  function flabel($s_table)
  {
    static $nar_f = array (
      'country' => 'Länder',
      'kat_option' => 'Kategorie-Optionen',
      'lang' => 'Sprachen',
      'lookup' => 'Lookups',
      'nav' => 'Navigation',
      'faqkat' => 'FAQ-Kategorien',
      'message' => 'Skript-Meldungen',
      'anzeige' => 'Anzeigen',
      'faq' => 'FAQ',
      'news' => 'News',
      'news_key' => 'News / Schlüsselwörter',
      'attr' => 'Attribute',
      'attr_group' => 'Attributgruppen',
      'attr_option' => 'Attribut-Optionen',
      'kat' => 'Kategorien'
    );
    return (($s=$nar_f[$s_table]) ? $s : $s_table);
  }

  function getstrfields($s_ptable, $s_ftable)
  {
    $ar_tmp = $GLOBALS['db']->fetch1("select
      if (sum(V1 is not null), 'V1', '') v1,
      if (sum(V2 is not null), 'V2', '') v2,
      if (sum(T1 is not null), 'T1', '') v3
    from `$s_ptable` where S_TABLE='$s_ftable'");
    $ar_ret = array ();
    foreach($ar_tmp as $v) if ($v) $ar_ret[] = $v;
    return $ar_ret;
  }
// Parameter -------------------------------------------------------------------
  $do = $_REQUEST['do'];

  // Sprachen
  if ($id_lang0 = (int)$_REQUEST['L0_ID_LANG'])
    ;
  else
    $id_lang0 = (int)$_SESSION['translate.L0'];

  if ($id_lang1 = (int)$_REQUEST['L1_ID_LANG'])
    ;
  else
    $id_lang1 = (int)$_SESSION['translate.L1'];

  if (!$id_lang1)
    $id_lang1 = $db->fetch_atom("select ID_LANG from lang
      order by B_PUBLIC desc, BITVAL desc limit 1");

  if ($id_lang1==$id_lang0)
    $id_lang0 = 0;

  $_SESSION['translate.L0'] = (int)$id_lang0;
  $_SESSION['translate.L1'] = (int)$id_lang1;

?>
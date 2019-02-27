<?php
/* ###VERSIONSBLOCKINLCUDE### */


  global $id_kat, $s_lang;

  function sc_sort_rating(&$row, $i) {
    if(empty($row['RATING_LAST']))
    {
      $row["RANK"] = '<font color="black" size="1">neu</font>';
      $row["COLOR"] = '<font color="black">';
      $row['RATING_LAST'] = '';
    } else {
      if($row['RATING'] < $row['RATING_LAST'])
      {
        $row["RANK"] = '<img src="/bilder/down.png">';
        $row["COLOR"] = '<font color="red">';
      } else if($row['RATING'] > $row['RATING_LAST']) {
        $row["RANK"] = '<img src="/bilder/up.png">';
        $row["COLOR"] = '<font color="green">';
      } else {
        $row["RANK"] = '<img src="/bilder/stay.png">';
        $row["COLOR"] = '<font color="black">';
      }
      $row['RATING_LAST'] = ' <font color="gray" size="1">('.$row['RATING_LAST'].')</font>';
    }
  }

  function correct_clicks(&$row, $i) {
  	if (!isset($row["CLICKS"])) $row["CLICKS"] = 0;
  }

  // Zeit nach der die Datei neu geschrieben wird:
  $seconds_recache = 300; // 5min

  $cache_file = $ab_path."cache/kats/top/scripte.kat".$id_kat.".".$s_lang.".htm";
  $cache_lastupdate = false;
  if (file_exists($cache_file)) {
    $cache_lastupdate = filemtime($cache_file);
  }
  if (!$cache_lastupdate || ((time()-$cache_lastupdate) > ($seconds_recache))) {
    $tpl_box = new Template("tpl/$s_lang/top-box-scripte.htm");

    /*
     * Beste bewertungen
     */
    	$top_sort = "RATING";
      $tpl_top = new Template("tpl/$s_lang/top-scripte.htm");
      include("tpl/top-scripte.php");

      $tpl_box->addvar("list_ratings", $tpl_top->process());

      unset($tpl_top);

    /*
     * Meisten Kommentare
     */
      $top_sort = "COMMENTS";
      $tpl_top = new Template("tpl/$s_lang/top-scripte.htm");
      include("tpl/top-scripte.php");

      $tpl_box->addvar("list_comments", $tpl_top->process());

      unset($tpl_top);

    /*
     * Meisten Klicks
     */
      $top_sort = "CLICKS";
      $tpl_top = new Template("tpl/$s_lang/top-scripte.htm");
      include("tpl/top-scripte.php");

      $tpl_box->addvar("list_clicks", $tpl_top->process());

      unset($tpl_top);

    file_put_contents($cache_file, $tpl_box->process());
    chmod($cache_file, 0777);
    unset($tpl_box);
  }
  $tpl_content->addvar("content", file_get_contents($cache_file));
?>
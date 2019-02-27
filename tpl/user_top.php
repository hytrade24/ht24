<?php
/* ###VERSIONSBLOCKINLCUDE### */


  global $s_lang;


  /*
   * Meisten Kommentare
   */
  $cache_user = $ab_path."cache/user_top.$s_lang.htm";
  $cache_lastupdate = false;
  if (file_exists($cache_user)) {
    $cache_lastupdate = filemtime($cache_user);
  }
  if (!$cache_lastupdate || ((time()-$cache_lastupdate) > (300))) {
    $top_sort = "lastpost";
    $tpl_top = new Template("tpl/$s_lang/top-user.htm");
    require_once("tpl/top-user.php");
    file_put_contents($cache_user, $tpl_top->process());
    chmod($cache_user, 0777);
    unset($tpl_top);
  }
  $tpl_content->addvar("liste_user", file_get_contents($cache_user));
?>
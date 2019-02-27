<?php
/* ###VERSIONSBLOCKINLCUDE### */


  #$SILENCE = false;

  $cache_file = $ab_path."cache/users/".$user['CACHE']."/".$uid."/my_comments.".$s_lang.".htm";
  if (file_exists($cache_file)) {
    $cache_time = filemtime($cache_file);
  } else {
    $cache_time = time() - 3601;
  }
  if ((time() - $cache_time) > 3600) {
    $tpl_usercache = new Template($ab_path."tpl/".$s_lang."/cache_user_comments.html");
    $ar_comments = $db->fetch_table("SELECT USERNAME, RATING, FK_USER, LEFT(COMMENT,40) AS COMMENT FROM rating_user WHERE FK=".$uid." AND RATE_TYP='USER' ORDER BY STAMP DESC LIMIT 10");
    if (!empty($ar_comments)) {
      $tpl_usercache->addlist("liste_user", $ar_comments, $ab_path."tpl/".$s_lang."/cache_user_comments.row.html");
    }
    unset($ar_comments);
    $ar_comments = $db->fetch_table("SELECT r.USERNAME, r.RATING, r.FK_USER, LEFT(r.COMMENT,40) AS COMMENT FROM rating_script r LEFT JOIN script s ON s.ID_SCRIPT=r.FK WHERE s.FK_USER=".$uid." ORDER BY r.STAMP DESC LIMIT 10");
    if (!empty($ar_comments)) {
      $tpl_usercache->addlist("liste_script", $ar_comments, $ab_path."tpl/".$s_lang."/cache_user_comments.row.html");
    }
    file_put_contents($cache_file, $tpl_usercache->process());
    chmod($cache_file, 0777);
  }

?>

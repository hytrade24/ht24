<?php
/* ###VERSIONSBLOCKINLCUDE### */


  if ($_REQUEST["do"] == "now") {
    $db->querynow("UPDATE `ad_master` SET CRON_DONE=0");
    die(forward("index.php?page=articles_recache&do=done"));
  }
  if ($_REQUEST["do"] == "done") {
    $tpl_content->addvar("done", 1);
  }
?>
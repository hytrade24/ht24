<?php
/* ###VERSIONSBLOCKINLCUDE### */


  global $db;

  $db->querynow("UPDATE job SET STATUS=(STATUS-STATUS&1) WHERE (STATUS&1)=1 AND STAMP_END < NOW()");
  $db->querynow("UPDATE job_live SET STATUS=(STATUS-STATUS&1) WHERE (STATUS&1)=1 AND STAMP_END < NOW()");
?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */



  //session_start();
  // Load JsHttpRequest backend.
  require_once dirname(__FILE__)."/JsHttpRequest.php";
  // Create main library object. You MUST specify page encoding!
  $JsHttpRequest = new JsHttpRequest("windows-1251");
    
  // weitere classen

  
  ### INIT CLASSES
  
  $GLOBALS['_RESULT'] = array
  (
    'msg' => array
	(
	  'err' => array(),
	  'ok' => array()
    ),
	'formfields' => array()
  );
  

?>
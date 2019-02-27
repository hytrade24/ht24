<?php
/* ###VERSIONSBLOCKINLCUDE### */



//die(ht(dump($_COOKIE["php_resource_rating"]["script"]["146"])));
include_once('sys/lib.newcomment.php');

$err = array();
$comment = new comment( $_REQUEST['FK'], $_REQUEST['table'] );

if(!$_COOKIE['php_resource'])
  $err[] = "NO_COOKIE";
//-- cookies sind deaktiviert  
 
if($_COOKIE["php_resource_rating"][$_REQUEST['table']][$_REQUEST['FK']])
  $err[] = "SHORT_TIME";
//-- darf noch nicht voten

if(($last_stamp = $comment->checkRating()) && !$_COOKIE["php_resource_rating"][$_REQUEST['table']][$_REQUEST['FK']]) {
  if(strtotime($last_stamp) > time())
    $err[] = "SHORT_TIME";
}   
//-- darf noch nicht voten

if(count($err) <= 0)
  if(!$comment->addRating($_REQUEST['RATING']))
    $err[] = "ERR_SAVE";
  else {
    $tpl_content->addvar("ok", 1);
	setcookie("php_resource_rating[".$_REQUEST['table']."][".$_REQUEST['FK']."]", 1, time() + 21600, "/");
  }
	
if(count($err) > 0)  
  $tpl_content->addvar("error", implode(" - ", get_messages("BEWERTUNG", implode(",", $err))));
?>
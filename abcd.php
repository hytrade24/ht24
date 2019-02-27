<?php 
require_once('vendor/autoload.php');
use \Statickidz\GoogleTranslate;

$text="Ihre Werbung auf HYTRADE24";
echo translate_api('de','en',$text);
function translate_api($source,$target,$text)
{
$trans = new GoogleTranslate();
$result = $trans->translate($source, $target, $text);
 return $result;
}





// E:\xampp\htdocs\tpl\ad_request.php:
?>



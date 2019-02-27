<?php
/* ###VERSIONSBLOCKINLCUDE### */



 class bbcode 
 {
   
   var $text = NULL;
   var $ar_bb = array();
   var $parsed_code = NULL;
   var $err = array();
   
   function bbcode()
   {
     
   } // bbcode()
   
   function resetClass()
   {
     ### setzt class zurück
	 $this->text = NULL;
	 $this->ar_bb = array();
	 $this->parsed_text = NULL;
	 $this->err = array();
	 $this->pages = array();
	 
	 
   } // resetClass();      
   
   function hackPageBreaks()
   {
     $this->pages = explode("[pagebreak]", $this->parsed_text);
	 return $this->pages;
   } // hackPageBreaks
   
   function parseBB($text)
   {
      $this->text = $this->parsed_text = $text;
    
    ### Font tags()
    $this->parseFontTags();
    
    ### Code tags()
    $this->parseCodeTags();
	  
	  ### php tags
	  $this->parsePhpTags();
	  
	  ### Bilder 
	  $this->parseImgTags();
	  
	  return $this->parsed_text;
	  #echo $this->parsed_text;
   } // parseBB()
   
   function parseFontTags()
   {     
    $this->parsed_text = preg_replace("%(\[u\])(.*?)(\[\/u\])%si", "<u>\\2</u>", $this->parsed_text);
    $this->parsed_text = preg_replace("%(\[b\])(.*?)(\[\/b\])%si", "<strong>\\2</strong>", $this->parsed_text);
    $this->parsed_text = preg_replace("%(\[i\])(.*?)(\[\/i\])%si", "<i>\\2</i>", $this->parsed_text);
    $this->parsed_text = preg_replace("%(\[color=#)([0-9a-f]{3,6})(\])(.*?)(\[\/color\])%si", "<span style=\"color: #$2\">$4</span>", $this->parsed_text);          
   } // parseCodeTags()
   
   function parseCodeTags()
   {     
    $this->parsed_text = preg_replace("%(\[code\])(.*?)(\[\/code\])%sei", "'<pre class=\"code\">'.stdHtmlentities('\\2').'</pre>'", $this->parsed_text);
   } // parseCodeTags()
   
   function parsePhpTags()
   {
      #echo ht(dump($this->parsed_text));
	  $this->parsed_text = preg_replace("%(\[php\])(.*?)(\[\/php\])%sei", "\$this->highlight('\\2')", $this->parsed_text);
      #die(ht(dump($this->parsed_text)));
   } // function parsePhpTags()
   
   function parseImgTags()
   {
     $this->parsed_text = preg_replace("/(\[img:)(\s?)(.*?)(\])/si", "<img src=\"/uploads/user/$3\" alt=\"$3\">", $this->parsed_text);
   } // parseImgTags
   
   function highlight($str)
   {
     #echo "VOR HIGHLIGHT: ".ht(dump($str));
	 ob_start();
	 highlight_string(stripslashes($str));
	 $return = ob_get_contents();
	 #die($return);
	 
	 ### functionslinks
	 /*preg_match_all("/([a-z0-9_]{2,})(\s{0,})(\()([\)]*)/si", $str, $ar, PREG_PATTERN_ORDER);
	 $x=$new=array();
	 
	 for($i=0; $i<count($ar[1]); $i++)
	 {
	   if(function_exists($ar[1][$i]))
	   {
	     $x[] = $ar[1][$i];
		 $new[] = '<a href="/handbuch/function.'.str_replace("_", '-', $ar[1][$i]).'.htm"  title="Zum Handbuch wechseln">'.$ar[1][$i].'</a>';
	   } // is php function
	   
	   
	 } // for functions	 
	 */
	 $return = str_replace($x, $new, $return);
	 /*echo ht(dump($x));
	 echo ht(dump($new));
	 echo $return; die();*/
	 ob_clean();	 
	 return $return;
   } // highlight()
   
 } // bbcode class 

?>
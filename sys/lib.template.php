<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path. 'sys/lib.cache.template.php';


/*
23.05-06  tpl_langref geändert  'DE' hinzugefügt!  Dadurch wird aus DE -> WWW
wir benötigt in index.langrow.htm

22.05.06   LoadText geändert    berni
Die Langselect wurde verändert siehe index.php

*/

function addnoparse($str)
{
  return str_replace('{', '{~}', $str);
}
function remnoparse($str)
{
  return str_replace('{~}', '{', $str);
}
#require_once('inc.time.php');
define ('PREG_FLT', '(-?\d+)?\.\d+|-?\d+');
/*
  0   komplette Zahl (int oder float)
  1   Vorkomma (nur bei float)
*/
#define (PREG_NUM, '(((-?\d+)?\.\d+)|-?\d+)(e((-?\d+)?\.\d+|-?\d+))?');
/*
  0,1 komplette Zahl (int, float oder exp)
  2   int oder float
  3     Vorkomma (nur bei float)
  4   exponent 'e'.(int oder float)
  5     int oder float
  6       Vorkomma (nur bei float)
*/
/*
  $ttt = 0;
  function time_start()
  {
    global $ttt;
    $t = explode(' ', microtime());
    $ttt = (int)$t[1] + (float)$t[0];
  }
  function time_stop()
  {
    global $ttt;
    $t = explode(' ', microtime());
    $t = (int)$t[1] + (float)$t[0];
    $t = $t-$ttt;
#    echo "$t seconds<br />";
    return $t;
  }
*/

//==============================================================================
  class Template
  {
    var $filename, $table;
    var $vars = array ();
    var $subtplParams = array();
    var $tpl_text;
    var $isTemplateRecursiveParsable = FALSE;
	var $isTemplateCached = FALSE;
    var $inheritParentVariables = true;  

    public static function CreateDynamic($template_fn, $table = "") {
        if (preg_match("/\.twig\.html?$/", $template_fn)) {
            return new Template_TwigWrapper($template_fn, $table);
        } else {
            return new Template($template_fn, $table);
        }
    }
    
    function LoadText($template_fn, $table='')
    {
      global $assoc_loaded_templates, $ab_path, $nar_systemsettings;

        $template_fn = str_replace($ab_path, "", $template_fn);

        $templateType = preg_match("/^([^\/]+)\//", $template_fn, $matches);
        if ($matches['1'] == 'tpl' || $matches['1'] == 'mail' || $matches['1'] == 'skin' || $matches['1'] == 'module') {
            $cacheTemplate = new CacheTemplate();
            if($nar_systemsettings['CACHE']['TEMPLATE_AUTO_REFRESH'] == 1) {
                if($cacheTemplate->isFileDirty($template_fn)) {
                    $cacheTemplate->cacheFile($template_fn);
                }
            }

            $template_fn = 'cache/design/' . $template_fn;
        }

      $this->filename = $template_fn;
      if (!$assoc_loaded_templates[$template_fn])
        $assoc_loaded_templates[$template_fn] =  (file_exists($ab_path.$template_fn)
          ? implode('', file($ab_path.$template_fn))
          : "<br><i>no such file: $template_fn</i><br />"
        );
      $this->tpl_text = $assoc_loaded_templates[$template_fn];
      $this->table = $table;
    }

    function Template($template_fn, $table='')
    {
      global $assoc_loaded_templates;
      $this->LoadText($template_fn, $table);
      $this->vars['sitetitle'] = $GLOBALS['sitetitle'];
      if (is_array ($ar = &$GLOBALS['nar_tplglobals']))
        $this->addvars($ar);
    } // constructor Template



    /**
     * Get a list of template objects filled with the given row variables (similar to $template->addlist)
     * @param string        $templateFilename
     * @param array         $arRows
     * @param string|null   $language
     * @return array
     */
    public static function createTemplateList($templateFilename, $arRows, $language = null, $flattenRowArrays = false) {
        if ($language === null) {
            $language = $GLOBALS['s_lang'];
        }
        $arResult = array();
        foreach ($arRows as $rowIndex => $arRow) {
            if ($arRow instanceof JsonSerializable) {
                $arRow = $arRow->jsonSerialize();
            }
            if ($flattenRowArrays) {
                $arRow = array_merge($arRow, array_flatten($arRow));
            }
            // Create template and add to list
            $tplRow = new Template($templateFilename);
            $tplRow->addvar("i", $rowIndex);
            $tplRow->addvars($arRow);
            $arResult[] = $tplRow;
        }
        return $arResult;
    }

    function checkMemoryUsage($memoryUsedPercentMax = 80, $writeEventlog = false) {
        if ($GLOBALS["_warning_memory_template"] !== true) {
            if (empty($GLOBALS["_warning_memory_limit"])) {
                $GLOBALS["_warning_memory_limit"] = ini_get('memory_limit');
                if (preg_match('/^(\d+)(.)$/', $GLOBALS["_warning_memory_limit"], $matches)) {
                    $GLOBALS["_warning_memory_limit"] = Tools_Utility::configSizeToBytes($matches[0]);
                }
            }
            $memoryAvail = $GLOBALS["_warning_memory_limit"];
            $memoryUsed = memory_get_usage(true);
            $memoryUsedPercent = $memoryUsed * 100 / $memoryAvail;
            if ($memoryUsedPercent > $memoryUsedPercentMax) {
                $GLOBALS["_warning_memory_template"] = true;
                if ($writeEventlog) {
                    eventlog('warning', 'Hoher Speicherverbrauch beim verarbeiten der Templates!');
                }
                return true;
            }
        }
        return false;
    }

    function addvars($array, $s_prefix=FALSE)
    {
      if (!is_array ($array))
      {
        if (!SILENCE)
          if (function_exists('dump') && function_exists('ht'))
            echo ht(dump($this)), '<hr />', ht(dump($array)), '<hr />';
          else
            { var_dump($this); echo '<hr />'; var_dump($array); echo '<hr />'; }
        $ar = array();
        for ($i=0; $i<func_num_args(); $i++)
          $ar[] = php_dump(func_get_arg($i));
        myerr ('Template::addvars('
          . implode(", ", $ar)
          . '): first parameter is not an array');
      }
      if ($s_prefix)
      {
        $ar_tmp = array ();
        foreach($array as $k=>$v)
          $ar_tmp[$s_prefix.$k] = $v;
        $array = $ar_tmp;
      }
      // 2004-07-03: Tabelle autom. ermitteln
      elseif (!$this->table)
        foreach($array as $k=>$v) if (preg_match('/^ID\_/', $k))
        {
          $this->table = strtolower(substr($k, 3));
          break;
        }
      $this->vars = array_merge($array, $this->vars);
    } // function Template->addvars

    function addvar($name, $value)
    {
      $this->vars[$name] = $value;
    } // function Template->addvar

    function addlist_fast($name, $array, $str_subtpl, $arInheritedVariables = false, $s_callback=NULL) {
        return $this->addlist($name, $array, $str_subtpl, $s_callback, array(
            "processNow"        => true,
            "inheritVariables"  => $arInheritedVariables
        ));
    }

    function addlist($name, $array, $str_subtpl, $s_callback=NULL, $moreOptions = array())
    {
        if (!is_array($array)) return;
        $eventList = false;
        if (array_key_exists('ebizRecordLoadtimeTemplate', $_COOKIE)) {
            $eventList = Tools_LoadtimeStatistic::getInstance()->createEvent("Template-List", $name, array("count" => count($array)));
        }
        $ar_liste = array();
        foreach($array as $i=>$row) {
            if ($s_callback) {
                if (strpos($s_callback, ';') !== FALSE) {
                    foreach (explode(';', $s_callback) as $key => $functionname) {
                        $functionname = trim($functionname);
                        $functionname($row, $i);
                    }
                } else {
                    $s_callback($row, $i);
                }
            }
            $tpl_tmp = new Template($str_subtpl, $this->table);
            $tpl_tmp->isTemplateRecursiveParsable = $this->isTemplateRecursiveParsable;
            $tpl_tmp->isTemplateCached = $this->isTemplateCached;
            if (array_key_exists("cached", $moreOptions)) {
                // Enable/disable recursive parsing
                $tpl_tmp->isTemplateCached = ($moreOptions["cached"] ? true : false);
            }
            if (array_key_exists("recursive", $moreOptions)) {
                // Enable/disable recursive parsing
                $tpl_tmp->isTemplateRecursiveParsable = ($moreOptions["recursive"] ? true : false);
            }
            $tpl_tmp->inheritParentVariables = ($moreOptions["inheritVariables"] === null ? $this->inheritParentVariables : $moreOptions["inheritVariables"]);
            $tpl_tmp->addvars($row);
            $tpl_tmp->addvar('i', $i);
            $tpl_tmp->addvar('even', 1-((int)$i&1));
            if ((int)$i+1 == count($array)) {
                $tpl_tmp->addvar('last', 1);
            }
            if (array_key_exists("processNow", $moreOptions) && ($moreOptions["processNow"] === true)) {
                // Instantly render template instead of keeping it in memory until delivery
                $ar_liste[] = self::process_value($tpl_tmp, true);
            } else {
                // Store template for later rendering (including variables that are being set/changed in the future)
                $ar_liste[] = $tpl_tmp;
            }
        }
        $this->addvar($name, $ar_liste);
        if ($eventList !== false) {
            $eventList->finish();
        }
    } // function Template->addlist

    function process_value($value, $isList = FALSE)
    {
      global $nar_systemsettings;
      // Wert ermitteln, rekursiv wenn noetig
      if (is_array ($value))
      {
        $tmp = array ();
        foreach($value as $v)
          $tmp[] = Template::process_value($v, TRUE);
		if ($nar_systemsettings["SITE"]["TEMPLATE_COMMENTS"] == 1) {
			$comment_file = (is_object($value[0]) ? $value[0]->filename : "Unknown File");
			$comment_start = "\n<!-- LIST-START - File: ".$comment_file." -->\n";
			$comment_end = "\n<!-- LIST-ENDE - File: ".$comment_file." -->\n";
        	return $comment_start.implode('', $tmp).$comment_end;
		} else {
        	return implode('', $tmp);
		}
      }
      elseif (is_object($value))
        if (!strcasecmp('Template', ($class=get_class($value)))
          # php4: get_class = lower case; php5: tatsaechliche Schreibung!
          || is_subclass_of($value, 'Template'))
        {
          // inherit variables
          if ($this->inheritParentVariables === true) {
              // Alle variablen aus Eltern-Template übernehmen
              foreach ($this->vars as $k=>$v) {
                  if (!is_object($v) && !is_array($v) && !array_key_exists($k, $value->vars)) {
                      $value->addvar($k, $v);
                  }
              }
              if ($tmp = $this->vars['curframe'])
                  $value->addvar('curframe', $tmp);
          } else if (is_array($this->inheritParentVariables)) {
              // Nur bestimmte variable aus Eltern-Template übernehmen
              foreach ($this->inheritParentVariables as $k=>$v) {
                  if (is_int($k)) {
                      // Simple mapping (no renaming e.g. array("FOO", "BAR"))
                      $value->addvar($v, $this->vars[$v]);
                  } else {
                      // Extended mapping (variables can be renamed e.g. array("FOO" => "BAR"))
                      $value->addvar($v, $this->vars[$k]);
                  }
              }
          }
          // process
          return $value->process(FALSE, $isList);
        }
        else
#          myerr("Template: $name is a non-template object (class='$class')");
          return "Object{$class}";
      else
        return $value;
    } // Template::process_value

    function getval($varname, $to_num = FALSE)
    {


      // 2005-07-13: Funktionen schachteln
      if (preg_match('/\(/', $varname))
        return $this->process_text('{'. $varname. '}');

      // Wert einer Variable ermitteln; bei Array und Objekt: Ergebnis speichern
      $value = $this->vars[$varname];
      if (is_array ($value) || is_object($value) || '='==substr($value,0,1))
        $value = $this->vars[$varname] = Template::process_value($value);
      $is_num = preg_match('/^('. PREG_FLT. ')$/', trim($value));
      if ($to_num && !$is_num)
        $value = remnoparse($value);
      return (!$to_num || $is_num ? $value : strlen($value));
    } // Template::getval

    function process_value_array($replaceVariable, $replaceValue = "", $text, $replacementText = NULL) {
    	if($replacementText === NULL) {
    		$replacementText = $replaceVariable;
    	} else {
    		$replacementText = $replacementText. '.' . $replaceVariable;
    	}

    	if(is_array($replaceValue)) {
    		foreach ($replaceValue as $key => $value) {
    			$text = $this->process_value_array($key, $value, $text, $replacementText);
    		}

    	} else {
    		$text = str_replace('{'.$replacementText.'}', $replaceValue, $text);
    	}
    	return $text;
    }

      function process_text($text, $dropempty = TRUE) {

#static $indent = '';echo $indent, $this->filename, "<br />\n";$indent .= '&nbsp;&nbsp;';mytime();
          // benoetigte Permissions abfragen --> als Vars definieren
          //$text = preg_replace('/{\s*loadperm\s*\((.*)\)\s*\}/Ue', '$this->tpl_loadperm("$1")', $text);
          $text = preg_replace_callback('/{\s*loadperm\s*\((.*)\)\s*\}/U', function($match) {
              return $this->tpl_loadperm($match[1]);
          }, $text);


          #var_dump($text);

          // {restrict (ident)}...{endrestrict}; may be nested
          global $nar_pageallow;
          while (FALSE !== ($p_ende = strpos($text, '{endrestrict}'))) {
              // Parameter ermitteln
              $match = substr($text, 0, $p_ende + 13);
              $p_start = -10;
              while (FALSE !== ($tmp = strpos($match, '{restrict ', $p_start + 10))) $p_start = $tmp;
              if (0 > $p_start) break;
              $match = substr($match, $p_start);
              if (FALSE === ($p_if_close = strpos($match, '}'))) myerr("Template: syntax error at '$part'");
              $length = strlen($match);
              $true = substr($match, $p_if_close + 1, $length - 13 - $p_if_close - 1);
              $false = '';
              $ident = trim(substr($match, 10, $p_if_close - 10), '() ');
              $restrict = true;
              if (substr($ident, 0, 1) == '!') {
                  $restrict = false;
                  $ident = substr($ident, 1);
              }
              $hasAccess = ($nar_pageallow[$ident] ? true : false);
              // match ersetzen
              $text = substr_replace($text, ($hasAccess == $restrict ? $true : $false), $p_start, $length);
          } // end {restrict (ident)}...{endrestrict}

          // {if (condition)} ... [{else} ... ]{/if}; may be nested
          while (FALSE !== ($p_ende = strpos($text, '{endif}'))) {
              // Parameter ermitteln
              $match = substr($text, 0, $p_ende + 7);
              $p_start = -4;
              while (FALSE !== ($tmp = strpos($match, '{if ', $p_start + 4))) $p_start = $tmp;
              if (0 > $p_start) break;
              $match = substr($match, $p_start);
              if (FALSE === ($p_if_close = strpos($match, '}'))) myerr("Template: syntax error at '$part'");
              $length = strlen($match);
              if (FALSE !== ($p_else = strpos($match, '{else}'))) {
                  $true = substr($match, $p_if_close + 1, $p_else - $p_if_close - 1);
                  $false = substr($match, $p_else + 6, $length - 7 - $p_else - 6);
              } else {
                  $true = substr($match, $p_if_close + 1, $length - 7 - $p_if_close - 1);
                  $false = '';
              }
              $cond = trim(substr($match, 4, $p_if_close - 4));
              $strcond = $cond;
              /**/
              // vars ersetzen
#echo "$cond -> ";
              //$cond = preg_replace('/tpl_([a-z_]+)\(([^)]+)\)/ie', '$this->tpl_$1("$2")', $cond, -1, $functionCount);
              $cond = preg_replace_callback('/tpl_([a-z_]+)\(([^)]+)\)/i', function ($match) {
                  return eval('return $this->tpl_'.$match[1].'("'.$match[2].'");');
              }, $cond);
              //$cond = preg_replace('/[a-z_]\w*/ie', '$this->getval("$0", true)', $cond);
              $cond = preg_replace_callback('/[a-z_]\w*/i', function ($match) {
                  $value = $this->getval($match[0], true);
                  if (preg_match("/^[0-9]+$/", $value) && !preg_match("/^[1-9][0-9]*$/", $value)) {
                      // Prevent invalid numeric values
                      return '"'.$value.'"';
                  }
                  return $value;
              }, $cond);
              
              // wahr oder falsch?
#echo "$cond\n";
              if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $cond)) {
                  $if = strspn($cond, '-:0') != strlen($cond); // date/datetime
              } else {
                  try {
                      #var_dump($strcond, $cond);
                      $success = eval("\$if=$cond; return true;"); // sonstige
                      if ($success !== true) {
                          $error = error_get_last();
                          eventlog("error", "Fehler beim Auflösen des if-blocks '".$cond."' in Template '".$this->filename."'", $error["message"]);
                      }
                  } catch (Exception $e) {
                      eventlog("error", "Fehler beim Auflösen des if-blocks '".$cond."' in Template '".$this->filename."'");
                  }
              }
#echo "$strcond: $cond : ", ($if ? 'true':'false'), '<br />', "\n";
              // match ersetzen
              $text = substr_replace($text, ($if ? $true : $false), $p_start, $length);
              /*/
                      $cond = preg_replace('/[a-z_]\w*'.'/ie', '('.(int)$dropempty.' || isset($this->vars["$0"]) ? $this->getval("$0", true) : "DONT!")', $cond);
                      // wahr oder falsch?
              #echo "<br />$cond";
                      if (false===strpos($cond,'DONT!'))
                      {
              #echo ':)';
              #echo "$cond\n";
                        eval ("\$if=$cond;");
                      // match ersetzen
                        $text = substr_replace($text, ($if ? $true:$false), $p_start, $length);
                      }
                      else
                        $pos0 = $p_start+$length;
              #else echo ":( - $true - $false";
              /**/
          } // end {if ...}..{endif}
#echo $indent;mytime('if');

          // in-template functions

          $text = $this->parseTemplateString($text);


         # echo "<pre>";var_dump($stack);echo"</pre>";die();


#echo $indent;mytime('defined');
          // default values
#xxx hier koennte eventuell nochmal ein noparse dazwischen hauen
          //$text = preg_replace('/\{(\w+):(.*)\}/Ue', '(strlen($value=$this->getval("$1")) ? $value : "$2")', $text);
          $text = preg_replace_callback('/\{(\w+):(.*)\}/U', function($match) {
              return (strlen($value=$this->getval($match[1])) ? $value : $match[2]);
          }, $text);
#echo $indent;mytime('default');
          // calculations
          //$text = preg_replace('/\{\=((' . PREG_FLT . '|\b\w+\b)(\s*(-|\+|&|\|)\s*(' . PREG_FLT . '|\b\w+\b))*)\}/e', '$this->tpl_calc("$1")', $text);
#echo $indent;mytime('calc');

          // undefined vars
          if ($dropempty) $text = remnoparse(preg_replace('/\{\w+\}/', '', $text));
#echo $indent;mytime('undefined');
#$indent = substr($indent,12);
          return $text;
      } // function Template::process_text

      function parseTemplateString_getStack(&$tpl, &$tplPointer = 0)
      {
          $arStack = array();
          $stackPointer = 0;
          $stackIsOpen = false;
          while ($tplPointer < strlen($tpl)) {

              $posOpen = strpos($tpl, '{', $tplPointer);
              $posClose = strpos($tpl, '}', $tplPointer);

              if (($posOpen === FALSE) && ($posClose === FALSE)) {
                  // End of string reached
                  break;
              } else {
                  // Brackets left in string
                  if (($posClose === FALSE) || (($posOpen !== FALSE) && ($posOpen < $posClose))) {
                      // Opening bracket next
                      $tplPointer = $posOpen + 1;
                      $arStack[$stackPointer] = array(
                          "START" => $posOpen,
                          "END" => false,
                          "CONTENT" => "",
                          "CHILDREN" => $this->parseTemplateString_getStack($tpl, $tplPointer)
                      );
                      $stackIsOpen = true;
                  } else if (($posOpen === FALSE) || (($posClose !== FALSE) && ($posClose < $posOpen))) {
                      // Closing bracket next
                      if ($stackIsOpen) {
                          // Close previously opened element
                          $tplPointer = $posClose + 1;
                          $arStack[$stackPointer]["CONTENT"] = substr($tpl, $arStack[$stackPointer]["START"], $posClose - $arStack[$stackPointer]["START"] + 1);
                          $arStack[$stackPointer]["END"] = $posClose;
                          $stackPointer++;
                          $stackIsOpen = false;
                      } else {
                          // Nothing to be closed. Return now!
                          break;
                      }
                  }
              }
          }
          return $arStack;
      }
      
      function parseTemplateString_processStack(&$tpl, &$tplStack, &$parentEntryLength = 0) {
          for ($index = count($tplStack)-1; $index >= 0; $index--) {
              $arEntry = $tplStack[$index];
              // Replace content
              $entryLength = $arEntry["END"] - $arEntry["START"] + 1;
              if (preg_match("/^(\{\s*(\w+)\((.*)\)\s*\})$/", $arEntry["CONTENT"], $arFuncMatch) && method_exists("Template", "tpl_".$arFuncMatch[2])) {
                  // Is function!
                  $functionName = "tpl_".$arFuncMatch[2];
                  $functionValue = $this->parseTemplateString_getValue( $this->$functionName($arFuncMatch[3]) );
                  $tpl = substr_replace($tpl, $functionValue, $arEntry["START"], $entryLength);
                  $parentEntryLength += strlen($functionValue) - $entryLength;
              } else if (preg_match("/^\{([^\{\}\(\)]+)\}$/", $arEntry["CONTENT"], $arVarMatch) && array_key_exists($arVarMatch[1], $this->vars)) {
                  // Is variable!
                  $variableValue = $this->parseTemplateString_getValue( $this->vars[ $arVarMatch[1] ] );
                  $tpl = substr_replace($tpl, $variableValue, $arEntry["START"], $entryLength);
                  $parentEntryLength += strlen($variableValue) - $entryLength;
              } else {
                  // No function or variable, parse nested entries.
                  if (!empty($arEntry["CHILDREN"])) {
                      $this->parseTemplateString_processStack($tpl, $arEntry["CHILDREN"], $arEntry["END"]);
                  }
              }
          }
      }
      
      function parseTemplateString_getValue(&$value, $isList = false) {
          if (is_array($value)) {
              $listResult = "";
              foreach ($value as $listIndex => $listValue) {
                  $listResult .= $this->parseTemplateString_getValue($listValue, true);
              }
              return $listResult;
          } else  if (($value instanceof Template) || ($value instanceof FrameTemplate)) {
              return $this->process_template($value, $isList);
          } else {
              return $value;
          }
      }
      
      function parseTemplateString($tpl) {
          $this->parseTemplateString_processStack($tpl, $this->parseTemplateString_getStack($tpl));
          return $tpl;
      }

      function process_template(Template $tpl, $isList = false) {
          // inherit variables
          if ($tpl->inheritParentVariables === true) {
              // Alle variablen aus Eltern-Template übernehmen
              foreach ($this->vars as $k=>$v) {
                  if (!is_object($v) && !is_array($v) && !array_key_exists($k, $tpl->vars)) {
                      $tpl->addvar($k, $v);
                  }
              }
              if ($tmp = $this->vars['curframe'])
                  $tpl->addvar('curframe', $tmp);
          } else if (is_array($tpl->inheritParentVariables)) {
              // Nur bestimmte variable aus Eltern-Template übernehmen
              foreach ($tpl->inheritParentVariables as $k=>$v) {
                  if (is_int($k)) {
                      // Simple mapping (no renaming e.g. array("FOO", "BAR"))
                      $tpl->addvar($v, $this->vars[$v]);
                  } else {
                      // Extended mapping (variables can be renamed e.g. array("FOO" => "BAR"))
                      $tpl->addvar($v, $this->vars[$k]);
                  }
              }
          }
          // process
          return $tpl->process(FALSE, $isList);
      }

    function process($dropempty=TRUE,$isList=FALSE)
    {
      global $nar_tplglobals, $nar_systemsettings;

        $eventTemplate = false;
        if (array_key_exists('ebizRecordLoadtimeTemplate', $_COOKIE)) {
            $eventTemplate = Tools_LoadtimeStatistic::getInstance()->createEvent("Template", $this->filename, array("is_list" => $isList));
        }
        
        $comment_start = "";
        $comment_end = "";
        $container_start = "";
        $container_end = "";

#echo ht(dump($nar_tplglobals));
      if (is_array ($nar_tplglobals))
        $this->addvars($nar_tplglobals);
#echo ht(dump($this->filename)), ht(dump($this->vars)), '<hr />';
	  if ($nar_systemsettings["SITE"]["TEMPLATE_COMMENTS"] == 1) {
	  	global $templates_in_use;
	  	if (!is_array($templates_in_use))
	  		$templates_in_use = array();
	  	if (empty($templates_in_use[$this->filename])) {
	  		$templates_in_use[$this->filename] = 1;
	  	} else {
	  		$templates_in_use[$this->filename] += 1;
	  	}
	  	if ($isList) {
		  	$comment_start = "";
		  	$comment_end = "\n<!-- ####### // LIST ITEM END ############### -->\n";
	  	} else {
		  	$comment_start = "\n<!-- START - File: ".$this->filename." -->\n";
		  	$comment_end = "\n<!-- ENDE - File: ".$this->filename." -->\n";
	  	}
	  }

      if($nar_systemsettings["SITE"]["TEMPLATE_DEBUG"] == 1) {
          $debugId = (string)(microtime(TRUE)*10000);
          if((strpos($this->filename, "tpl/") !== FALSE)) {

          $container_start = "\n<div class='debug-template-file-container' id='debug-template-file-container-id-".$debugId."'>\n";
          $container_start .= "\n<div class='debug-template-file-name'>".$this->filename."</div>\n";
          #$container_start .= "\n <div class='debug-template-file-c' rel='debug-template-file-container-id-".$debugId."'>\n";
          $container_end = "\n</div>\n";
         # $container_end .= "\n<script type='text/javascript'>debugTemplateFile('".$debugId."')</script>\n";
          }
      }

        Template_Modules_SubTemplates::pushParent($this->filename);

		$text = $container_start . $comment_start . $this->process_text($this->tpl_text, $dropempty) . $comment_end . $container_end;
		if ($this->isTemplateRecursiveParsable) {
			$text = str_replace(array('^', '°'), array('{', '}'), $text);
		}
		if($this->isTemplateCached) {
			$text = $this->process_text($text);
		}
        Template_Modules_SubTemplates::popParent($this->filename);
        if($eventTemplate !== false) {
            $eventTemplate->finish();
        }
		return $text;
    } // function Template::process

/*
    function geterrorsnippet()
    {
      return new Template(dirname($this->filename), 'error_snip');
    }
    // IN-TEMPLATE FUNCTIONS
    function tpl_class($param)
    {
      global $class, $msg;
      $params = explode(',', $param);
      $field = trim($params[0]);
      $classname = trim($params[1]);
      if (!($ret = $class[$field]))
        $ret = ($classname ? ' class="'. $classname. '"' : '');
      return $ret;
    } // Template::tpl_class
    function tpl_errmsg($field)
    {
      global $errmsg;
      if ($msg = trim($errmsg[trim($field)]))
      {
        $tpl = $this->geterrorsnippet();
        $tpl->addvar('msg', $msg);
        return $tpl->process();
      }
    }
    function tpl_input($param)
    {
      $params = explode(',', $param);
      $field = trim($params[0]);
#      if (ereg('^pass', $field)) $field = 'PASS';
      $class = trim($params[1]);
      if (!($table = trim($params[2]))) $table=$this->table;
      global $data;
      if (!$value = $this->vars[$field])
      if (!$value = $_POST[$field])
        $value = $data[$field];
      else
        $data[$field] = $value;
      if (ereg('^pass', $field))
        $GLOBALS['fielddefs'][$table][$field] = $GLOBALS['fielddefs'][$table]['PASS'];
#echo "$field:$value:". $this->vars[$field]. "<br />";
      return field($table, $field, addnoparse($value), $field, $class);
    } // Template::tpl_input
*/
    function subdrop($prefix, $type, $s_morehtm, $value, $z, $a=1)
    {
      if ($a==$z) // 2004-07-01
      {
        return '<input type="hidden" name="'. $prefix. '_'. $type. '" value="'. $a. '">'
          . ('m'==$type ? $GLOBALS['nar_monthstr'][$a] : $a);
      }
      else
      {
#echo "$prefix, $type, $s_morehtm, $value, $z, $a<br>";
        $ret = array ('<select name="', $prefix, '_', $type, '" '. $s_morehtm. '>');
        if ((int)$value && $value<$a) $ret[] = '
        <option selected value="'. $value. '">'. $value. '</option>';
        for($i=$a; $i<=$z; $i++)
        {
          $val = ('y'==$type ? $i : sprintf('%02d', $i));
          $ret[] = '
        <option '
          . ( $value==$i ? 'selected ': ''). 'value="'. $val. '">'
          . ('m'==$type && isset($GLOBALS['nar_monthstr'])? $GLOBALS['nar_monthstr'][$i] : $val). '</option>';
        }
        if ((int)$value && $value>$z) $ret[] = '
        <option selected value="'. $value. '">'. $value. '</option>';
        $ret[] = '
      </select>';
        if ('m'==$type && !isset($GLOBALS['nar_monthstr']))
          $ret[] = '.';
        return implode('', $ret);
      }
    } // Template::(tpl_)subdrop
    function tpl_datedrop($param)
    {
      # date:iso
      # range:<,=,> & kombi plus aktueller Wert; default:<=>; je 5 Jahre
      # neu 2004-06-30: a-b; ab 07-01 auch mit Variablen
      # neu 2005-07-13: morehtm
      # neu 2005-08-11: a..(-N)  -- z.B. N=18 --> 2005-18 = 1987
      $now = date('Y-m-d');
      $y_now = substr($now, 0, 4);
      list($param, $range, $s_morehtm) = explode(',', stripslashes($param));
      if(!($range = trim($range))) $range='<=>';
#echo "<b>datedrop $param, $range, $s_morehtm</b><br>";

      if (preg_match('/^(-?\d+)?..(-?\d+)?$/', $range, $ar_match))
      {
        if (!strlen($y_a = $ar_match[1])) $y_a = $y_now;
        elseif ($y_a<=0) $y_a += $y_now;
        if (!strlen($y_z = $ar_match[2])) $y_z = $y_now;
        elseif ($y_z<=0) $y_z += $y_now;
      }
      elseif (preg_match('/^(\d+)?-(\d+)?$/', $range, $ar_match))
      {
        if (!($y_a = $ar_match[1])) $y_a = $y_now;
        if (!($y_z = $ar_match[2])) $y_z = $y_now;
      }
      elseif (preg_match('/^([a-zA-Z_]\w*|\d+)?-([a-zA-Z_]\w*|\d+)?$/', $range, $ar_match))
      {
        if (!($y_a = (int)$this->getval($ar_match[1]))) $y_a = $y_now;
        if (!($y_z = (int)$this->getval($ar_match[2]))) $y_z = $y_now;
      }
      else
      {
        $y_z = $y_now + (FALSE!==strpos($range, '>') ? 5 : 0);
        $y_a = $y_now - (FALSE!==strpos($range, '<') ? 5 : 0);
      }
#echo "$range -> $y_z,$y_a<br>";#die();
      $fl_js = ('true'==$fl_js ? 1 : $this->getval($fl_js));
      $date = $this->getval($param);
#echo "$date:$y-$m-$d";
      if (!$date || preg_match('/^0000\-00\-00/', $date))
      {
        if ($y_z<$y_now)
          $date = $y_z. date('-m-d');
        else
          $date = $now;
        list($y,$m,$d) = explode('-', substr($date,0,10));
      }
      elseif(preg_match('/^[0-9]+$/', $param)) // timestamp
      {
        $len=strlen($param);
        $n = (8==$len || 14==$len ? 4 : 2);
        $y = substr($param, 0, $n);
        if ($n==2)
          $y += ($y<70 ? 1900 : 2000);
        if (!($m = substr($param, $n, 2)))
          $m = substr($now, 5,2);
        if (!($d = substr($param, $n+2, 2)))
          $d = substr($now, 8,2);
      }
      else
        list($y,$m,$d) = explode('-', substr($date,0,10));
      $now = substr($now, 0, 4);
      return
          $this->subdrop($param, 'd', $s_morehtm, $d, 31). '. '
        . $this->subdrop($param, 'm', $s_morehtm, $m, 12). ' '
        . $this->subdrop($param, 'y', $s_morehtm, $y, $y_z, $y_a)
      ;
    } // Template::tpl_datedrop
    function tpl_timedrop($param)
    {
      static $suffix = array ('h', 'i', 's');
      # time:iso
      # fields: 3=His (default), 2=Hi, 1=H
      # neu 2005-07-13: morehtm
      list($param, $fields, $s_morehtm) = explode(',', $param);
      $time = (preg_match('/(^| )(\d{0,2}:\d{0,2}(:\d{0,2}))$/', $this->getval($param), $ar_tmp)
        ? $ar_tmp[2]
        : date('H:i:s')
      );
#echo "$time<br>";
      $fields = ($fields ? max(1, min(3, (int)$fields)) : 3);
      $v = explode(':', substr($time, -8));
      $res = array ();
      for($i=0; $i<$fields; $i++)
#{echo "subdrop($param, $suffix[$i], s_morehtm, $v[$i], ".($i ? 59:23).", 0);<br>";
        $res[] = $this->subdrop($param, $suffix[$i], $s_morehtm, $v[$i], ($i ? 59:23), 0);
#}
      return implode(':', $res);
    } // Template::tpl_timedrop

    function tpl_strip_tags($param) { return addnoparse(strip_tags($this->getval($param))); }
    function tpl_nl2br($param) { return addnoparse(nl2br($this->getval($param))); }
    function tpl_htm($param) { return addnoparse(stdHtmlentities($this->getval($param))); }
    function tpl_htm_raw($param) { return str_replace(array('{', '}'), array('&#123;', '&#125;'), htmlentities($this->getval($param))); }
    function tpl_text($param) { return addnoparse(nl2br(stdHtmlentities($this->getval($param)))); }
    function tpl_url($param) { return addnoparse(rawurlencode($this->getval($param))); }
    function tpl_urllabel($param) { return addnoparse(chtrans($this->getval($param))); }
    function tpl_js($param) { return addnoparse(js_quote($this->getval($param))); }
	function tpl_int($param) { return addnoparse((int)$this->getval($param)); }
    function tpl_base64encode($param) { return base64_encode($this->parseTemplateString($param)); }
    function tpl_base64encode_array($param) { return base64_encode(serialize($this->vars[$param])); }
    function tpl_lower($param) { return strtolower($this->vars[$param]); }
    function tpl_upper($param) { return strtoupper($this->vars[$param]); }

    function tpl_abbrev($param)
    {
      list($param,$uint_maxlen) = explode(',', $param);
      $str = $this->getval($param);
      $uint_maxlen = $this->getval($uint_maxlen);
      if (!$uint_maxlen) $uint_maxlen = 100;
      if (strlen($str)>$uint_maxlen)
        $str = substr($str, 0, $uint_maxlen-4). ' ...';
      return addnoparse(nl2br(stdHtmlentities($str)));
    }
    function tpl_shorten($param) {
        list($text,$length,$removeHtml) = explode(',', $param);
        $text = $this->parseTemplateString($text);
		if ($removeHtml) {
			$text = strip_tags($text);
		}
        $length = (int)$this->parseTemplateString($length
        );
        if($length <= 0) { $length = 100; }
        if (strlen($text) > $length) {
            $text = substr($text, 0, $length - 4) . ' ...';
        }
        return addnoparse($text);
    }

    function tpl_shorten_filename($param) {
        list($text,$length) = explode(',', $param);
        $text = $this->parseTemplateString($text);
        $length = (int)$this->parseTemplateString($length);
        if($length <= 0) { $length = 100; }
        if (strlen($text) > $length) {
          $arFile = pathinfo($text);
          $lengthName = $length - strlen($arFile['extension']) - 4;
          $text = substr($arFile['filename'], 0, $lengthName).'....'.$arFile['extension'];
        }
        return addnoparse($text);
    }
      
    function tpl_has_permission($params) {
        global $db;
        // Get parameters
        list($permIdent, $permType, $userId) = explode(',', $params);
        $permIdent = $this->parseTemplateString($permIdent);
        if (is_numeric($permType)) {
            $permType = (int)$permType;
        } else {
            switch (strtoupper($permType)) {
                case "D":
                case "DEL":
                case "DELETE":
                    $permType = 8;
                    break;
                case "E":
                case "EDIT":
                    $permType = 4;
                    break;
                case "C":
                case "CREATE":
                    $permType = 2;
                    break;
                case "R":
                case "READ":
                default:
                    $permType = 1;
                    break;
                    
            }
        }
        $userId = $this->parseTemplateString($userId);
        if ($userId == "") {
            $userId = $GLOBALS["uid"];
        }
        // Initialize cache
        if (!is_array($GLOBALS["_permCache"])) {
            $GLOBALS["_permCache"] = array(
                "permByIdent"   => array(),
                "roleByIdent"   => array(),
                "rolesByUser"   => array(),
                "accessByUser"  => array(),
                "accessByRole"  => array()
            );
        }
        // Get permission id
        $permId = false;
        if (array_key_exists($permIdent, $GLOBALS["_permCache"]["permByIdent"])) {
            $permId = $GLOBALS["_permCache"]["permByIdent"][$permIdent];
        } else {
            $permId = $db->fetch_atom("SELECT ID_PERM FROM `perm` WHERE IDENT='".mysql_real_escape_string($permIdent)."'");
            if ($permId > 0) {
                $GLOBALS["_permCache"]["permByIdent"][$permIdent] = $permId;
            }
        }
        if ($permId === false) {
            // Permission not found!
            return 0;
        }
        // Get role ids
        $roleIds = array();
        if (array_key_exists($userId, $GLOBALS["_permCache"]["rolesByUser"])) {
            $roleIds = $GLOBALS["_permCache"]["rolesByUser"][$userId];
        } else if ($userId !== false) {
            $roleIds = $db->fetch_nar("SELECT FK_ROLE FROM `role2user` WHERE FK_USER=".(int)$userId, 0, 1);
        }
        if (empty($roleIds)) {
            // No roles assigned, get guest role.
            if (array_key_exists("Gast", $GLOBALS["_permCache"]["roleByIdent"])) {
                $roleGuest = $GLOBALS["_permCache"]["roleByIdent"]["Gast"];
            } else {
                $roleGuest = (int)$db->fetch_atom("SELECT ID_ROLE FROM `role` WHERE LABEL='Gast'");
            }
            if ($roleGuest == 0) {
                $roleGuest = 1;
            }
            $roleIds[] = $roleGuest;
        } else {
            // Roles found! Cache roles.
            $GLOBALS["_permCache"]["rolesByUser"][$userId] = $roleIds;
        }
        // Get role permissions
        $permRoles = 0;
        foreach ($roleIds as $roleIndex => $roleId) {
            if (array_key_exists($roleId, $GLOBALS["_permCache"]["accessByRole"])) {
                $permRoles |= $GLOBALS["_permCache"]["accessByRole"][$roleId];
            } else {
                $permRole = (int)$db->fetch_atom("SELECT BF_ALLOW FROM `perm2role` WHERE FK_ROLE=".(int)$roleId." AND FK_PERM=".(int)$permId);
                $GLOBALS["_permCache"]["accessByRole"][$roleId] = $permRole;
                $permRoles |= $permRole;
            }
        }
        // TODO: Get user permission
        $permUser = $permRoles;
        /*
        if ($userId !== false) { 
            if (array_key_exists($userId, $GLOBALS["_permCache"]["accessByUser"])) {
                $permUser = $GLOBALS["_permCache"]["accessByUser"][$userId];
            } else {
                $permUserEntry = $db->fetch1("SELECT * FROM `perm` WHERE IDENT='".mysql_real_escape_string($permId)."'");
                if (is_array($permUserEntry)) {
                    // Inherit from role
                    $permUser = $permRoles & $permUserEntry["BF_INHERIT"];
                    // Revoke by user
                    $permUser = $permRoles - ($permRoles & $permUserEntry["BF_REVOKE"]);
                    // Grant by user
                    $permUser = $permRoles | $permUserEntry["BF_GRANT"];
                }
                $GLOBALS["_permCache"]["accessByUser"][$permIdent] = $permUser;
            }
        }
        */
        return ($permUser & $permType ? 1 : 0);
    }  

      function tpl_market_article_count($params)
	{
		list($id_kat,$hideZero,$template) = explode(',', $params);
		if (!is_numeric($id_kat)) {
			$id_kat = $this->getval(trim($id_kat));
		} else {
			$id_kat = (int)$id_kat;
		}
		include_once "sys/lib.pub_kategorien.php";
		$kat_cache = new CategoriesCache();
		$result = $kat_cache->getCacheArticleCount($id_kat);
        if ($hideZero && ($result <= 0)) {
            return "";
        } else {
            if (!empty($template)) {
                $result = str_replace("{COUNT}", $result, $template);
            }
            return $result;
        }
	}
      
      function tpl_market_article_description($params) {
          list($articleId, $maxLength) = explode(',', $params);
          if (!is_numeric($articleId)) {
              $articleId = (int)$this->parseTemplateString(trim($articleId));
          } else {
              $articleId = (int)$articleId;
          }
          if (!is_numeric($maxLength)) {
              $maxLength = (int)$this->parseTemplateString(trim($maxLength));
          } else {
              $maxLength = (int)$maxLength;
          }
          if ($maxLength <= 0) {
              $maxLength = 100;
          }
          $article = Api_Entities_MarketplaceArticle::getById($articleId);
          if ($article instanceof Api_Entities_MarketplaceArticle) {
              // Get description text and return shortened text
              $articleDescription = $article->getDescriptionText();
              if (strlen($articleDescription) > $maxLength) {
                  $articleDescription = substr($articleDescription, 0, $maxLength - 4) . ' ...';
              }
              return $articleDescription;
          } else {
              // Article not found!
              return "";
          }
	    }
      
      function tpl_market_article_shipping($params) {
          list($articleId, $defaultVar, $variantVar, $viewType, $countryVar, $providerVar) = explode(',', $params);
          $articleId = (int)$this->getval($articleId, FALSE);
          $defaultValue = (double)$this->getval($defaultVar, FALSE);
          $variantId = (!empty($variantVar) ? (int)$this->getval($variantVar, FALSE) : null);
          $countryId = (!empty($countryVar) ? (int)$this->getval($countryVar, FALSE) : null);
          $providerId = (!empty($providerVar) ? $this->getval($providerVar, FALSE) : null);
          $defaultCurrency = $this->getval("CURRENCY_DEFAULT");
          $eventArticleShipping = new Api_Entities_EventParamContainer(array(
              "template"          => $this,
              "articleId"         => $articleId,
              "variantId"         => $variantId,
              "countryId"         => ($countryId !== null ? (int)$countryId : null),
              "providerId"        => ($providerId !== null ? $providerId : null),
              "defaultVar"        => $defaultVar,
              "defaultValue"      => $defaultValue,
              "defaultCurrency"   => $defaultCurrency,
              "viewType"          => (!empty($viewType) ? $viewType : "default"),
              "output"            => $this->tpl_topreis($defaultVar)." ".$defaultCurrency
          ));
          Api_TraderApiHandler::getInstance()->triggerEvent( Api_TraderApiEvents::MARKETPLACE_AD_SHIPPING_DISPLAY, $eventArticleShipping );
          return $eventArticleShipping->getParam("output");
      }

      function tpl_market_field($params) {
          $adId = false;
          $adTable = false;
          list($fieldName, $fieldNamePrefix, $fieldFallback) = explode(',', $params);
          if (array_key_exists($fieldNamePrefix."ID_AD_MASTER", $this->vars)) {
              $adId = $this->vars[$fieldNamePrefix."ID_AD_MASTER"];
          } else if (array_key_exists($fieldNamePrefix . "ID_AD", $this->vars)) {
              $adId = $this->vars[$fieldNamePrefix . "ID_AD"];
          } else if (array_key_exists("ID_AD_MASTER", $this->vars)) {
              $adId = $this->vars["ID_AD_MASTER"];
          } else if (array_key_exists("ID_AD", $this->vars)) {
              $adId = $this->vars["ID_AD"];
          }
          if (array_key_exists($fieldNamePrefix."AD_TABLE", $this->vars)) {
              $adTable = $this->vars[$fieldNamePrefix."AD_TABLE"];
          } else if (array_key_exists("AD_TABLE", $this->vars)) {
              $adTable = $this->vars["AD_TABLE"];
          }
          if ($adTable !== false) {
              if ($adId === false) {
                  if (array_key_exists($fieldNamePrefix."ID_".strtoupper($adTable), $this->vars)) {
                      $adId = $this->vars[$fieldNamePrefix."ID_".strtoupper($adTable)];
                  } else if (array_key_exists("ID_".strtoupper($adTable), $this->vars)) {
                      $adId = $this->vars["ID_".strtoupper($adTable)];
                  }
              }
              $fieldNameFull = (!empty($fieldNamePrefix) ? $fieldNamePrefix : "").$fieldName;
              if (!array_key_exists($fieldNameFull, $this->vars) && ($adId !== false)) {
                  require_once $GLOBALS["ab_path"]."sys/lib.ads.php";
                  $arAd = AdManagment::getAdById($adId);
                  $this->vars[$fieldNameFull] = $arAd[$fieldName];
              }
              if (array_key_exists($fieldNameFull, $this->vars)) {
                  return $this->tpl_market_field_render($adTable, $fieldName, $this->vars[$fieldNameFull]);
              }
          }
          return $fieldFallback;
      }
      
      private function tpl_market_field_render($adTable, $fieldName, $fieldValue) {
          require_once $GLOBALS["ab_path"]."sys/lib.ad_table.php";
          $table = AdTable::getTableByName($adTable);
          $arField = $table->getFieldByName($fieldName);
          $fieldUnit = (!empty($arField["V2"]) ? " ".$arField["V2"] : "");
          switch ($arField["F_TYP"]) {
              default:
              case "INT":
                  // Just return value
                  return $fieldValue.$fieldUnit;
              case "LIST":
                  if ($fieldValue > 0) {
                      return $this->tpl_market_list_value($fieldValue).$fieldUnit;
                  } else {
                      return "";
                  }
          }
      }

      function tpl_market_field_element_checked($params) {
          list($fieldName, $fieldNamePrefix, $elementId, $fieldFallback) = explode(',', $params);
          $adId = false;
          $adTable = false;
          if (array_key_exists($fieldNamePrefix."ID_AD_MASTER", $this->vars)) {
              $adId = $this->vars[$fieldNamePrefix."ID_AD_MASTER"];
          } else if (array_key_exists($fieldNamePrefix . "ID_AD", $this->vars)) {
              $adId = $this->vars[$fieldNamePrefix . "ID_AD"];
          } else if (array_key_exists("ID_AD_MASTER", $this->vars)) {
              $adId = $this->vars["ID_AD_MASTER"];
          } else if (array_key_exists("ID_AD", $this->vars)) {
              $adId = $this->vars["ID_AD"];
          }
          if (array_key_exists($fieldNamePrefix."AD_TABLE", $this->vars)) {
              $adTable = $this->vars[$fieldNamePrefix."AD_TABLE"];
          } else if (array_key_exists("AD_TABLE", $this->vars)) {
              $adTable = $this->vars["AD_TABLE"];
          }
          if ($adTable !== false) {
              if ($adId === false) {
                  if (array_key_exists($fieldNamePrefix."ID_".strtoupper($adTable), $this->vars)) {
                      $adId = $this->vars[$fieldNamePrefix."ID_".strtoupper($adTable)];
                  } else if (array_key_exists("ID_".strtoupper($adTable), $this->vars)) {
                      $adId = $this->vars["ID_".strtoupper($adTable)];
                  }
              }
              $fieldNameFull = (!empty($fieldNamePrefix) ? $fieldNamePrefix : "").$fieldName;
              if (!array_key_exists($fieldNameFull, $this->vars) && ($adId !== false)) {
                  require_once $GLOBALS["ab_path"]."sys/lib.ads.php";
                  $arAd = AdManagment::getAdById($adId);
                  $this->vars[$fieldNameFull] = $arAd[$fieldName];
              }
              if (array_key_exists($fieldNameFull, $this->vars)) {
                  $arChecked = explode("x", trim($this->vars[$fieldNameFull], "x"));
                  $arSearchFor = explode("x", $elementId);
                  if (count($arSearchFor) == 1) {
                      return (in_array($elementId, $arChecked) ? 1 : 0);
                  } else {
                      foreach ($arSearchFor as $searchIndex => $searchId) {
                          if (in_array($searchId, $arChecked)) {
                              return 1;
                          }
                      }
                      return 0;
                  }
              }
          }
          return (!empty($fieldFallback) ? $fieldFallback : 0);
      }

      function tpl_market_list_value($params) {
          global $db, $langval;
          list($fieldValue,$fieldName) = explode(',', $params);
          $fieldValue = $this->parseTemplateString($fieldValue);
          $fieldName = (!empty($fieldName) ? $fieldName: "s.V1");
          $fieldHash = sha1($fieldValue."|".$fieldName);
          // Check cache
          if (!is_array($GLOBALS["localRequestCache"])) {
              $GLOBALS["localRequestCache"] = array();
          }
          if (!array_key_exists("list_value", $GLOBALS["localRequestCache"])) {
              $GLOBALS["localRequestCache"]["list_value"] = array();
          }
          if (!array_key_exists($fieldHash, $GLOBALS["localRequestCache"]["list_value"])) {
              // Get list option
              $GLOBALS["localRequestCache"]["list_value"][$fieldHash] = $db->fetch_atom("
                  SELECT ".$fieldName."
                  FROM `liste_values` t
                  LEFT JOIN string_liste_values s
                    ON s.S_TABLE='liste_values' AND s.FK=t.ID_LISTE_VALUES
                       AND s.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
                  WHERE t.ID_LISTE_VALUES=".$fieldValue);
          }
          return $GLOBALS["localRequestCache"]["list_value"][$fieldHash];
      }

      function tpl_market_list_values($params) {
          global $db, $langval;
          list($fieldValues,$fieldName) = explode(',', $params);
          $fieldValues = $this->parseTemplateString($fieldValues);
          $fieldValuesList = explode("x", trim($fieldValues, "x"));
          foreach ($fieldValuesList as $index => $value) {
              $fieldValuesList[$index] = (int)$value;
          }
          $fieldName = (!empty($fieldName) ? $fieldName: "s.V1");
          $fieldHash = sha1($fieldValues."|".$fieldName);
          // Check cache
          if (!is_array($GLOBALS["localRequestCache"])) {
              $GLOBALS["localRequestCache"] = array();
          }
          if (!array_key_exists("list_values", $GLOBALS["localRequestCache"])) {
              $GLOBALS["localRequestCache"]["list_values"] = array();
          }
          if (!array_key_exists($fieldHash, $GLOBALS["localRequestCache"]["list_values"])) {
              // Get list option
              $GLOBALS["localRequestCache"]["list_values"][$fieldHash] = $db->fetch_nar("
                SELECT ".$fieldName."
                FROM `liste_values` t
                LEFT JOIN string_liste_values s
                  ON s.S_TABLE='liste_values' AND s.FK=t.ID_LISTE_VALUES
                     AND s.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
                WHERE t.ID_LISTE_VALUES IN (".implode(", ", $fieldValuesList).") ORDER BY t.ORDER ASC", false, 1);
          }
          return $GLOBALS["localRequestCache"]["list_values"][$fieldHash];
      }

      function tpl_market_list_input($params) {
          global $ab_path, $db, $langval, $s_lang;
          list($articleTable,$fieldName,$fieldValue,$inputHtml) = explode(',', $params);
          $cacheInterval = 3600;
          $articleTable = $this->parseTemplateString($articleTable);
          $fieldName = $this->parseTemplateString($fieldName);
          $fieldValue = (empty($fieldValue) ? $fieldName : $fieldValue);
          // Get list id
          $listId = $db->fetch_atom("
            SELECT f.FK_LISTE 
            FROM `field_def` f
            JOIN `table_def` t ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
            WHERE f.F_NAME='".mysql_real_escape_string($fieldName)."'
                AND t.T_NAME='".mysql_real_escape_string($articleTable)."'");
          $cacheFile = $ab_path."cache/marktplatz/input_list.".$listId.".".$s_lang.".htm";
          $cacheContent = "";
          if (!file_exists($cacheFile) || ((time() - filemtime($cacheFile)) > $cacheInterval)) {
              // Update cache
              $tplList = new Template("tpl/".$s_lang."/cache_marktplatz_input_list.htm");
              $tplList->isTemplateRecursiveParsable = true;
              $tplList->addvar("NAME", $fieldName);
              $tplList->addvar("HTML", $inputHtml);
              $tplList->addvar("VALUE", $fieldValue);
              // Get list options
              $options = $db->fetch_table("
                SELECT t.*, s.V1, s.V2, s.T1
                FROM `liste_values` t
                LEFT JOIN string_liste_values s
                  ON s.S_TABLE='liste_values' AND s.FK=t.ID_LISTE_VALUES
                     AND s.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
                WHERE t.FK_LISTE=".$listId."
                ORDER BY t.ORDER ASC");
              $tplList->addlist("liste", $options, "tpl/".$s_lang."/cache_marktplatz_input_list.row.htm");
              $cacheContent = $tplList->process(false);
              file_put_contents($cacheFile, $cacheContent);
          } else {
              $cacheContent = file_get_contents($cacheFile);
          }
          $tpl_tmp = new Template("tpl/de/empty.htm");
          $tpl_tmp->tpl_text = $cacheContent;
          $tpl_tmp->isTemplateCached = true;
          $tpl_tmp->addvars($this->vars);
          #die(var_dump($this->vars));
          return $tpl_tmp->process(false);
      }

    function tpl_market_kat_path($params) {
        list($id_kat, $seperator, $urlEncode) = explode(",", $params);
        $id_kat = $this->parseTemplateString($id_kat);
        if ((int)$id_kat <= 0) {
            return "";
        }
        $seperator = ($seperator === null ? " > " : $seperator);
        // Normale Kategorie-Darstellung
        $cachefile = $GLOBALS['ab_path']."cache/marktplatz/ariane_".$GLOBALS['s_lang'].".".$id_kat.".txt";

        $cacheFileLifeTime = $GLOBALS['nar_systemsettings']['CACHE']['LIFETIME_CATEGORY'];
        $modifyTime = @filemtime($cachefile);
        $diff = ((time()-$modifyTime)/60);

        if(($diff > $cacheFileLifeTime) || !file_exists($cachefile)) {
            require_once("sys/lib.pub_kategorien.php");
            $kat_cache = new CategoriesCache();
            $kat_cache->cacheKatArianeText($id_kat);
        }
        $path = file_get_contents($cachefile);
        $arPath = explode("|", $path);
        if (($urlEncode !== null) && $urlEncode) {
            foreach ($arPath as $pathIndex => $pathName) {
                $arPath[$pathIndex] = addnoparse(chtrans($pathName));
            }
        }
        return implode($seperator, $arPath);
    }

    function tpl_market_kat_path_url($params) {
        return $this->tpl_market_kat_path($params.",/,1");
    }

	function tpl_market_job_count($params)
	{
		global $db;
		return $db->fetch_atom("SELECT count(*) FROM `job` WHERE OK=3");
	}

	function tpl_modul($params)
	{
	  $hack = explode(",", $params);
	  include_once "module/".$hack[0]."/functions.php";
	  //echo ht(dump($params));
	  $func = array_shift($hack);
	  return $func($hack);
	}

	function subtpl_scanparams(&$subtpl_params, &$ar, $raw = false)
	// aux function for tpl_subtpl and tpl_content
	{
	    $stackName = "";
	    $stackValue = "";
	    $stackDepth = 0;
		foreach($subtpl_params as $vardef) {
			$vardef = trim($vardef);
			if ($stackDepth > 0) {
                $stackOpen = substr_count($vardef, "{");
                $stackClose = substr_count($vardef, "}");
                $stackDepth += ($stackOpen - $stackClose); 
                if ($stackDepth == 0) {
                    $valueRaw = $stackValue.",".$vardef;
                    $ar[$stackName] = trim($raw ? $valueRaw : $this->parseTemplateString($valueRaw));
                } else {
                    $stackValue .= ",".$vardef;
                }
			} else if ($vardef == '*') {
				foreach ($this->vars as $k=>$v) {
					if (!is_object($v) && !array_key_exists($k, $ar)) {
						$ar[$k] = $v;
	        		}
				}
			} elseif (($p = strpos($vardef, '*')) == strlen($vardef)-1) {
				$pattern = substr($vardef, 0, strlen($vardef)-1);
				foreach ($this->vars as $k=>$v) {
					if (strpos($k, $pattern) === 0) {
						$ar[$k] = $v;
	        		}
				}
			} elseif (FALSE!==($p = strpos($vardef, '='))) {
                $valueRaw = substr($vardef, $p+1);
                $stackOpen = substr_count($valueRaw, "{");
                $stackClose = substr_count($valueRaw, "}");
                $stackDepth += ($stackOpen - $stackClose); 
                if ($stackDepth == 0) {
                    if (!$raw && preg_match("/^\{([a-z0-9-_]+)\}$/i", $valueRaw, $arMatchValue) && array_key_exists($arMatchValue[1], $this->vars)) {
                        $ar[trim(substr($vardef, 0, $p))] = $this->vars[$arMatchValue[1]];
                    } else {
                        $ar[trim(substr($vardef, 0, $p))] = substr($vardef, $p + 1);
                    }
                } else {
                    $stackName = trim(substr($vardef, 0, $p));
                    $stackValue = $valueRaw;
                }
        	} else {
				$ar[trim($vardef)] = $this->vars[$vardef];
			}
		}
	}

    function tpl_subtpl($param) // filename, params
      /* params:
        *         alle Variablen aus aktuellem Template vererben, die weder Object noch Array sind
        varname   benannte Variable aus aktuellem Template vererben
        name=val  Variable mit konstantem Wert definieren
      */
    {
      // Get raw parameters
      $ar_subtpl_raw = array();
      $subtpl_params_raw = explode(',', $param);
      $subtpl_template = array_shift($subtpl_params_raw);
      $this->subtpl_scanparams($subtpl_params_raw, $ar_subtpl_raw, true);
      // Get parsed parameters  
	  #$param = $this->parseTemplateString($param);
      $subtpl_params = explode(',', $param);
      $subtpl_filename = $this->parseTemplateString(trim(array_shift($subtpl_params)));
      if (preg_match("/\.twig\.html?$/", $subtpl_filename)) {
          // Twig template
          $templateVars = array();
          $this->subtpl_scanparams($subtpl_params, $templateVars);
          $template = new Template_Twig($subtpl_filename, $templateVars);
          if (preg_match("/^tpl\/[a-z]+\/(.+)\.twig\.html?$/i", $subtpl_filename, $arMatchFile)) {
              // Regular relative template given
              $templatePhpFile = $GLOBALS["ab_path"]."tpl/".$arMatchFile[1].".php";
              if (file_exists($templatePhpFile)) {
                  // Matching php file found! Include it.
                  include $templatePhpFile;
              }
          }
          return $template->render();
      } else {
          // Default template
          $sub_tpl = new Template($subtpl_filename);
          $sub_tpl->isTemplateRecursiveParsable = $this->isTemplateRecursiveParsable;
          $sub_tpl->isTemplateCached = $this->isTemplateCached;
          $sub_tpl->subtplParams = $ar_subtpl_raw;
    #echo ht(dump($subtpl_params));
          if ($b_noscript = count($subtpl_params) && 'noscript'==trim($subtpl_params[0]))
            array_shift($subtpl_params);
    #echo "sub_tpl ( $param ) : ", dump($b_noscript), '<br />';
          $this->subtpl_scanparams($subtpl_params, $sub_tpl->vars);
    
          // Skript da? ausfuehren!
          preg_match('%^tpl/\w+/(.*)\.htm$%', $subtpl_filename, $ar_match);
          if (!$b_noscript && $ar_match[1] && file_exists($s_script = 'tpl/'. $ar_match[1]. '.php'))
          {
              extract ($GLOBALS);
              $tpl_content = &$sub_tpl;
              // Log timing?
              $eventScript = false;
              if (array_key_exists('ebizRecordLoadtimeTemplate', $_COOKIE)) {
                  $eventScript = Tools_LoadtimeStatistic::getInstance()->createEvent("PHP", $s_script, array("is_subtpl" => true, "parameters" => $param));
              }
              // Include script
              include $s_script;
              if($eventScript !== false) {
                  $eventScript->finish();
              }
          }
    
          return $sub_tpl->process();
      }
    } // Template::tpl_subtpl

    function tpl_content($param) // tplfile, layout, params - Details zu params siehe tpl_subtpl
    {
      $ar_params = explode(',', $param);
      $s_type = trim(array_shift($ar_params));
      $s_layout = trim(array_shift($ar_params));
      $sub_tpl = new Template('tpl/'. $GLOBALS['s_lang']. '/content_'. $s_type
        . ($s_layout ? $s_layout. '_' : ''). '.htm');
      $this->subtpl_scanparams($ar_params, $ar_vars);
      $sub_tpl->addvars($ar_vars);
      ${'content_'. $s_type} ($sub_tpl, $ar_vars);
      return $sub_tpl->process();
    }

    function tpl_calc($param)
    {
      $param = preg_replace_callback('/{(\w+)}/', function($match) {
                return $this->getval($match[1], false);
            },
            preg_replace('/\{('. PREG_FLT. ')\}/', '$1',
                preg_replace('/('. PREG_FLT. ')|\b\w+\b/', '{$0}', $param)
            )
        );
      do
        $param = preg_replace_callback('/^\s*('. PREG_FLT. ')\s*([-\/\^|&!*+%]|&~|\|\||&&|!=)\s*('. PREG_FLT. ')/', function($match) {
            return eval('return '.$match[1].$match[3].$match[4].';');
        }, $tmp = $param);
      while ($tmp!=$param);
      return $param;
    } // Template::tpl_calc

    function tpl_date_format($param)
    {
      list($param, $format) = explode(',', $param);
      $param = ($param != "" ? $this->getval(trim($param)) : time());
      $format = trim($format);
      if (preg_match('/^[0-9]+$/', $param)) {
      	// Pure number
      	return date($format, (int)$param);
      } else {
      	$time = strtotime($param);
      	return date($format, $time);
      }
    } // Template::tpl_date_format

    function tpl_todate($param) # datum, withtime
    {
      list($param, $withtime) = explode(',', $param);
      $param = $this->getval(trim($param));
      if (preg_match('/^[0-9]+$/', $param)) // timestamp
      {
        $len=strlen($param);
        $n =  (8==$len || 14==$len ? 4 : 2);
        $y = substr($param, 0, $n);
        if ($n==2)
          $y += ($y<70 ? 1900 : 2000);
        $m = substr($param, $n, 2);
        $d = substr($param, $n+2, 2);
        if ((int)$withtime && $len>8)
          $t = ' '.substr(chunk_split(substr($param, -(10==$len ? 4 : 6)), 2, ':'),0,-1);
        else
          $t = '';
      }
      elseif (strpos($date = substr($param, 0, 10), '-'))
        return iso2date($param, $withtime);
      else
        return $param;
    }
    function tpl_tokw($param) # datum -> kw/year
    {
      $param = $this->getval(trim($param));
      if (preg_match('/^[0-9]+$/', $param)) // timestamp
      {
        $len=strlen($param);
        $n =  (8==$len || 14==$len ? 4 : 2);
        $y = substr($param, 0, $n);
        if ($n==2)
          $y += ($y<70 ? 1900 : 2000);
        $m = substr($param, $n, 2);
        $d = substr($param, $n+2, 2);
        if ((int)$withtime && $len>8)
          $t = chunk_split(substr($param, -(10==$len ? 4 : 6)), 2, ':');
        else
          $t = '';
      }
      elseif (strpos($date = substr($param, 0, 10), '-'))
      {
        list($y,$m,$d) = explode('-', $date);
        $t = ((int)$withtime ? substr($param, 10) : '');
      }
      else
        return FALSE;
      return date ('W/Y', mktime(2,2,2,$m,$d,$y));
    }
    function tpl_tokb($param)
    {
      $param = (int)$this->getval($param);
      $b = $param%1024;
      $param = $param>>10;
      $kb = $param%1024;
      $mb = $param>>10;
      $ret = array ();
      if ($mb) $ret[] = "$mb MB";
      if ($kb) $ret[] = round($kb+$b/1024,2). ' KB';
      else     $ret[] = "$b Bytes";
      return implode(' ', $ret);
    }
    function tpl_topreis($param)
    {
      list($param,$digits) = explode(',', $param);
      if (!$digits) $digits=2;
      return sprintf("%0.".$digits."f", (double)$this->getval($param, FALSE));
    }
      function tpl_topreis_ex($param)
      {
          list($param, $digits, $seperator, $seperatorThousand, $zeroReplace) = explode(',', $param);
          if (empty($digits)) {
              $digits = 2;
          }
          if (empty($seperator)) {
              $seperator = ",";
          }
          if (empty($seperatorThousand)) {
              $seperatorThousand = ".";
          }
          if (empty($zeroReplace)) {
              $zeroReplace = "-";
          }
          return preg_replace("/".preg_quote($seperator.str_repeat("0", $digits))."$/", $seperator.$zeroReplace,
              number_format((double)$this->getval($param, FALSE), $digits, $seperator, $seperatorThousand)
          );
      }
	  function tpl_pseudopreis_discount($param) {
		  list($preis, $pseudopreis) = explode(',', $param);
		  $preis = $this->parseTemplateString($preis);
		  $pseudopreis = $this->parseTemplateString($pseudopreis);

		  return floor((1 - ($preis / $pseudopreis)) * 100);
	  }

    function tpl_tokonto($param)
    {
      $v = $this->tpl_topreis($param);
      if ($v>0) return $v. ' Haben';
      if ($v<0) return substr($v,1). ' Soll';
      return $v;
    }

    function tpl_checked($param)
    {
/**/
      list($p1, $p2, $s) = explode(',', $param);
      if (!$s) $s = 'checked ';
      $curval = trim($p1);
        if (preg_match('/[^0-9]/', $curval))
        $curval = $this->getval($curval);
      $chkval = trim($p2);
        if (preg_match('/[^0-9]/', $chkval))
        $chkval = $this->getval($chkval);
      return ($curval==$chkval ? $s : '');
/*/
      if (false===($uint_pos = strpos($param, ','))) return '';
      $curval = trim(substr($param,0,$uint_pos));
      if (ereg('[^0-9]', $curval))
        $curval = $this->getval($curval);
      $chkval = trim(substr($param,$uint_pos+1));
      if (ereg('[^0-9]', $chkval))
        $chkval = $this->getval($chkval);
      return ($curval==$chkval ? 'checked ' : '');
/**/
    }# {checked(wert1,wert2)}

    function tpl_select($param) // tabelle[, feldname[, label-spalte[, where[,ordercol,[morehtm[, nulltext]]]]]]
    {
      global $db;
      static $def = NULL;
#die(ht(dump($this)));
      list($str_table, $str_fieldname, $str_labelcol, $str_where, $str_ordercol, $str_morehtm, $str_nulltext)
        = explode(',', stripslashes($param));
#if ($str_table=='ticket') echo ht(dump($param));
      if ($this->table)
      {
        if (is_null($def))
          $def=$db->getdef($this->table);
      }
      else
        $def = array ('FK_'. strtoupper($str_table)=>array (
          'Null'=>'YES'
        ));
#echo "table:$str_table<br />fieldname:$str_fieldname<br />labelcol:$str_labelcol<br />where:$str_where<br />ordercol:$str_ordercol<br />morehtm:".stdHtmlentities($str_morehtm)."<br />nulltext:$str_nulltext<br />";
      $str_table = trim($str_table);
#if ('ebiz_server'==$str_table) echo ht(dump($def)),'<hr />';
      if (!($str_fieldname = trim($str_fieldname)))
        $str_fieldname = 'FK_'. strtoupper($str_table);
      if (!($str_labelcol = trim($str_labelcol)))
        $str_labelcol = 'LABEL';
      $str_pkcol = 'ID_'. strtoupper($str_table);
      $selected = $this->getval($str_fieldname);
#echo ht(dump($this->vars));echo "selected($str_fieldname):$selected<br />";
      if ($str_where)
        $str_where = " where ". $this->process_text($str_where);
      if (!$str_ordercol)
        $str_ordercol = 2;
      if ('YES'==$def[$str_fieldname]['Null'] || $str_nulltext)
      {
        if (!$str_nulltext)
          $str_nulltext = '---';
        $nar = array (NULL=>$str_nulltext);
      }
      else
        $nar = array ();

      if ('LFT'==$str_ordercol)
        $s_sql = $db->lang_select($str_table, "t.$str_pkcol, concat(repeat('- ', count(u.LFT)), t.$str_labelcol)"). '
          left join `'. $str_table. '` u on u.ROOT=t.ROOT and t.LFT BETWEEN u.LFT AND u.RGT and t.LFT<>u.LFT'
          . preg_replace('/ROOT=(\d+)/', 't.ROOT=$1', $str_where). '
          group by t.LFT order by t.LFT';
      else
        $s_sql = $db->lang_select($str_table, "$str_pkcol, $str_labelcol")
          . "$str_where order by $str_ordercol";

      $nar += $db->fetch_nar($s_sql);
      $ar_tmp = array ();
      foreach($nar as $k=>$v) $ar_tmp[] = '
    <option '. ($selected==$k ? 'selected="selected" ':''). 'value="'
      . stdHtmlentities($k). '">'. stdHtmlentities($v). '</option>';
      return '<select name="'. $str_fieldname. '" id="'
        . strtolower($str_fieldname). '" '. $str_morehtm. '>'. implode('', $ar_tmp). '
  </select>';
    } // end method tpl_select

    
    
    function tpl_select_country($param) // [feldname[, label-spalte[, where[,ordercol,[morehtm[, nulltext]]]]]]
    {
      global $db, $langval;
      // Get parameters
      list($fieldName, $labelColumn, $whereCondition, $orderColumn, $moreHtml, $nullText) = explode(",", stripslashes($param));
      $arWhere = array();
      $countryGroups = Api_CountryGroupManagement::getInstance($db, $langval);
      $uniqueId = (int)$GLOBALS["_bsCountryUnique"];
      $GLOBALS["_bsCountryUnique"] = $uniqueId+1;
      // Apply defaults
      if (empty($fieldName)) $fieldName = "FK_COUNTRY";
      if (empty($labelColumn)) $labelColumn = "sc.V1";
      if (!empty($whereCondition)) $arWhere[] = $whereCondition;
      if (empty($orderColumn)) $orderColumn = "sc.V1";
      $selectedId = (int)$this->vars[$fieldName];
      // Get grouped country list
      $arCountryList = $countryGroups->fetchCountryList(null, $labelColumn, $orderColumn, $arWhere);
      // Generate html output
      $htmlResult = '<select id="'.$fieldName.'" name="'.$fieldName.'" autocomplete="false" data-bootstrap-select="country'.$uniqueId.'" data-container="body"'.(!empty($moreHtml) ? ' '.$moreHtml : '').' data-container="body">'."\n";
      if (!empty($nullText)) {
        $htmlResult .= '<option value="">'.htmlspecialchars($nullText).'</option>'."\n";
      }
      $groupId = null;
      $groupOthers = "";
      foreach ($arCountryList as $countryIndex => $countryDetails) {
        $htmlOption =   '<option value="'.$countryDetails["ID_COUNTRY"].'"'.($countryDetails["ID_COUNTRY"] == $selectedId ? ' selected' : '').'>'.
                          htmlspecialchars($countryDetails["COUNTRY_NAME"]).
                        '</option>'."\n";
        if ($countryDetails["ID_COUNTRY_GROUP"] !== null) {
          if ($countryDetails["ID_COUNTRY_GROUP"] != $groupId) {
            // Group changed
            if ($groupId !== null) {
              // Close previous group
              $htmlResult .= '</optgroup>'."\n";
            }
            // Open new group
            $groupLabel = $countryGroups->fetchCountryGroupPathText($countryDetails["ID_COUNTRY_GROUP"]);
            $htmlResult .= '<optgroup label="'.htmlspecialchars($groupLabel).'">'."\n";
            $groupId = $countryDetails["ID_COUNTRY_GROUP"];
          }
          $htmlResult .= $htmlOption;
        } else {
          $groupOthers .= $htmlOption;
        }
      }
      if ($groupId !== null) {
        // Close last regular group
        $htmlResult .= '</optgroup>'."\n";
      }
      // Add others group
      if (!empty($groupOthers)) {
        $htmlResult .= '<optgroup label="'.htmlspecialchars(Translation::readTranslation("general", "country.group.others", null, array(), "Sonstige")).'">'."\n";
        $htmlResult .= $groupOthers;
        $htmlResult .= '</optgroup>'."\n";
      }
      // Close select tag
      $htmlResult .= '</select>'."\n";
      // Bootstrap select initialisation
      $htmlResult .= 
        "<script type='text/javascript'>\n".
        "  jQuery('select[data-bootstrap-select=country".$uniqueId."]').selectpicker({ liveSearch: true });\n".
        "</script>\n";
      return $htmlResult;
    } // end method tpl_select_country
    
    

	  function tpl_print_fk($param) // tabelle[, feldname[, label-spalte[,nulltext]]]
	  {
		  global $db, $langval;
		  list($str_table, $str_fieldname, $str_labelcol, $str_nulltext) = explode(',', stripslashes($param));
		  $str_table = trim($str_table);
		  if (!($str_fieldname = trim($str_fieldname))) $str_fieldname = 'FK_' . strtoupper($str_table);
		  $selected = $this->getval($str_fieldname);
		  if (!($str_labelcol = trim($str_labelcol))) $str_labelcol = 'LABEL';
		  if ($selected) $ret = $db->fetch_atom($db->lang_select($str_table, $str_labelcol) . "
			where " . (preg_match('/^nav/', $str_table) ? 'ID_NAV' : 'ID_' . strtoupper($str_table)) . "=" . $selected); else
			  $ret = FALSE;

		  return ($ret ? $ret : ($str_nulltext ? $str_nulltext : '---'));
	  } // end method tpl_print_fk

    function tpl_lookup($param) // art[, spalte[, fl_admin=false[, morehtm[,ordercol[,nulltext]]]]]
    // wenn FL_ADMIN, wird VALUE statt LABEL angezeigt
    {
      list($str_art, $str_fieldname, $fl_admin, $str_morehtm, $str_ordercol, $s_nulltext) = explode(',', $param);
      $str_ordercol = (empty($str_ordercol) ? "F_ORDER ASC" : $str_ordercol);
      return $this->tpl_select('lookup, '. ($str_fieldname ? $this->parseTemplateString($str_fieldname) : 'LU_'. strtoupper($str_art))
        . ','. ($fl_admin ? 'VALUE' : ''). ",art='". $str_art. "',$str_ordercol,$str_morehtm,$s_nulltext");
    } // function tpl_lookup

      function tpl_select_liste($param) {
          global $db;
          list($id_liste,$str_fieldname,$str_html,$cache,$nulltext) = explode(',', $param);
          if (empty($nulltext)) {
              $nulltext = Translation::readTranslation("marketplace", "select.please.choose", null, array(), "Bitte wählen");
          }
          $id_liste = $this->parseTemplateString($id_liste);
          if ($id_liste > 0) {
              $str_fieldname = $this->parseTemplateString($str_fieldname);
              $str_html = $this->parseTemplateString($str_html);
              $ar_html = array();
              $ar_html[] = "<select name='".$str_fieldname."'".(!empty($str_html) ? " ".str_replace("\\'", "'", $str_html) : "").">";
              $ar_html[] = "	<option value=''>".$nulltext."</option>";
              $query = "
                SELECT l.*, s.V1, s.V2, s.T1
    		    FROM `liste_values` l
    		    LEFT JOIN `string_liste_values` s ON
        			s.S_TABLE='liste_values' AND s.FK=l.ID_LISTE_VALUES AND
        			s.BF_LANG=if(l.BF_LANG_LISTE_VALUES & ".$GLOBALS['langval'].", ".$GLOBALS['langval'].", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
        		WHERE l.FK_LISTE=".(int)$id_liste."
        		ORDER BY l.ORDER ASC, s.V1 ASC";
              $ar_liste = $db->fetch_table($query);
              foreach ($ar_liste as $ar_value) {
                  if ($cache) {
                      $ar_html[] = "	<option value='".$ar_value["ID_LISTE_VALUES"]."'^if ".$str_fieldname."==".$ar_value["ID_LISTE_VALUES"]."° selected='selected'^endif°>".
                                            stdHtmlentities($ar_value["V1"])."</option>";
                  } else {
                      $ar_html[] = "	<option value='".$ar_value["ID_LISTE_VALUES"]."'{if ".$str_fieldname."==".$ar_value["ID_LISTE_VALUES"]."} selected='selected'{endif}>".
                                            stdHtmlentities($ar_value["V1"])."</option>";
                  }
              }
              $ar_html[] = "</select>";
              if ($cache) {
                  return implode($ar_html, "\n");
              } else {
                  $result = $this->process_text( implode($ar_html, "\n"), true );
                  return $result;
              }
          }
          return "";
      }

      function tpl_select_multicheck($param) {
          global $db;
          list($id_liste,$str_fieldname,$str_html,$cache) = explode(',', $param);
          $id_liste = $this->parseTemplateString($id_liste);
          if ($id_liste > 0) {
              $str_fieldname = $this->parseTemplateString($str_fieldname);
              $str_html = $this->parseTemplateString($str_html);
              $ar_html = array();
              $query = "
                SELECT l.*, s.V1, s.V2, s.T1
    		    FROM `liste_values` l
    		    LEFT JOIN `string_liste_values` s ON
        			s.S_TABLE='liste_values' AND s.FK=l.ID_LISTE_VALUES AND
        			s.BF_LANG=if(l.BF_LANG_LISTE_VALUES & ".$GLOBALS['langval'].", ".$GLOBALS['langval'].", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
        		WHERE l.FK_LISTE=".(int)$id_liste."  ORDER BY l.ORDER ASC";
              $ar_liste = $db->fetch_table($query);
              foreach ($ar_liste as $ar_value) {
                  $is_selected = '';
                  if ($cache) {
                      $is_selected = '^if '.$str_fieldname.'_'.$ar_value['ID_LISTE_VALUES'].'° checked="checked"^endif°';
                  } else {
                      $is_selected = '{if '.$str_fieldname.'_'.$ar_value['ID_LISTE_VALUES'].'} checked="checked"{endif}';
                  }
                  $ar_html[] = '
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="'.$str_fieldname.'[]" value="'.$ar_value['ID_LISTE_VALUES'].'"'.$is_selected.$str_html.'>
                            '.stdHtmlentities($ar_value['V1']).'
                        </label>
                    </div>';
              }
              return implode($ar_html, "\n");
          }
          return "";
      }

    function tpl_print_lu($param) // art[,spalte[,nulltext[,labelspalte]]]
    {
      global $db;
      list($str_art, $str_fieldname, $str_nulltext, $s_labelcol)
        = explode(',', $param);
      $str_art = strtoupper(trim($str_art));
      if (!$str_fieldname = trim($str_fieldname))
        $str_fieldname = 'LU_'.$str_art;
      if (!$s_labelcol = trim($s_labelcol))
        $s_labelcol = 'LABEL';
      $selected = (int)$this->getval($str_fieldname);
      $ret = ($selected
        ? $db->fetch_atom($db->lang_select('lookup', $s_labelcol). "where art='$str_art' and ID_LOOKUP=". $selected)
        : FALSE
      );
      return ($ret ? $ret : ($str_nulltext ? $str_nulltext : '---'));
    } // end method tpl_print_lu
      
      function tpl_print_lookup($param) {
          global $db, $langval;
          list($idLookup, $strNullText) = explode(",", $param);
          $idLookup = $this->parseTemplateString($idLookup);
          if (empty($strNullText)) {
              $strNullText = "---";
          }
          $result = $db->fetch_atom("
            SELECT s.V1 AS LABEL FROM `lookup` t
            LEFT JOIN string s on s.S_TABLE='lookup' and s.FK=t.ID_LOOKUP
              AND s.BF_LANG=if(t.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG+0.5)/log(2)))
            WHERE ID_LOOKUP=". $idLookup);
          return ($result ? $result : $strNullText);
      }

    function is_intext($str_varname)
    {
      return FALSE!==(strpos($tpl_main->tpl_text, '{'. $str_varname. '}'));
    }

    function tpl_pageref($s_ident)
    {
      static $nar_ret = array ();
      if ($this)
        $s_ident = $this->process_text($s_ident);
      if ($ret = $nar_ret[$s_ident])
        return $ret;
	  #echo ht(dump($ret));
/** / # nested set
      global $db, $gid,  $n_navroot, $nav_current;
      static $n = NULL;
      if (is_null($n)) $n = 1+$db->fetch_atom("select max(LFT) from nav where ROOT=". $n_navroot);
      return 'index.php?nav='. $db->fetch_atom("select ID_NAV,
        if (LFT between ". $nav_current['LFT']. " and ". $nav_current['RGT']. ", ". (3*$n). "-LFT,
          if (". $nav_current['LFT']. " between LFT and RGT, ". $n. "+LFT, ". $n. "-LFT)
        ) WEIGHT
      from nav
      where ROOT=".  $n_navroot. " and IDENT='". mysql_escape_string($s_ident). "'
      order by WEIGHT desc limit 0,1");
/*/
      if ($GLOBALS['nar_systemsettings']['SITE']['MOD_REWRITE'])
      {
        global $ar_nav;
        // Pfad?
        $match = FALSE;
        foreach ($ar_nav as $id=>$nav)
        {
          if (!$nav['IDENT'] || !$nav['B_VIS'])
            continue;
          if ($s_ident == $nav['ALIAS'])
          {
            $match = $id;
            break;
          }
          elseif ($s_ident == $nav['IDENT'] && !$match)
            $match = $id;
        }
        $ret = $s_ident. '/';
        if ($match)
        {
          $nav = &$ar_nav[$match];



          if ($nav['PARENT'])
          {
		    #do while entfernt
			#ident für link prüfen
			#jürgen 12.1.09
            #do
            #{
              $nav = &$ar_nav[$nav['PARENT']];
			  #echo (ht(dump($nav)));
              $s_parent = ($nav['ALIAS'] ? $nav['ALIAS'] : $nav['IDENT']);
            #} while ($nav['PARENT']);
			if($nav['IDENT'])
              $ret = $s_parent. '/'. $s_ident.'.htm';
			else
			  $ret = $s_ident.'.htm';
			#echo ht(dump($ret));
          }
        }
      }
      else
        $ret = 'index.php?page='. $s_ident;
/**/  #die(ht(dump($nar_ret)));
      return $nar_ret[$s_ident] = $ret;
    }

    function tpl_img($param)
    {
      global $db, $s_chdir;
      list($id,$wmax,$hmax) = explode(',', $param);
      $tpl_img = new Template('img/img.htm');
      $row = $db->fetch1("select * from img where ID_IMG=". (int)$this->getval(trim($id))/** /. " and OK=3"/**/);
      if (!(int)$wmax) $wmax=NULL;
      if (!(int)$hmax) $hmax=NULL;
#echo $s_chdir, ht(dump($row));
      if ($wmax || $hmax)
        list($row['WIDTH'], $row['HEIGHT']) = getimageresize($s_chdir.$row['SRC'], $wmax, $hmax);
      $tpl_img->addvars($row);
      $tpl_img->addvar('path', (strpos($_SERVER['REQUEST_URI'], '/admin/') ? '../':''));
      return $tpl_img->process();
    }

    function tpl_forward($param)
    {
      forward("index.php?$param");
    }

    function shownav($n_from, $n_to, $b_showall, $s_type, $id_parent)
    {
      global $ar_navpath, $ar_nav, $nar_pageallow, $nar_systemsettings, $s_lang;

      static $cache = array ();
      $s_param = (int)$n_from.':'.(int)$n_to.':'.$b_showall.':'.$s_type.':'.$id_parent;
      if ($cache[$s_param]) return $cache[$s_param];
#if (!$n_from && !$s_type) echo ht(dump($ar_navpath));
#echo "<b>shownav($n_from, $n_to, $b_showall, $s_type, $id_parent)</b><br />";
      $i=0;
      $ar_res = array ();
#if (!$n_from && !$s_type) echo ht(dump($nar_pageallow));
      if(!is_array($ar_nav[$id_parent]))
	  {
	    echo $id_parent ." - id_parent";
		die(ht(dump($ar_nav)));
      }
	  foreach($ar_nav[$id_parent]['KIDS'] as $id)
      {
        $row = $ar_nav[$id];
        // nicht sichtbar? skip
        if (!$row['B_VIS'])
          continue(1);
        // kein IDENT? Unterpunkte scannen
        if (!$row['IDENT'])
        {
#echo '<b>nopage ', stdHtmlentities($row['V1']), '</b><br />';
          $q = $row['KIDS'];
          while ($id_kid = array_shift($q))
          {
            $kid = $ar_nav[$id_kid];
#echo 'kid: ', stdHtmlentities($kid['V1']), '(vis=', $kid['B_VIS'], ', IDENT=', $kid['IDENT'], ')<br />';
            if ($kid['B_VIS'])
            {
              if (!$kid['IDENT'])
                $q = array_merge($kid['KIDS'], $q);
              elseif ($nar_pageallow[$kid['IDENT']])
              {
                $row['IDENT'] = $kid['IDENT'];
                $row['ALIAS'] = $kid['ALIAS'];
                break;
              }
            }
          }
        }
#if (!$n_from && !$s_type) echo ht(dump($row));
        // IDENT und Recht vorhanden ...
        if ($row['IDENT'] && $nar_pageallow[$row['IDENT']])
        {
          $tpl_tmp = new Template('skin/'.$s_lang.'/nav'. (int)$n_from
            . ($s_type ? '.'. $s_type : ''). '.htm', 'nav');

          $tpl_tmp->addvars($row);
		  #echo ht(dump($row));
          $tpl_tmp->addvar('i', $i);
          $tpl_tmp->addvar('POS', $i+1);
          $tpl_tmp->addvar('is_inpath', $fl_current = in_array ($row['ID_NAV'], $ar_navpath));
          $tpl_tmp->addvar('is_current', $tmp = (end($ar_navpath)==$row['ID_NAV']));

          $tpl_tmp->addvar('PAGE', $s_page =
            ($row['ALIAS'] ? 'page='. $row['ALIAS'] :
            ($row['IDENT'] ? 'page='. $row['IDENT'] :
            'nav='. $row['ID_NAV']
          )));

          // href (mit mod_rewrite)
          $href = ($GLOBALS['nar_systemsettings']['SITE']['MOD_REWRITE']
              ? $s = preg_replace('%^/+%', '', str_replace('index/', '',
                  ($row['ident_path'] ? (
                      ($p = strpos($row['ident_path'], '/'))
                          ? substr($row['ident_path'], 0, $p)
                          : $row['ident_path']
                      ). '/' : '')
                  . ($row['ALIAS'] ? $row['ALIAS'] : $row['IDENT'])
                  . ($row['PARENT'] ? '.htm' : '/')
              ))
              : 'index.php?'. $s_page
          );
          // Add SSL if requested.
          $useSSL = ($nar_systemsettings["SITE"]["USE_SSL"] ? ($row['B_SSL'] === "" ? 2 : $row['B_SSL']) : 0);
          // Generate URL

          if (!empty($row['IDENT'])) {
              $href = $this->tpl_uri_action($row['IDENT']);
          } else {
              $href = $this->tpl_uri_baseurl($href, false, !empty($_SERVER['HTTPS']), $useSSL);
          }
          $tpl_tmp->addvar('href', $href);

          $ar_res[] = $tpl_tmp->process(TRUE, TRUE);
          if ($n_to>$n_from && ($b_showall || $row['ID_NAV']==$ar_navpath[$n_from]))
            $ar_res = array_merge($ar_res,
              $this->shownav($n_from+1, $n_to, $b_showall, $s_type, $row['ID_NAV'])
            );
          $i++;
        }
      }
#echo ht(dump($ar_res));flush();#die();
      $cache[$s_param] = $ar_res;
      return $ar_res;
    }

    function tpl_nav_sub($str_params) {
        // Get parameters
        list($navTarget,$options) = explode(",", $str_params);
        // Parse options
        parse_str($options, $arOptions);
        // Default values
        $arOptions = array_merge(array(
            'LEVEL'     => 0,
            'SUFFIX'    => ""
        ), $arOptions);
        // Get target nav entry
        $navTarget = $this->parseTemplateString($navTarget);
        $navId = null;
        $navIdent = null;
        $navEntry = array();
        if (preg_match("/^[0-9]+$/", $navTarget)) {
            // By id
            $navId = (int)$navTarget;
            $navEntry = $GLOBALS['ar_nav'][$navId];
            $navIdent = $navEntry['IDENT'];
        } else {
            // By ident
            $navIdent = $navTarget;
            $navId = $GLOBALS['nar_ident2nav'][$navIdent];
            $navEntry = $GLOBALS['ar_nav'][$navId];
        }
        return $this->nav_sub_process($navEntry, $arOptions['LEVEL'], $arOptions['SUFFIX']);
    }

    function nav_sub_process($arNav, $level, $suffix) {
        $navTemplateFile = "skin/".$GLOBALS['s_lang']."/nav".$level.(empty($suffix) ? "" : ".".$suffix).".htm";
        $navTemplate = new Template($navTemplateFile);
        $navTemplate->addvars($arNav);
        if (!empty($arNav['KIDS'])) {
            $arKidList = array();
            foreach ($arNav['KIDS'] as $kidIndex => $kidId) {
                $arKidEntry = $GLOBALS['ar_nav'][$kidId];
                $arKidList[] = $this->nav_sub_process($arKidEntry, $level+1, $suffix);
            }
            $navTemplate->addvar("sub", $arKidList);
        }
        if ($GLOBALS['nar_systemsettings']['SITE']['TEMPLATE_COMMENTS'] == 1) {
            $comment_start = "\n<!-- NAVIGATION - File: ".$navTemplateFile." -->\n";
            $comment_end = "\n<!-- NAVIGATION END - File: ".$navTemplateFile." -->\n";
            return $comment_start.$navTemplate->process().$comment_end;
        } else {
            return $navTemplate->process();
        }
    }

    function tpl_nav($str_params)
    {
#echo "<b>tpl_nav($str_params)</b><br />";
      global $ar_navpath, $nar_systemsettings, $s_lang;
      // Parameter
      list($n_from, $n_to, $b_showall, $s_type) = explode(',', $str_params);
      $n_from = max(0, (int)$n_from);
      if (($n_to = (int)$n_to)<=0) $n_to = $n_from;
      $b_showall = !!trim($b_showall);
      $s_type = trim($s_type);
#echo "--&gt; $n_from, $n_to, $b_showall, $s_type<br />";
      // go
      if ($n_from > count($ar_navpath) && !$b_showall)
        return '';
      else {
	    if ($nar_systemsettings["SITE"]["TEMPLATE_COMMENTS"] == 1) {
	      $template_file = "skin/".$s_lang."/nav$uint_level$s_suffix.htm";
	      $comment_start = "\n<!-- NAVIGATION - File: ".$template_file." -->\n";
	      $comment_end = "\n<!-- NAVIGATION END - File: ".$template_file." -->\n";
	      return $comment_start.implode('', $this->shownav($n_from, $n_to, $b_showall, $s_type, (int)$ar_navpath[$n_from-1])).$comment_end;
	    } else {
	  	  return implode('', $this->shownav($n_from, $n_to, $b_showall, $s_type, (int)$ar_navpath[$n_from-1]));
	    }
      }
    }

    function showembnav($uint_level, $uint_to, $fl_showall, $row_parent, $s_suffix='')
    {
      global $db, $ar_navpath, $ar_nav, $nar_pageallow, $nar_systemsettings, $s_lang;
      static $ok = NULL;
      if (is_null($ok)) $ok = array_keys($nar_pageallow);
      $uint_parent = (int)(is_array($row_parent) ? $row_parent['ID_NAV'] : $row_parent);
      $ar_result = array();
#echo ht(dump($ok));
      foreach($ar_nav[$uint_parent]['KIDS'] as $id)
      {
        $row = $ar_nav[$id];
        if (!$row['B_VIS'] || ($row['IDENT'] && !in_array($row['IDENT'], $ok))) continue;
        $tpl_nav = new Template("skin/".$s_lang."/nav$uint_level$s_suffix.htm", 'nav');
#echo "skin/nav$uint_level$s_suffix.htm<br>";
        $tpl_nav->addvars($row);

        $tpl_nav->addvar('i', $i);
        $tpl_nav->addvar('POS', $i+1);
        $tpl_nav->addvar('is_inpath', $fl_current = in_array ($row['ID_NAV'], $ar_navpath));
        $tpl_nav->addvar('is_current', $tmp = (end($ar_navpath)==$row['ID_NAV']));

        $tpl_nav->addvar('PAGE', $s_page =
          ($row['ALIAS'] ? 'page='. $row['ALIAS'] :
          ($row['IDENT'] ? 'page='. $row['IDENT'] :
          'nav='. $row['ID_NAV']
        )));
        // href (mit mod_rewrite)
        $href = ($GLOBALS['nar_systemsettings']['SITE']['MOD_REWRITE']
            ? $s = preg_replace('%^/+%', '', preg_replace_callback('%index/(.*)$%', function ($match) {
                    return ($match[1] ? $match[1] : "index.php");
                },
                ($row['ident_path'] ? (
                    ($p = strpos($row['ident_path'], '/'))
                        ? substr($row['ident_path'], 0, $p)
                        : $row['ident_path']
                    ). '/' : '')

                . ($row['ALIAS'] ? $row['ALIAS'] : $row['IDENT'])
                . ($row['PARENT'] ? '.htm' : '/')
            ))
            : 'index.php?'. $s_page
        );
        // Add SSL if requested.
        $useSSL = ($nar_systemsettings["SITE"]["USE_SSL"] ? ($row['B_SSL'] === "" ? 1 : $row['B_SSL']) : 0);
        // Generate URL
        if (!empty($row['IDENT'])) {
            $href = $this->tpl_uri_action($row['IDENT']);
        } else if (!empty($row['ALIAS'])) {
            $href = $this->tpl_uri_action($row['ALIAS']);
            //$href = $this->tpl_uri_baseurl($href, false, !empty($_SERVER['HTTPS']), $useSSL);
        } else {
            #echo '<b>nopage ', stdHtmlentities($row['V1']), '</b><br />';
            $q = $row['KIDS'];
            while ($id_kid = array_shift($q)) {
              $kid = $ar_nav[$id_kid];
              #echo 'kid: ', stdHtmlentities($kid['V1']), '(vis=', $kid['B_VIS'], ', IDENT=', $kid['IDENT'], ')<br />';
              if ($kid['B_VIS']) {
                  if (!$kid['IDENT'])
                      $q = array_merge($kid['KIDS'], $q);
                  elseif ($nar_pageallow[$kid['IDENT']]) {
                      $row['IDENT'] = $kid['IDENT'];
                      $row['ALIAS'] = $kid['ALIAS'];
                      break;
                  }
              }
            }
            if (!empty($row['IDENT'])) {
              $href = $this->tpl_uri_action($row['IDENT']);
            } else if (!empty($row['ALIAS'])) {
              $href = $this->tpl_uri_action($row['ALIAS']);
              //$href = $this->tpl_uri_baseurl($href, false, !empty($_SERVER['HTTPS']), $useSSL);
            }
        }
        $tpl_nav->addvar('href', $href);

        $tpl_nav->addvar('PARENT', $uint_parent);
        $tpl_nav->addvar('is_separator', preg_match('/^\-+$/', $row['LABEL']));
        $tpl_nav->addvar('is_inpath', $fl_current = in_array($row['ID_NAV'], $ar_navpath));
        $tpl_nav->addvar('is_current', end($ar_navpath)==$row['ID_NAV']);
        if (($fl_showall || $fl_current) && ($uint_to<0 || $uint_level<$uint_to))
        {
          $ar_sub = $this->showembnav($uint_level+1, $uint_to,
          $fl_showall, $row, $s_suffix);
#          $fl_inssub = preg_match('/\{sub\}/', $tpl_nav->text);
#          if ($fl_inssub)
          $tpl_nav->addvar('sub', $ar_sub);
          $tmp = $tpl_nav->vars['href'];
/**/
          if (!$tmp || '.htm'==$tmp)
          {
            $tmp = '';
            if ($ar_sub && is_array($ar_sub) )
              foreach($ar_sub as $tpl_sub)
                if ($tmp = $tpl_sub->vars['href']) if ('.htm'!=$tmp)
                {
                  $tpl_nav->vars['href'] = $tmp;
                  break;
                }
                else
                  $tmp = '';
            if (!$tmp)
              $tpl_nav->vars['href'] = '';
          }
/**/
        }
        else
          $ar_sub = FALSE;
        $ar_result[] = $tpl_nav;
/*
        if ($ar_sub && !$fl_inssub)
          foreach($ar_sub as $subrow)
            $ar_result[] = $subrow;
*/
      }
      return $ar_result;
    }
    function tpl_navemb($str_params) {
        $eventNav = false;
        if (array_key_exists('ebizRecordLoadtime', $_COOKIE)) {
            $eventNav = Tools_LoadtimeStatistic::getInstance()->createEvent("Template-Nav", "Navigation", array("params" => $str_params));
        }
        global $group, $ar_navpath;
        list($uint_from, $uint_to, $fl_showall, $s_suffix) = explode(',', $str_params);
        if ($s_suffix) $s_suffix = '.' . $s_suffix;
#echo ht(dump($s_suffix));
        if ('*' == $uint_to) $uint_to = -1;
        elseif (!$uint_to) $uint_to = $uint_from;
        $result = $this->process_value($this->showembnav($uint_from, $uint_to, $fl_showall, (int)$ar_navpath[$uint_from - 1], $s_suffix));
        if ($eventNav !== false) {
            $eventNav->finish();
        }
        return $result;
    } // DirTemplate::tpl_navemb

    function tpl_liste($s_param)
    {
      list($s_varname, $s_tplfile) = explode(',', $s_param);
      $s_tplpath = dirname($this->filename). '/'. trim($s_tplfile);
      $ar_liste = array ();
      $array = $this->vars[trim($s_varname)];
      foreach($array as $i=>$row)
      {
        $tpl_tmp = new Template($s_tplpath, $this->table);
        $tpl_tmp->addvars($row);
        $tpl_tmp->addvar('i', $i);
        $tpl_tmp->addvar('even', 1-($i&1));
        $ar_liste[] = $tpl_tmp->process();
      }
      return implode('', $ar_liste);
    }

    function tpl_curref()
    {
      return $GLOBALS['s_curref'];
    }

    function tpl_langref($param)  {
        global $ab_path, $db, $id_nav, $urlCurrentRequest, $originalSystemSettings;
        // Get target language abbr.
        $abbr = $this->getval($param);
        // Set target language
        $language = $GLOBALS['lang_list'][$abbr];
        $idLang = $language['ID_LANG'];
        // Allow plugins to manipulate URL parameters
        $urlLink = Api_Entities_URL::createFromPage( $urlCurrentRequest->getPageIdent(), $urlCurrentRequest->getPageIdentPath(), $urlCurrentRequest->getPageAlias(), 
            array_slice($urlCurrentRequest->getPageParameters(), 1), $urlCurrentRequest->getPageParametersOptional(), false, $idLang);
        $urlParams = new Api_Entities_EventParamContainer(array(
            "url"       => $urlLink,
            "template"  => $this
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::URL_GENERATE, $urlParams);
        // Generate URL
        require_once $ab_path."sys/lib.nav.url.php";
        $navUrlMan = NavUrlManagement::getInstance($db);
        $urlIdent = $urlLink->getPageIdent();
        $urlParams = $urlLink->getPageParameters();
        $urlParamsOpt = $urlLink->getPageParametersOptional();
        $url = $navUrlMan->generateUrlByNav($id_nav, $urlParams, $urlParamsOpt, null, $idLang);

        global $ar_nav_urls, $ar_nav_urls_by_id;
        // Store current settings
        $currentLangId = $GLOBALS['lang_list'][ $GLOBALS['s_lang'] ]['ID_LANG'];
        $currentSiteURL = $GLOBALS["nar_systemsettings"]['SITE']['SITEURL'];
        $currentSiteURLBase = $GLOBALS["nar_systemsettings"]['SITE']['BASE_URL'];
        // Load target language
        if (file_exists($ab_path.'cache/nav.url.'.$idLang.'.php')) {
            include $ab_path.'cache/nav.url.'.$idLang.'.php';    // Siehe sys/lib.nav.url.php -> updateCache()
        } else {
            $ar_nav_urls = array();
            $ar_nav_urls_by_id = array();
        }
        $GLOBALS["nar_systemsettings"]['SITE']['SITEURL'] = ($language["DOMAIN"] != "" ? $language["DOMAIN"] : $originalSystemSettings['SITE']['SITEURL']);
        $GLOBALS["nar_systemsettings"]['SITE']['BASE_URL'] = ($language["BASE_URL"] != "" ? $language["BASE_URL"] : $originalSystemSettings['SITE']['BASE_URL']);
        // Generate URL
        if ($url !== false) {
            $url = $this->tpl_uri_baseurl($url);
        } else {
            // Custom URL found!
            array_unshift($urlParams, $urlIdent);
            $url = $this->tpl_uri_action(implode(",", $urlParams));
        }
        // Restore original values
        if (file_exists($ab_path.'cache/nav.url.'.$currentLangId.'.php')) {
            include $ab_path.'cache/nav.url.'.$currentLangId.'.php';    // Siehe sys/lib.nav.url.php -> updateCache()
        } else {
            $ar_nav_urls = array();
            $ar_nav_urls_by_id = array();
        }
        $GLOBALS["nar_systemsettings"]['SITE']['SITEURL'] = $currentSiteURL;
        $GLOBALS["nar_systemsettings"]['SITE']['BASE_URL'] = $currentSiteURLBase;
        return $url;
    }


    function tpl_sys($param)
    {
      $tmp = explode('.', $param);
      $s = $GLOBALS['nar_systemsettings'][$tmp[0]][$tmp[1]];
      if ('SITE'==$tmp[0] && 'SITEURL'==$tmp[1] && 128>($tmp = $GLOBALS['langval']))
        $s = preg_replace('%//\w+\.%', '//'. $GLOBALS['s_lang']. '.', $s);
      return $s;
    }

    function tpl_loadperm($s_ident)
    {
      global $db;
      $s_ident = trim($s_ident);
      $n_val = $db->perm_check($s_ident);
      $this->addvar('perm_'. $s_ident, $n_val);
      $this->addvar('perm_'. $s_ident. '_R', $n_val & PERM_READ);
      $this->addvar('perm_'. $s_ident. '_C', $n_val & PERM_CREATE);
      $this->addvar('perm_'. $s_ident. '_E', $n_val & PERM_EDIT);
      $this->addvar('perm_'. $s_ident. '_D', $n_val & PERM_DEL);
#      $this->addvar('perm_'. $s_ident. '_S', $n_val & PERM_SHOW);
      return '';
    }

    function tpl_editor($params)
    {
      global $nar_systemsettings,$editor_is_set;
    list($param,$width,$height,$css,$pluginMode) = explode(',', $params);
    if (empty($width))
        $width="760";
      if (empty($height))
        $height='400';
      $value='';
      if (isset($this->vars[$param]))
        $value = stdHtmlentities(addnoparse($this->getval($param)));
      $return = '';
    if(!$editor_is_set)
    {
      $translationStyle = "
        .mceButton.mceButtonEnabled.mce_image:after { content: '".Translation::readTranslation("general", "editor.image.insert.label", null, array(), "Bild einfügen/bearbeiten")."' }
        a.browse span:after { content: '".Translation::readTranslation("general", "editor.image.browse.label", null, array(), "Auswählen")."'; }";  
      $return = '<style type="text/css">'.$translationStyle.'</style>'."\n".
        $this->tpl_javascript_require_base("/tinymce/jscripts/tiny_mce/tiny_mce.js,tinyMCE");
    $editor_is_set = TRUE;
    } // erstes mal geladen
    $plugins = "safari,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template";
    $buttons1 = "formatselect,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull";
    $buttons2 = "cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,link,unlink,anchor,cleanup,|,insertdate,inserttime,|";
    if (!empty($pluginMode)) {
        switch ($pluginMode) {
            case 'simple+image':
                $buttons2 = "formatselect,|,cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,image,link,unlink,anchor,cleanup,|,insertdate,inserttime,|";
                break;
            case 'advanced':
                $buttons1 = "formatselect,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,|,tablecontrols";
                $buttons2 = "cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,link,unlink,anchor,cleanup,|,insertdate,inserttime,|";
                break;
        }
    }

    $return .= '<script type="text/javascript">
jQuery(function() {
  tinyMCE.init({
    // General options
    mode : "none",
    elements : "'.$param.'",
    theme : "advanced",
    width: "'.$width.'",
    height: "'.$height.'",
    language: "de",
    object_resizing : false,
    convert_fonts_to_spans : true,
	convert_urls : false,
    document_base_url : "/",
    relative_urls : false,
    remove_script_host : true,
	file_browser_callback : "editorImageUpload",

    plugins : "'.$plugins.'",

    // Theme options
    theme_advanced_buttons1 : "'.$buttons1.'",
    theme_advanced_buttons2 : "'.$buttons2.'",
    theme_advanced_buttons3 : "",
    theme_advanced_buttons4 : "",
    theme_advanced_toolbar_location : "top",
    theme_advanced_toolbar_align : "left",
    theme_advanced_statusbar_location : "bottom",
    theme_advanced_resizing : true,

    // Example content CSS (should be your site CSS)
    content_css : "'.($css ? $css : '/skin/style.css').'?" + new Date().getTime()
  });

    tinyMCE.execCommand("mceAddControl", true, "'.$param.'");
});
</script>';

    $return .= "\n\n";

    $return .= '<textarea name="'.$param.'" id="'.$param.'">'.$value.'</textarea>';

    return $return;
    }


    function tpl_editor_bb($params)
    {
      global $nar_systemsettings,$editor_is_set;
    list($param,$width,$height,$css,$html) = explode(',', $params);
    if (empty($width))
        $width="760";
      if (empty($height))
        $height='400';
      $value='';
      if (isset($this->vars[$param]))
        $value = stdHtmlentities(addnoparse($this->getval($param)));
      $return = '';
    if(!$editor_is_set)
    {
        $return = '<script type="text/javascript" src="'.$this->tpl_uri_baseurl('/tinymce/jscripts/tiny_mce/tiny_mce.js').'"></script>';
    $editor_is_set = TRUE;
    } // erstes mal geladen

    $return .= '<script type="text/javascript">
  tinyMCE.init({
    // General options
    theme : "advanced",
    mode : "none",
    plugins : "bbcode",

    elements : "'.$param.'",
    theme : "advanced",
    width: "'.$width.'",
    height: "'.$height.'",
    language: "de",
    object_resizing : false,
    convert_fonts_to_spans : true,
    convert_urls : false,
    document_base_url : "/",
    relative_urls : false,
    remove_script_host : true,
    entity_encoding : "raw",
    add_unload_trigger : false,
    remove_linebreaks : false,
    inline_styles : false,

    theme_advanced_buttons1 : "bold,italic,underline,|,cut,copy,paste,pastetext,pasteword,|,undo,redo,|,link,unlink,forecolor,removeformat,cleanup",
    theme_advanced_buttons2 : "",
    theme_advanced_buttons3 : "",
    theme_advanced_toolbar_location : "top",
    theme_advanced_toolbar_align : "center",
    theme_advanced_statusbar_location : "bottom",
    theme_advanced_styles : "",

    // Example content CSS (should be your site CSS)
    content_css : "'.($css ? $css : '/skin/style.css').'?" + new Date().getTime()
  });
    tinyMCE.execCommand("mceAddControl", true, "'.$param.'");
</script>';

    $return .= "\n\n";

    $return .= '<textarea name="'.$param.'" id="'.$param.'"'.($html ? " ".$html : "").'>'.$value.'</textarea>';

    return $return;
    }


      
    function tpl_country_flag($params) {
        global $db;
        list($countryId, $imageSuffix, $imagePrefix) = explode(',', $params);
        $countryId = $this->parseTemplateString($countryId);
        $countryCode = "unknown";
        if (array_key_exists("cacheCountryCodeMapping", $GLOBALS) && array_key_exists($countryId, $GLOBALS["cacheCountryCodeMapping"])) {
            $countryCode = $GLOBALS["cacheCountryCodeMapping"][$countryId];
        } else {
            if (!array_key_exists("cacheCountryCodeMapping", $GLOBALS)) {
                $GLOBALS["cacheCountryCodeMapping"] = array();
            }
            $countryCode = $db->fetch_atom("SELECT CODE FROM `country` WHERE ID_COUNTRY=".(int)$countryId);
            $GLOBALS["cacheCountryCodeMapping"][$countryId] = $countryCode;
        }
        return $this->tpl_uri_baseurl("/gfx/lang.".$imagePrefix.strtolower($countryCode).$imageSuffix.".gif");
    }
      
    function tpl_country_name($params) {
        global $db, $langval;
        list($countryId) = explode(',', $params);
        $countryId = $this->parseTemplateString($countryId);
        $countryName = "unknown";
        if (array_key_exists("cacheCountryNameMapping", $GLOBALS) && array_key_exists($countryId, $GLOBALS["cacheCountryNameMapping"])) {
            $countryName = $GLOBALS["cacheCountryNameMapping"][$countryId];
        } else {
            if (!array_key_exists("cacheCountryNameMapping", $GLOBALS)) {
                $GLOBALS["cacheCountryNameMapping"] = array();
            }
            $countryName = $db->fetch_atom("
              SELECT s.V1 FROM `country` c
              JOIN `string` s ON s.S_TABLE='country' AND c.ID_COUNTRY=s.FK
                AND s.BF_LANG=if(c.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(c.BF_LANG+0.5)/log(2)))
              WHERE c.ID_COUNTRY=".(int)$countryId);
            $GLOBALS["cacheCountryNameMapping"][$countryId] = $countryName;
        }
        return $countryName;
    }
      
	function tpl_content_page($param)
    {
      global $s_lang,$ar_nav,$id_nav,$ar_byname;
      if(!$param)
	  {
	    if($ar_nav[$id_nav]['FK_INFOSEITE'])
		  $file=$ar_nav[$id_nav]['FK_INFOSEITE'];
	  }
	  else {
        $param = $this->parseTemplateString($param);
        if((int)$param > 0 && in_array($param, $ar_byname)) {
            $file = $param;
        } else {
	        $file = $ar_byname[$param];
        }
      }
#echo $param." :: ".$file."<hr />";
#echo ht(dump($ar_byname));
	  if($file)
	  {
	    $tpl_tmp = new Template("cache/info/".$s_lang.".".$file.".htm");
	    $tpl_tmp->addvars($this->vars);
		return $tpl_tmp->process();
		#return file_get_contents("cache/info/".$s_lang.".".$file.".htm");
	  }
	  else
	    return (!SILENCE ? '<div class="hinweis"><span class="error">Fehlender Content-Bereich &quot;'.$param.'&quot;</span></div>' : "");
#return $GLOBALS['db']->fetch_atom("select T1 from string_c where V1='".$param."'");
    }

      function tpl_compare_list_contains($param) {
          $arList = explode(",", $param);
          $varName = array_shift($arList);
          if (array_key_exists($varName, $this->vars)) {
              $varList = explode(",", $this->vars[$varName]);
              foreach ($varList as $varListIndex => $varListValue) {
                  if (!in_array($varListValue, $arList)) {
                      return 0;
                  }
              }
              return 1;
          }
          return 0;
      }

      function tpl_compare_list_equals($param) {
          $arList = explode(",", $param);
          $varName = array_shift($arList);
          if (array_key_exists($varName, $this->vars)) {
              $varList = explode(",", $this->vars[$varName]);
              if (implode(",", $varList) == implode(",", $arList)) {
                  return 1;
              }
              return 0;
          }
          return 0;
      }

  function tpl_advertisement($param)
    {
    	// Werbung ebiz-trader
    	global $db, $id_kat;
    	if (!empty($param)) {
            $eventAdvertisement = false;
            if (array_key_exists('ebizRecordLoadtimeTemplate', $_COOKIE)) {
                $eventAdvertisement = Tools_LoadtimeStatistic::getInstance()->createEvent("Template", "{advertisement(".$param.")}");
            }
	    	list($id_ad) = explode(",", $param);
	    	if (($id_kat > 0) && ($id_ad > 0)) {
		    	$ar_ad = $db->fetch1("
		    		SELECT
		    			u.ID_ADVERTISEMENT_USER,
		    			u.CODE,
		    			k.FK_KAT,
		    			s.COUNT
		    		FROM
		    			`advertisement_user` u
		    		LEFT JOIN
		    			`advertisement_kat` k ON
		    				k.FK_ADVERTISEMENT_USER=u.ID_ADVERTISEMENT_USER
		    		LEFT JOIN
		    			`advertisement_stat` s ON
		    				s.FK_ADVERTISEMENT_USER=u.ID_ADVERTISEMENT_USER AND s.STAMP=CURDATE() AND
		    				s.FK_KAT=k.FK_KAT
		    		WHERE
		    			u.FK_ADVERTISEMENT=".$id_ad." AND u.ENABLED=1 AND k.FK_KAT=".$id_kat."
		    			AND (CURDATE() BETWEEN u.STAMP_START AND u.STAMP_END)
		    		ORDER BY
		    			s.COUNT ASC, RAND() ASC
		    		LIMIT 1");
		    	if (!empty($ar_ad)) {
			    	if ($ar_ad['COUNT'] == NULL) {
			    		$db->querynow("
			    			INSERT INTO `advertisement_stat`
			    				(FK_ADVERTISEMENT_USER, FK_KAT, STAMP, COUNT)
			    			VALUES
			    				(".$ar_ad['ID_ADVERTISEMENT_USER'].", ".$id_kat.", CURDATE(), 1)");
			    	} else {
			    		$db->querynow("
			    			UPDATE
			    				`advertisement_stat`
			    			SET
			    				COUNT=COUNT+1
			    			WHERE
			    				STAMP=CURDATE() AND FK_KAT=".$id_kat." AND
			    				FK_ADVERTISEMENT_USER=".$ar_ad['ID_ADVERTISEMENT_USER']);
			    	}
		    		return $ar_ad['CODE'];
		    	}
	    	}
	    	$fallback = $db->fetch_atom("SELECT FALLBACK FROM `advertisement` WHERE ID_ADVERTISEMENT=".$id_ad);
            if ($eventAdvertisement !== false) {
                $eventAdvertisement->finish();
            }
	    	if (!empty($fallback)) {
	    		// Infobereich fallback
	    		return $fallback;
	    	}
    	}
    	return "";
    }

	  function tpl_adserver($param)
	  {
		  GLOBAL $db;
		  list($top,$kat,$typ) = explode(',', $param);
		  if ($top)	$where = "and top=1";
		  if ($typ)	$where.= "and LU_BANNER=".$typ;
		  $q="select ID_ADS,banner,CURDATE() as akttime,ID_ADS*0+ rand() AS sort from ads left join kat start on start.ID_KAT=".$kat." left join kat k on FK_KAT=k.ID_KAT where (k.LFT >=start.LFT and k.RGT <= start.RGT) and aktiv=1 and DATE_END >= CURDATE()and DATE_START <= CURDATE()  ".$where." order by sort";
		  $row = $db->fetch1($q);
		  $query = 'SELECT * FROM ads_stats a WHERE a.FK_ADS = "'.$row['ID_ADS'].'" AND a.DATUM = "'.$row['akttime'].'"';
		  $count = count($db->fetch_table( $query ));
		  if ($count==0) {
			  $db->querynow ("insert into ads_stats (VIEWS, FK_ADS,DATUM ) values (1,".$row['ID_ADS'].",'".$row['akttime']."')");
		  }
		  else {
			  $db->querynow("update ads_stats  set VIEWS=VIEWS+1 where FK_ADS =".$row['ID_ADS']." and DATUM='".$row['akttime']."'");
		  }
          $tpl_banner = new Template("tpl/de/empty.htm");
          $tpl_banner->tpl_text = $row['banner'];
          return $tpl_banner->process();
	  }

	function tpl_thumbnail($param, $configuration = array()) {
        $tmp = explode(',', $param);

        $file = $tmp['0'];
        if(strpos($file, '{') !== FALSE) {
            $file = $this->parseTemplateString($file);


        } elseif(substr($file, 0, 1) == '"') {
            $file = substr($file, 1, -1);
        } else {
            $file = $this->getval($file);
            if(substr($file, 0, 1) != '/') $file = '/'.$file;
        }
        if ((strpos($file, $GLOBALS["ab_baseurl"]) === 0)) {
            $file = preg_replace("/".preg_quote($GLOBALS["ab_baseurl"], "/")."/", "/", $file, 1);
        }
    
        if(isset($tmp['1']) == TRUE && trim($tmp['1']) != 'null') {
            $width = $tmp['1'];
        } else {
             $width = NULL;
        }
        if(isset($tmp['2']) && trim($tmp['2']) != 'null') {
            $height = $tmp['2'];
        } else {
            $height = NULL;
        }

        if (($tmp['3'] == 'crop') || ($tmp['3'] == 'crop-height')) {
            $crop = "height";
        } else if ($tmp['3'] == 'crop-width') {
            $crop = "width";
        } else {
            $crop = FALSE;
        }
        
        $gravity = (isset($tmp['4']) ? $tmp['4'] : "Center");

        require_once dirname(__FILE__).'/lib.imagecache.php';

        $imagecache = new Hostbar_Imagecache();
		if(isset($configuration['cachePath']) && $configuration['cachePath'] != '') {
			$imagecache->setCachePath($configuration['cachePath']);
		}

        $file = $imagecache->cache($file, $width, $height, $crop, $gravity);
        $file = str_replace($GLOBALS["ab_path"], "", $file);

        // Create URL object with host set to false in order to force relative links for images
        $urlLink = Api_Entities_URL::createFromURL( false );
        
        return $this->tpl_uri_baseurl($file, false, null, 1, $urlLink);
    }

    function tpl_thumbnail_article($param) {
		global $ab_path;
		require_once $ab_path.'sys/lib.ads.php';

		$tmp = explode(',', $param);
		$articleId = $this->parseTemplateString(array_shift($tmp));
		if((int)$articleId == 0) {
			return $this->tpl_thumbnail(implode(',', $tmp));
		}

		$articleCachePath = AdManagment::getAdCachePath($articleId, true, false);

		return $this->tpl_thumbnail(implode(',', $tmp), array('cachePath' => $articleCachePath));
	}

      function tpl_thumbnail_article_loader($param) {
          $tmp = explode(',', $param);

          $loaderId = $this->parseTemplateString(array_shift($tmp));
          $articleId = $this->parseTemplateString(array_shift($tmp));
          $width = $this->parseTemplateString(array_shift($tmp));
          $height = $this->parseTemplateString(array_shift($tmp));
          $crop = (array_shift($tmp) == 'crop')?true:false;

          return Template_Helper_ArticleImageLoader::renderArticleImageLoader($loaderId, $articleId, $width, $height, $crop);
      }

    function tpl_user_jobs($params) {
    	global $db, $langval;
    	list($id_user,$template,$limit) = explode(",", $params);
    	if (!is_numeric($id_user)) {
    		$id_user = $this->getval($id_user);
    	}
    	// Verwendetes Template
		$str_subtpl = "tpl/de/user_job".(!empty($template) ? ".".$template : "").".htm";
		$str_rowtpl = "tpl/de/user_job".(!empty($template) ? ".".$template : "").".row.htm";
    	// Jobs aus der Datenbank auslesen
		$query = "SELECT
    			j.*, sj.*,
    			(SELECT NAME FROM `user` WHERE ID_USER=j.FK_USER) as NAME
    		FROM `job` j
    		LEFT JOIN `string_job` sj ON
    			sj.S_TABLE='job' AND sj.FK=j.ID_JOB AND
    			sj.BF_LANG=if(j.BF_LANG_JOB & ".$langval.", ".$langval.", 1 << floor(log(j.BF_LANG_JOB+0.5)/log(2)))";
		// Nur für bestimmten User?
		if ($id_user > 0) {
			$query .= "\nWHERE j.FK_USER=".(int)$id_user;
		}
		$query .= "\nORDER BY j.STAMP DESC, j.B_TOP DESC";
		if ($limit > 0) {
			$query .= "\nLIMIT ".$limit;
		} else {
			$query .= "\nLIMIT 5";
		}
    	$ar_results = $db->fetch_table($query);
    	// Ergebnisse auflisten
    	$tpl_liste = new Template($str_subtpl, $this->table);
    	$tpl_liste->addlist("liste", $ar_results, $str_rowtpl);
		return $tpl_liste->process();
    }

	function tpl_kat($params)
	{
	  $tpl = (empty($params) ? 'kat1' : $params);
	  include_once "cache/kat2.".$GLOBALS['s_lang'].".php";
	  if(!isset($ar_nav))
	    $ar_nav = $GLOBALS['ar_kat'];
	  else
	    $GLOBALS['ar_kat'] = $ar_nav;
	  global $db;
	  $id_kat = (int)$GLOBALS['ar_params'][1];
	  if(!isset($ar_nav[$id_kat]))
	    $id_kat = 0;
	  $tmp = array();
	  if($id_kat > 0)
	  {
		//$id_kat = (isset($ar_nav[$id_kat]['LEVEL']) ? $ar_nav[$id_kat]['ID_KAT'] : $ar_nav[$ar_nav[0]['KIDS'][0]]['ID_KAT']);
	    $level = $ar_nav[$id_kat]['LEVEL'];
		$max_rgt = $ar_nav[$id_kat]['RGT'];
		$min_lft = $ar_nav[$id_kat]['LFT'];
		if($ar_nav[$id_kat]['PARENT'] == 0)
		{
		  $ar_kat = $db->fetch1("select t.ID_KAT, s.V1
		   from `kat` t
		   left join string_kat s on s.S_TABLE='kat' and s.FK=t.ID_KAT and s.BF_LANG=if(t.BF_LANG_KAT & ".$GLOBALS['langval'].", ".$GLOBALS['langval'].", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
		   where ROOT=2 and LFT=1");
		}
		else
		{
		  $ar_kat = $ar_nav[$ar_nav[$id_kat]['PARENT']];
		}
		$ar_kat['PARENT_LINK']=1;
		$tpl_tmp = new Template("skin/".$GLOBALS['s_lang']."/".$tpl.".htm");
		$tpl_tmp->addvars($ar_kat);
		$ar[] = $tpl_tmp->process();
		foreach($ar_nav as $key => $value)
		{
		  if($max_rgt < $value['RGT'] || $min_lft > $value['LFT'] || $key == 0)
		  {
		    #echo "ar level: ".$value['LEVEL']." forderung: ".$level."<br />";
			continue;
		  }
		  if($id_kat == $value['ID_KAT'])
		    $value['is_current']=1;
		  $tpl_tmp = new Template("skin/".$GLOBALS['s_lang']."/".$tpl.".htm");
		  $tpl_tmp->addvars($value);
		  $ar[] = $tpl_tmp->process();
		}
	  }
	  else
	  {
	    #echo "hier<hr />";
		#echo ht(dump($ar_nav[0]));
		$level = 1;
		for($i=0; $i<count($ar_nav[0]['KIDS']); $i++)
		{
		  #echo ht(dump($ar_nav[$ar_nav[0]['KIDS'][$i]]));
		  $tpl_tmp = new Template("skin/".$GLOBALS['s_lang']."/".$tpl.".htm");
		  $tpl_tmp->addvars($ar_nav[$ar_nav[0]['KIDS'][$i]]);
		  $ar[] = $tpl_tmp->process();
		}
		reset($ar_nav[0]['KIDS']);
	  }

	  return (empty($ar) ? FALSE : implode('', $ar)); //$tpl_tmp->process();
	}

  function tpl_getubox( $param ) // by Maurice ;)
	// $param = User-id und UserBoxNr
	{
        global $ab_path;

		$param_array = explode( "," , $param );
		$id = $this->vars[$param_array[0]];
//		$id = $param_array[0];
		$uboxNr = $param_array[1];
		return @file_get_contents($ab_path.'cache/usercache/ubox/ubox'.$id.'_'.$uboxNr.'.php');
	}

	function tpl_question()
	{
	  global $db, $langval;
	  $ar = $db->fetch1("select t.ID_QUESTION, s.V1 from `question` t
        left join string_app s on s.S_TABLE='question'
	     and s.FK=t.ID_QUESTION
	     and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
	    order by RAND()
		LIMIT 1");
	  return '<input type="hidden" name="ID_Q" value="'.$ar['ID_QUESTION'].'" /> '.stdHtmlentities($ar['V1']);
	} // question

	function tpl_youtube($param) {
		global $s_lang;
		// Parameter
		list($id,$width,$height) = explode(',', $param);
		// Mit einer Rauta am Anfang wird direkt der Youtube-Code übergeben, ansonsten wird der
		// Code aus der Template-Variable mit dem angegebenen Namen geholt.
		if (substr($id, 1, 1) != "#") {
			$id = $this->getval($id);
		}
		// Infobereich für Youtube-Player holen
		$file = "youtube"; // <-- Name des Templates
		$tpl_tmp = new Template("tpl/".$s_lang."/".$file.".htm");
		$tpl_tmp->addvar("id", $id);
		$tpl_tmp->addvar("width", $width);
		$tpl_tmp->addvar("height", $height);
		// HTML ausgeben
		return $tpl_tmp->process();
	}

	function tpl_youtube_preview($param) {
		global $s_lang;
		// Parameter
		list($id,$width,$height,$preview_width,$preview_height) = explode(',', $param);
		if (empty($width)) $width = 800;
		if (empty($height)) $height = 600;
		// Mit einer Rauta am Anfang wird direkt der Youtube-Code übergeben, ansonsten wird der
		// Code aus der Template-Variable mit dem angegebenen Namen geholt.
		if (substr($id, 1, 1) != "#") {
			$id = $this->getval($id);
		}
		// Infobereich für Youtube-Player holen
		$file = "youtube_thumb"; // <-- Name des Templates
		$tpl_tmp = new Template("tpl/".$s_lang."/".$file.".htm");
		$tpl_tmp->addvar("youtube_server", rand(1, 4));
		$tpl_tmp->addvar("id", $id);
		$tpl_tmp->addvar("width", $width);
		$tpl_tmp->addvar("height", $height);
		$tpl_tmp->addvar("preview_width", $preview_width);
		$tpl_tmp->addvar("preview_height", $preview_height);
		// HTML ausgeben
		return $tpl_tmp->process();
	}

	function tpl_youtube_input($param) {
		global $s_lang;
		// Parameter
		list($name,$size,$class,$button,$target) = explode(',', $param);
        require_once $GLOBALS["ab_path"]."sys/lib.youtube.php";
        return Youtube::GenerateInput($name,$size,$class,$button,$target);
	}

    function tpl_equals($param)
    {
      // Check if the given variables and/or parameters equal each other.
      $parameter = explode(',', $param);
      if (count($parameter) > 1) {
        $paramFirst = $this->getval(array_shift($parameter));
        while (count($parameter) > 0) {
          $paramCur = $this->getval(array_shift($parameter));
          if ($paramFirst != $paramCur) {
            return 0;
          }
        }
      }
      return 1;
    }

      function tpl_c_equals($param) {
      	  // TODO: Should this function do what the one above does?
          $parameter = explode(',', $param);

          $equality = TRUE;
          for($i = 0; ($i < count($parameter) - 2); $i++) {
              if($parameter[$i] != $parameter[$i+1]) {
                  $equality = FALSE;
              }
          }

          return $equality?1:0;
      }

      function tpl_filesize($param) {
          list($paramBytes, $paramDecimals) = explode(",", $param);
          // Default parameters
          if ($paramDecimals === null) $paramDecimals = 2;
          // Parse byte count (in case it is a template variable)
          $sizeBytes = (int)$this->parseTemplateString($paramBytes);
          // Output the proper formated size
          $sizeUnits = array("Byte", "KiB", "MiB", "GiB", "TiB", "PiB", "EiB", "ZiB", "YiB");
          $factor = floor((strlen($sizeBytes) - 1) / 3);
          return sprintf("%.".$paramDecimals."f", $sizeBytes / pow(1024, $factor)) . $sizeUnits[$factor];
      }

      function tpl_search_site_json_ld() {
        $str = '{
            "@context":     "http://schema.org",
            "@type":        "WebSite",
            "url":          "'.$GLOBALS['nar_systemsettings']['SITE']['SITEURL'].'",
            "potentialAction": {
                "@type":    "SearchAction",
                "target":   "'.$this->tpl_uri_baseurl('/index.php?SEARCH_PROXY=1&page=artikel-suche&PRODUKTNAME=&#123;search_term_string&#125;', true).'",
                "query-input":  "required name=search_term_string"
            }
        }';
        return $str;
      }

      function tpl_image_data_json_ld($src) {
        if ( strpos($src,",") != false ) {
            list($src,$json) = explode(",",$src);
        }
        $src = $this->parseTemplateString($src);
        list($width,$height) = getimagesize($src);
          $arr = array(
              "@type"   =>		"ImageObject",
			  "url"     =>		$this->tpl_uri_baseurl_full($src),
			  "width"   =>  	strval($width),
              "height"  =>      strval($height)
          );
          if ( $json == "1" ) {
	          return json_encode($arr);
          }
          return $arr;
      }

      function add_bread_crumbs_json_ld( $arPath, $category_type = null ) {
	    $list_elements = array();
	      $http = "";
	      if ( isset($_SERVER["HTTPS"]) ) {
		      $http = 'https://';
	      }
	      else {
		      $http = 'http://';
	      }
        foreach ( $arPath as $index => $row ) {
	        $itemListElement = array(
	            "@type"     =>  "ListItem",
                "item"      =>  array(),
                "position"  =>  $index + 1
            );

	        if ( $category_type == "news" ) {
		        if ( isset($row["HREF"]) ) {
			        $itemListElement['item']["@id"] = $http . $_SERVER["HTTP_HOST"] . $row["HREF"];
		        }
		        else {
			        if ( $row["LFT"] == "1" ) {
				        $itemListElement['item']["@id"] = $this->tpl_uri_action_full("news");
			        }
			        else {
				        $itemListElement['item']["@id"] = $this->tpl_uri_action_full("news,,,,".$row["ID_KAT"]);
			        }
		        }
            } else if ( $category_type == "calendar_events" ) {
		        if ( isset($row["HREF"]) ) {
			        $itemListElement['item']["@id"] = $http . $_SERVER["HTTP_HOST"] . $row["HREF"];
		        }
		        else {
			        if ( $row["LFT"] == "1" ) {
				        $itemListElement['item']["@id"] = $this->tpl_uri_action_full("calendar_events");
			        }
			        else {
				        $itemListElement['item']["@id"] = $this->tpl_uri_action_full("calendar_events,".$row["ID_KAT"]);
			        }
		        }
            } else if ( $category_type == "jobs" ) {
		        if ( isset($row["HREF"]) ) {
			        $itemListElement['item']["@id"] = $http . $_SERVER["HTTP_HOST"] . $row["HREF"];
		        }
		        else {
			        if ( $row["LFT"] == "1" ) {
				        $itemListElement['item']["@id"] = $this->tpl_uri_action_full("jobs");
			        }
			        else {
				        $itemListElement['item']["@id"] = $this->tpl_uri_action_full("jobs,".$row["ID_KAT"]);
			        }
		        }
	        } else if ( $category_type == "marketplace" ) {
		        if ( isset($row["HREF"]) ) {
			        $itemListElement['item']["@id"] = $http . $_SERVER["HTTP_HOST"] . $row["HREF"];
		        }
		        else {
			        if ( $row["LFT"] == "1" ) {
				        $itemListElement['item']["@id"] = $this->tpl_uri_action_full("marktplatz");
			        }
			        else {
				        $itemListElement['item']["@id"] = $this->tpl_uri_action_full("marktplatz,".$row["ID_KAT"].",".$this->tpl_urllabel($row["V1"]));
			        }
		        }
	        }

	        $itemListElement['item']["name"] = $row["V1"];
	        array_push($list_elements,$itemListElement);
        }
        $arr = array(
            "@context"          =>  "http://schema.org",
            "@type"             =>  "BreadcrumbList",
            "itemListElement"   =>  $list_elements
        );
        return json_encode($arr);
      }

      function tpl_organization_data_json_ld() {
	      $parameterHash = md5("organization");
	      $result = new Template("tpl/de/empty.htm");
	      $result->isTemplateRecursiveParsable = true;
	      $result->isTemplateCached = true;
	      $cachedir = $GLOBALS['ab_path']."cache/json_ld";
	      if (!is_dir($cachedir)) {
		      mkdir($cachedir, 0777, true);
	      }
	      $cachefile_json_ld = $cachedir."/".$GLOBALS['s_lang'].".organization.".$parameterHash.".json.ld.htm";
	      $cacheFileLifeTime = $GLOBALS['nar_systemsettings']['CACHE']['LIFETIME_CATEGORY'];
	      $modifyTime = @filemtime($cachefile_json_ld);
	      $diff = ((time() - $modifyTime) / 60);
	      if (($diff > $cacheFileLifeTime) || !file_exists($cachefile_json_ld) || true) {
		      if ( isset($_SERVER["HTTPS"]) ) {
			      $http = 'https://';
		      }
		      else {
			      $http = 'http://';
		      }
          $arSocialLinks = array();
          if (!empty($GLOBALS['nar_systemsettings']['SITE']['CONTACT_FACEBOOK'])) {
            $arSocialLinks[] = $GLOBALS['nar_systemsettings']['SITE']['CONTACT_FACEBOOK'];
          }
          if (!empty($GLOBALS['nar_systemsettings']['SITE']['CONTACT_TWITTER'])) {
            $arSocialLinks[] = $GLOBALS['nar_systemsettings']['SITE']['CONTACT_GOOGLE'];
          }
          if (!empty($GLOBALS['nar_systemsettings']['SITE']['CONTACT_TWITTER'])) {
            $arSocialLinks[] = $GLOBALS['nar_systemsettings']['SITE']['CONTACT_TWITTER'];
          }
          if (!empty($GLOBALS['nar_systemsettings']['SITE']['CONTACT_YOUTUBE'])) {
            $arSocialLinks[] = $GLOBALS['nar_systemsettings']['SITE']['CONTACT_YOUTUBE'];
          }
          $arr = array(
            "@context" => "http://schema.org",
            "@type" => "Organization",
            "url" => $http . $_SERVER["HTTP_HOST"],
            "logo" => $this->tpl_image_data_json_ld(substr($this->tpl_uri_resource("images/logo.png"), 4)),
            "contactPoint" => array(
              "@type" => "ContactPoint",
              "telephone" => $GLOBALS['nar_systemsettings']['SITE']['CONTACT_PHONE'],
              "contactType" => "customer service"
            ),
            "name" => $GLOBALS['nar_systemsettings']['SITE']['SITENAME']
          );
          if (!empty($arSocialLinks)) {
            $arr["sameAs"] = $arSocialLinks;
          }
		      $str_organization_json_ld = json_encode($arr);
		      $tpl_organization_json_ld = new Template("tpl/".$GLOBALS['s_lang']."/cache.organization.json.ld.htm");
		      $tpl_organization_json_ld->addvar('str_organization_json_ld',$str_organization_json_ld);

		      $result->tpl_text = $tpl_organization_json_ld->process(true);

		      file_put_contents($cachefile_json_ld, $result->tpl_text);
        } else {
          $result->tpl_text = file_get_contents($cachefile_json_ld);
        }
	      $result->vars = $this->vars;

	      return $result->process(true);
      }
      
      function tpl_option($params) {
        list($type, $option) = explode(",", $params);
        return $GLOBALS["nar_systemsettings"][$type][$option];
      }
      
      function tpl_option_is_set($params) {
        list($type, $option) = explode(",", $params);
        return (!empty($GLOBALS["nar_systemsettings"][$type][$option]) ? 1 : 0);
      }

      function tpl_kat_ariadne_dynamic_json_ld($params) {
	      global $db, $langval;
	      $ar_params = explode(",", $params);
	      $id_kat = (int)$this->parseTemplateString($ar_params[0]);
	      $ariadneTemplate = $this->parseTemplateString($ar_params[1]);
	      $currentPageTitle = $this->parseTemplateString($ar_params[2]);
	      if (empty($ariadneTemplate)) {
		      $ariadneTemplate = "default";
	      }
	      if (empty($currentPageTitle)) {
		      $currentPageTitle = false;
	      } else {
		      $params .= ",".$_SERVER["REQUEST_URI"];
	      }
	      $parameterHash = md5($params);
	      $result = new Template("tpl/de/empty.htm");
	      $result->isTemplateRecursiveParsable = true;
	      $result->isTemplateCached = true;
	      $cachedir = $GLOBALS['ab_path']."cache/ariadne/kat";
	      if (!is_dir($cachedir)) {
		      mkdir($cachedir, 0777, true);
	      }
	      $cachefile_json_ld = $cachedir."/".$GLOBALS['s_lang'].".".$ariadneTemplate.".".$id_kat.".".$parameterHash.".json.ld.htm";
	      $str_json_ld = null;
	      $cacheFileLifeTime = $GLOBALS['nar_systemsettings']['CACHE']['LIFETIME_CATEGORY'];
	      $modifyTime = @filemtime($cachefile_json_ld);
	      $diff = ((time() - $modifyTime) / 60);
	      if (($diff > $cacheFileLifeTime) || !file_exists($cachefile_json_ld)) {
		      $tplAriane_json_ld = new Template("tpl/".$GLOBALS['s_lang']."/cache.kat_ariadne.".$ariadneTemplate.".json.ld.htm");
		      if ($id_kat > 0) {
			      $arKat = $db->fetch1("SELECT * FROM `kat` WHERE ID_KAT=".$id_kat);
			      if (!is_array($arKat)) {
			          return "";
                  }
			      // Update cache file
			      $arPath = $db->fetch_table("SELECT el.*, s.T1, s.V1, s.V2 FROM `kat` el
                        LEFT JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=el.ID_KAT
                          AND s.BF_LANG=if(el.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(el.BF_LANG_KAT+0.5)/log(2)))
                        WHERE el.LFT<=".$arKat["LFT"]." AND el.RGT>".$arKat["LFT"]." AND el.ROOT=".$arKat["ROOT"]." AND el.LFT>1
                        GROUP BY el.ID_KAT ORDER BY el.LFT");
			      if ($currentPageTitle !== false) {
				      $arPath[] = array("HREF" => $_SERVER["REQUEST_URI"], "V1" => $currentPageTitle);
			      }
			      $str_json_ld = $this->add_bread_crumbs_json_ld( $arPath, $ar_params[1] );
			      $tplAriane_json_ld->addvar('str_json_ld',$str_json_ld);
		      } else {
			      // Update cache file (root category)
			      if ($currentPageTitle !== false) {
				      $arPath = array(
					      array("HREF" => $_SERVER["REQUEST_URI"], "V1" => $currentPageTitle)
				      );
				      $tplAriane_json_ld->addvar('str_json_ld',$str_json_ld);
			      }
		      }
		      $result->tpl_text = $tplAriane_json_ld->process(true);

		      file_put_contents($cachefile_json_ld, $result->tpl_text);

	      } else {
		      $result->tpl_text = file_get_contents($cachefile_json_ld);
	      }
	      $result->vars = $this->vars;

	      return $result->process(true);
      }

      function tpl_kat_ariadne_dynamic($params) {
          global $db, $langval;
          $ar_params = explode(",", $params);
          $id_kat = (int)$this->parseTemplateString($ar_params[0]);
          $ariadneTemplate = $this->parseTemplateString($ar_params[1]);
          $currentPageTitle = $this->parseTemplateString($ar_params[2]);
          $cityName = $this->parseTemplateString($ar_params[3]);
          if (empty($ariadneTemplate)) {
              $ariadneTemplate = "default";
          }
          if (empty($currentPageTitle)) {
              $currentPageTitle = false;
          } else {
              $params .= ",".$_SERVER["REQUEST_URI"];
          }
          $parameterHash = md5($params); 
          $result = new Template("tpl/de/empty.htm");
          $result->isTemplateRecursiveParsable = true;
          $result->isTemplateCached = true;
          $cachedir = $GLOBALS['ab_path']."cache/ariadne/kat";
          if (!is_dir($cachedir)) {
              mkdir($cachedir, 0777, true);
          }
          $cachefile = $cachedir."/".$GLOBALS['s_lang'].".".$ariadneTemplate.".".$id_kat.".".$parameterHash.".htm";
          $cacheFileLifeTime = $GLOBALS['nar_systemsettings']['CACHE']['LIFETIME_CATEGORY'];
          $modifyTime = @filemtime($cachefile);
          $diff = ((time() - $modifyTime) / 60);
          if (($diff > $cacheFileLifeTime) || !file_exists($cachefile)) {
              $tplAriane = new Template("tpl/".$GLOBALS['s_lang']."/cache.kat_ariadne.".$ariadneTemplate.".htm");
              if ($id_kat > 0) {
                  $arKat = $db->fetch1("SELECT * FROM `kat` WHERE ID_KAT=".$id_kat);
			      if (!is_array($arKat)) {
			          return "";
                  }
                  // Update cache file
                  $arPath = $db->fetch_table("SELECT el.*, s.T1, s.V1, s.V2 FROM `kat` el
                        LEFT JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=el.ID_KAT
                          AND s.BF_LANG=if(el.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(el.BF_LANG_KAT+0.5)/log(2)))
                        WHERE el.LFT<=".$arKat["LFT"]." AND el.RGT>".$arKat["LFT"]." AND el.ROOT=".$arKat["ROOT"]." AND el.LFT>1
                        GROUP BY el.ID_KAT ORDER BY el.LFT");
                  if ($currentPageTitle !== false) {
                      $arPath[] = array("HREF" => $_SERVER["REQUEST_URI"], "V1" => $currentPageTitle);
                  }
                  if ( !empty($cityName) ) {
	                  $tplAriane->addvar("city_name",$cityName);
                  }
                  $tplAriane->addlist("liste", $arPath, "tpl/".$GLOBALS['s_lang']."/cache.kat_ariadne.".$ariadneTemplate.".row.htm");
              } else {
                  // Update cache file (root category)
                  if ($currentPageTitle !== false) {
                      $arPath = array(
                          array("HREF" => $_SERVER["REQUEST_URI"], "V1" => $currentPageTitle)
                      );
                      $tplAriane->addlist("liste", $arPath, "tpl/".$GLOBALS['s_lang']."/cache.kat_ariadne.".$ariadneTemplate.".row.htm");
                  }
              }
              $result->tpl_text = $tplAriane->process(true);

              file_put_contents($cachefile, $result->tpl_text);

          } else {
              $result->tpl_text = file_get_contents($cachefile);
          }
          $result->vars = $this->vars;
          
          return $result->process(true);
      }

      function tpl_favorite_link($params) {
          $arParams = explode(",", $params);
	      if (count($arParams) <= 1) {
	          // Current page
              $url = implode(",",$GLOBALS['ar_params']);
		      $title = $this->parseTemplateString(array_pop($arParams));
              if (empty($title)) {
                  $title = $GLOBALS["tpl_main"]->vars["pagetitle"];
              }
              if (empty($title)) {
                  $title = $GLOBALS["tpl_main"]->vars["newstitle"];
              }
              if (empty($title)) {
                  $title = $GLOBALS["nar_tplglobals"]['curpagename'];
              }
	      } else {
	          // Specified page
		      $title = $this->parseTemplateString(array_pop($arParams));
		      $url = implode(",",$arParams);
          }

	      $tplFavorite = new Template("tpl/".$GLOBALS['s_lang']."/favorite_link.htm");
	      $tplFavorite->addvar("url",$url);
	      $tplFavorite->addvar("pagetitle",$title);
	      return $tplFavorite->process(true);
      }
      
      function tpl_color_hex($params) {
          $ar_params = explode(",", $params);
          $color = $this->getval($ar_params[0]);
          if (preg_match("/^#([0-9a-f]){1}([0-9a-f]){1}([0-9a-f]){1}$/i", $color, $ar_matches)) {
              // Single digit colors (e.g. #f00 / #fff), convert to #rrggbb
              return "#".$ar_matches[1].$ar_matches[1].$ar_matches[2].$ar_matches[2].$ar_matches[3].$ar_matches[3];
          } else {
              return $color;
          }
      }

      /* ===============================================================================================================
       * PLUGIN FUNCTIONS
       * ===============================================================================================================
       */

      function tpl_plugin($param) {
          $arParams = explode(",", $param);
          $pluginName = array_shift($arParams);
          $pluginAction = array_shift($arParams);
          $loadtimeEvent = false;
          if (array_key_exists('ebizRecordLoadtimeTemplate', $_COOKIE)) {
              $loadtimeEvent = Tools_LoadtimeStatistic::getInstance()->createEvent("PHP", "Plugin ".$pluginName."/".$pluginAction);
          }
          // Parse parameters
          foreach ($arParams as $paramIndex => $paramValue) {
              $arParams[$paramIndex] = $this->process_text($paramValue);
          }
          // Trigger event
          $eventParams = new Api_Entities_EventParamContainer(array(
              "action" => $pluginAction, "params" => $arParams, "template" => $this, 
              "result" => (defined('DEVELOPMENT') ? "Plugin function not found '".$pluginName."::".$pluginAction."'" : "")
          ));
          Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::TEMPLATE_PLUGIN_FUNCTION, $eventParams, $pluginName);
          if ($loadtimeEvent !== false) {
              $loadtimeEvent->finish();
          }
          // Return result
          return $eventParams->getParam("result");
      }
      
      /* ===============================================================================================================
       * URL FUNCTIONS
       * ===============================================================================================================
       */

      function tpl_uri_is_active($param) {
          global $nar_tplglobals;
          $parameter = explode(",", $param);
          $valueInactive = array_pop($parameter);
          $valueActive = array_pop($parameter);
          $page = $parameter['0'];
          if(count($parameter) > 1) {
              $checkFullUrl = true;
          } else {
              $checkFullUrl = false;
          }
          $url = implode(",", $parameter);

          return ((($nar_tplglobals['curpage'] == $page) && (!$checkFullUrl || (strpos($_SERVER['REQUEST_URI'], $url) !== FALSE))) ? $valueActive : $valueInactive);
      }

      /**
       * @param string              $param
       * @param bool                $isAbsolute
       * @param null                $isSSL
       * @param Api_Entities_URL    $urlLink
       * @return string
       */
      function tpl_uri_action($param, $isAbsolute = false, $isSSL = null, Api_Entities_URL &$urlLink = null) {
          global $ar_nav, $nar_ident2nav, $nar_systemsettings;

          if ($isSSL === null) {
              $isSSL = !empty($_SERVER['HTTPS']);
          }
          // Parse url parameters
          if ($param instanceof Api_Entities_URL) {
              $ident = $param->getPageIdent();
              $uriParametersParsed = $param->getPageParameters();
              $parameterOptional = $param->getPageParametersOptional();
              if ($urlLink === null) {
                  $urlLink = $param;
              }
          } else {
              list($param, $paramOptional) = explode('|', $param);
              $parameter = explode(',', $param);
              $parameterOptional = array();
              if ($paramOptional !== null) {
                  $parameterOptionalRaw = explode(',', $paramOptional);
                  foreach($parameterOptionalRaw as $vardef) {
                      $vardef = trim($vardef);
                      if ($vardef == '*') {
                          foreach ($this->vars as $k=>$v) {
                              if (!is_object($v) && !array_key_exists($k, $parameterOptional)) {
                                  $parameterOptional[$k] = $v;
                              }
                          }
                      } elseif (($p = strpos($vardef, '*')) == strlen($vardef)-1) {
                          $pattern = substr($vardef, 0, strlen($vardef)-1);
                          foreach ($this->vars as $k=>$v) {
                              if (strpos($k, $pattern) === 0) {
                                  $parameterOptional[$k] = $v;
                              }
                          }
                      } elseif (FALSE!==($p = strpos($vardef, '='))) {
                          $parameterOptional[trim(substr($vardef, 0, $p))] = $this->parseTemplateString( substr($vardef, $p+1) );
                      } else {
                          $parameterOptional[trim($vardef)] = $this->vars[$vardef];
                      }
                  }
              }
              $ident = array_shift($parameter);
              $ident = $this->parseTemplateString($ident);
              $uriParameters = $parameter;
              $uriParametersParsed = $parameter;
    
              foreach ($uriParameters as $key => $uriParameter) {
                  $uriParameters[$key] = (trim($uriParameter));
                  $uriParametersParsed[$key] = $this->parseTemplateString($uriParameter);
              }
          }

          if ($urlLink === null) {
              $urlHost = rtrim(str_replace("http://", "", $GLOBALS["nar_systemsettings"]['SITE']['SITEURL']), "/");
              $urlLink = Api_Entities_URL::createFromPage( $ident, false, false, $uriParametersParsed, $parameterOptional );
              $urlLink->setHost($urlHost);
              $urlLink->setSecure($isSSL);
	          //$urlLink->setPageParametersOptional($parameterOptional);
          } else {
              $urlLink->setPageIdent($ident);
              $urlLink->setPageParameters($uriParametersParsed);
              $urlLink->setPageParametersOptional($parameterOptional);
          }
          // Trigger API-Event allowing to manipulate the parameters before generating the URL
          $urlParams = new Api_Entities_EventParamContainer(array(
              "url" => $urlLink,
              "template" => $this
          ));
          Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::URL_GENERATE, $urlParams);
          $uriParametersParsed = $urlLink->getPageParameters();
          $parameterOptional = $urlLink->getPageParametersOptional();
          
          $id_nav = $nar_ident2nav[$ident];
          if ($id_nav != NULL) {
              $ident_path = (!empty($ar_nav[$id_nav]["ident_path"]) ? explode("/", $ar_nav[$id_nav]["ident_path"]) : array());
              $url_path = "/" . (!empty($ident_path) ? $ident_path[0] . "/" : "");
              $url_ident = ($ar_nav[$id_nav]["ALIAS"] ? $ar_nav[$id_nav]["ALIAS"] : $ar_nav[$id_nav]["IDENT"]);
              $urlLink->setPageIdentPath($url_path);
              
              if (is_array($GLOBALS['ar_nav_urls_by_id'][$id_nav])) {
                  require_once $GLOBALS['ab_path']."sys/lib.nav.url.php";
                  $navUrlMan = NavUrlManagement::getInstance($GLOBALS["db"]);
                  $href = $navUrlMan->generateUrlByNav($id_nav, $uriParametersParsed, $parameterOptional, $this);
                  if ($href !== false) {
                      $uri = $this->tpl_uri_baseurl($href, $isAbsolute, $isSSL, 1, $urlLink);
                      return $uri;
                  }
              }
              
              $url_ident_file = $url_ident . ((count($uriParametersParsed) > 0) ? (',' . implode(',', $uriParametersParsed)) : '') . '.htm';
              if ($url_path == '/') {
                  if (in_array($url_ident, array("index"))) {
                      $uri = $this->tpl_uri_baseurl($url_ident_file, $isAbsolute, $isSSL, 1, $urlLink);
                  } else if ((isset($uriParametersParsed) && (count($uriParametersParsed) > 0)) || $ar_nav[$id_nav]["PARENT"]) {
                      $uri = $this->tpl_uri_baseurl($url_ident . '/' . $url_ident_file, $isAbsolute, $isSSL, 1, $urlLink);
                  } else {
                      $uri = $this->tpl_uri_baseurl($url_ident . '/', $isAbsolute, $isSSL, 1, $urlLink);
                  }
              } else {
                  $uri = $this->tpl_uri_baseurl($url_path . $url_ident_file, $isAbsolute, $isSSL, 1, $urlLink);
              }
              return $uri;
          }
      }

      /**
       * @param string              $param
       * @param bool                $useSSL
       * @param Api_Entities_URL    $urlLink
       * @return string
       */
      function tpl_uri_action_full($param, $useSSL = null, Api_Entities_URL &$urlLink = null) {
          $urlHost = rtrim(str_replace("http://", "", $GLOBALS["nar_systemsettings"]['SITE']['SITEURL']), "/");
          if ($urlLink === null) {
              $urlLink = Api_Entities_URL::createFromURL($urlHost);
          } else {
              $urlLink->setHost($urlHost);
          }
          return $this->tpl_uri_action($param, true, $useSSL, $urlLink);
      }

      /**
       * @param string              $param
       * @param bool                $isAbsolute
       * @param bool|null           $isSSL
       * @param Api_Entities_URL    $urlLink
       * @return string
       */
      function tpl_uri_resource($param, $isAbsolute = false, $isSSL = null, Api_Entities_URL &$urlLink = null) {
          global $ab_path, $s_lang;
          if ($isSSL === null) {
              $isSSL = !empty($_SERVER['HTTPS']);
          }
          $uri = $this->parseTemplateString($param);
          if (preg_match("/^.+\.js$/i", $uri)) {
              // Javascript-Datei! Translation-Tool informieren.
              require_once $ab_path."sys/lib.translation_tool.php";
              TranslationTool::logAdditionalTranslationFile($ab_path."cache/design/resources/".$s_lang."/".ltrim($uri, "/"));
          }

          if (substr($uri, 0, 1) == '/') {
              $uri = substr($uri, 1);
          }

          $cacheTemplate = new CacheTemplate();
          if($GLOBALS["nar_systemsettings"]['CACHE']['TEMPLATE_AUTO_REFRESH'] == 1) {
              if ($cacheTemplate->isFileDirty('resources/' . $s_lang . '/' . $uri)) {
                  $cacheTemplate->cacheFile('resources/' . $s_lang . '/' . $uri);
              }
          }

          $uriFull = rtrim($GLOBALS["nar_systemsettings"]['SITE']['BASE_URL'], "/") . "/" . 'cache/design/resources/' . $s_lang . '/' . $uri;
          if ($urlLink === null) {
              $urlLink = Api_Entities_URL::createFromURL( false, $uriFull, $isSSL );
          } else {
              $urlLink->setPath( $uriFull );
              $urlLink->setSecure( $isSSL );
          }

          Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::URL_OUTPUT, $urlLink);

          return $urlLink->getRaw($isAbsolute);
      }

      /**
       * @param string              $param
       * @param bool                $useSSL
       * @param Api_Entities_URL    $urlLink
       * @return string
       */
      function tpl_uri_resource_full($param, $useSSL = null, Api_Entities_URL &$urlLink = null) {
          $urlHost = rtrim(str_replace("http://", "", $GLOBALS["nar_systemsettings"]['SITE']['SITEURL']), "/");
          if ($urlLink === null) {
              $urlLink = Api_Entities_URL::createFromURL($urlHost);
          } else {
              $urlLink->setHost($urlHost);
          }
          return $this->tpl_uri_resource($param, true, $useSSL, $urlLink);
      }

      /**
       * @param string              $param
       * @param bool                $isAbsolute
       * @param bool|null           $isSSL
       * @param int                 $useSSL
       * @param Api_Entities_URL    $urlLink
       * @return string
       */
      function tpl_uri_baseurl($param, $isAbsolute = false, $isSSL = null, $useSSL = 1, Api_Entities_URL &$urlLink = null) {
          if ($isSSL === null) {
              $isSSL = !empty($_SERVER['HTTPS']);
          }
          $uri = $this->parseTemplateString($param);
          if ($GLOBALS["nar_systemsettings"]["SITE"]["USE_SSL"] && !$isAbsolute) {
              // Result is not absolute, ssl can be prepended
              if ($GLOBALS["nar_systemsettings"]["SITE"]["USE_SSL_GLOBAL"]) {
                  $useSSL = 2;
              }
              switch ($useSSL) {
                  case 2:
                      // SSL immer aktivieren
                      $isSSL = true;
                      break;
                  case 0:
                      // SSL immer deaktivieren
                      $isSSL = false;
                      break;
                  case 1:
                  default:
                      // Aktuelle einstellung beibehalten / relativ verlinken
                      break;
              }
          }
          if ($uri == "") {
              $uri = str_replace($GLOBALS["nar_systemsettings"]['SITE']['BASE_URL'], "/", $_SERVER["REQUEST_URI"]);
          }
          $uriFull = rtrim($GLOBALS["nar_systemsettings"]['SITE']['BASE_URL'], "/") . "/" . ltrim($uri, "/");
          if ($urlLink === null) {
              $urlHost = rtrim(str_replace("http://", "", $GLOBALS["nar_systemsettings"]['SITE']['SITEURL']), "/");
              $urlLink = Api_Entities_URL::createFromURL( $urlHost, $uriFull, $isSSL );
          } else {
              $urlLink->setPath( $uriFull );
              $urlLink->setSecure( $isSSL );
          }

          Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::URL_OUTPUT, $urlLink);

          return $urlLink->getRaw($isAbsolute);
      }

      /**
       * @param string              $param
       * @param bool                $isAbsolute
       * @param bool|null           $isSSL
       * @param int                 $useSSL
       * @param Api_Entities_URL    $urlLink
       * @return string
       */
      function tpl_uri_baseurl_ssl($param, $isAbsolute = false, $isSSL = null, $useSSL = 1, Api_Entities_URL &$urlLink = null) {
          if ($isSSL === null) {
              $isSSL = !empty($_SERVER['HTTPS']);
          }
          if (!$isAbsolute) {
              if ($GLOBALS["nar_systemsettings"]["SITE"]["USE_SSL"]) {
                  $useSSL = 2;
              }
          }
          return $this->tpl_uri_baseurl($param, $isAbsolute, $isSSL, $useSSL, $urlLink);
      }

      /**
       * @param string              $param
       * @param bool                $useSSL
       * @param Api_Entities_URL    $urlLink
       * @return string
       */
      function tpl_uri_baseurl_full($param, $useSSL = null, Api_Entities_URL &$urlLink = null) {
          $urlHost = rtrim(str_replace("http://", "", $GLOBALS["nar_systemsettings"]['SITE']['SITEURL']), "/");
          if ($urlLink === null) {
              $urlLink = Api_Entities_URL::createFromURL($urlHost);
          } else {
              $urlLink->setHost($urlHost);
          }
          return $this->tpl_uri_baseurl($param, true, $useSSL, $urlLink);
      }

      /* ===============================================================================================================
       * RESOURCE FUNCTIONS
       * ===============================================================================================================
       */

      function tpl_javascript_google_maps($param) {
          $arLibs = explode(",", $param);
          Template_Helper_ResourceLoader::requireGoogleMaps($arLibs);
          return "";
      }

      function tpl_javascript_require_remote($param) {
          list($source, $ident, $attributes) = explode(",", $param);
          $source = $this->parseTemplateString($source);
          Template_Helper_ResourceLoader::requireJavascript($source, $attributes, (!empty($ident) ? $ident : null));
          return "";
      }

      function tpl_javascript_require_resource($param) {
          list($source, $ident, $attributes) = explode(",", $param);
          $source = $this->tpl_uri_resource($source);
          Template_Helper_ResourceLoader::requireJavascript($source, $attributes, (!empty($ident) ? $ident : null));
          return "";
      }

      function tpl_javascript_require_base($param) {
          list($source, $ident, $attributes) = explode(",", $param);
          $source = $this->tpl_uri_baseurl($source);
          Template_Helper_ResourceLoader::requireJavascript($source, $attributes, (!empty($ident) ? $ident : null));
          return "";
      }

      /* ===============================================================================================================
       * FLOW FUNCTIONS
       * ===============================================================================================================
       */
      
      function tpl_limit_local($param) {
          list($ident, $amount) = explode(",", $param);
          $varName = "_ebiz_flow_limit_local_".$ident;
          $amount = ($amount > 0 ? (int)$amount : 1);
          $amountCurrent = (array_key_exists($varName, $this->vars) ? (int)$this->vars[$varName] : 0);
          if ($amount > $amountCurrent) {
              $this->vars[$varName] = $amountCurrent + 1;
              return true;
          } else {
              return false;
          }
      }
      
      function tpl_limit_global($param) {
          list($ident, $amount) = explode(",", $param);
          if (!array_key_exists("ebiz_flow_limit_global", $GLOBALS)) {
              $GLOBALS["ebiz_flow_limit_global"] = array();
          }
          $varName = $ident;
          $amount = ($amount > 0 ? (int)$amount : 1);
          $amountCurrent = (array_key_exists($varName, $GLOBALS["ebiz_flow_limit_global"]) ? (int)$GLOBALS["ebiz_flow_limit_global"][$varName] : 0);
          if ($amount > $amountCurrent) {
              $GLOBALS["ebiz_flow_limit_global"][$varName] = $amountCurrent + 1;
              return 1;
          } else {
              return 0;
          }
      }

    /**
     * Allow automatic conversion to string
     */
    function __toString() {
      return $this->process();
    }

  } // class Template

//==============================================================================

class FrameTemplate extends Template
{
  function FrameTemplate($str_templatefn, $str_frame, $str_table='')
  {
    if (!is_array ($ar_layoutdirs))
      $ar_layoutdirs = array ($ar_layoutdirs);
    $this->ar_dirs = $ar_layoutdirs;
    $fn = $str_templatefn.'_'.$str_frame.'.htm';
    if (!$str_frame || !file_exists($fn))
    {
      $fn = $str_templatefn. '.htm';
    }
    $this->Template($fn, $str_table);
  } // constructor FrameTemplate
} // class FrameTemplate

#function tpl_init()
#{
 # global $assoc_assoc_loaded_templates;
 # if (SESSION)
 #   session_register('assoc_loaded_templates');
 # if (!$assoc_assoc_loaded_templates)
 #   $assoc_loaded_templates = array ();
#}
#$ar_initfn[] = 'tpl_init';

function parse_mail($string,$ar=array())
{
	global $nar_systemsettings;
	$tpl_tmp = new Template("tpl/de/empty.htm");
	$tpl_tmp->tpl_text = $string;
	$tpl_tmp->addvars($ar);
	$tpl_tmp->addvar('CURRENCY_DEFAULT', $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
	return $tpl_tmp->process();
}

function secure_question(&$ar)
{
  global $db, $langval;
  $richtig = trim(strtolower($db->fetch_atom("
    select s.V2 from question q
    LEFT JOIN string_app s ON s.S_TABLE='question' AND s.FK = q.ID_QUESTION
    where
     s.BF_LANG=if(q.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(q.BF_LANG_APP+0.5)/log(2)))
     and FK=".(int)$ar['ID_Q'])));

  $x = array('ß', 'ä', 'ö', 'ü');
  $new = array('ss', 'ae', 'oe', 'ue');
  $antwort = trim(strtolower(str_replace($x, $new, trim($ar['ANSWER']))));
  return ($antwort == $richtig ? true : false);
} // question()

?>

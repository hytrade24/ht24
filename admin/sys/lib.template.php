<?php
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 7.4.0
 */

require_once $ab_path. 'sys/lib.cache.template.php';


function addnoparse($str)
{
  $str = str_replace('{', '{~}', $str);
  return $str;
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
    var $inheritParentVariables = true;

    function LoadText($template_fn, $table='')
    {
        global $assoc_loaded_templates, $ab_path, $nar_systemsettings;

        if(strpos($template_fn, '../') === 0 || strpos($template_fn, $ab_path) === 0) {
            if (strpos($template_fn, $ab_path) === 0) {
                $tmpTemplateFn = str_replace($ab_path, "", $template_fn);
            } else {
                $tmpTemplateFn = substr($template_fn, 3);
            }

            $templateType = preg_match("/^([-_a-zA-Z0-9]+)\//", $tmpTemplateFn, $matches);
            if ($matches['1'] == 'tpl' || $matches['1'] == 'skin' || $matches['1'] == 'module' || $matches['1'] == 'mail') {
                $cacheTemplate = new CacheTemplate();
                if($nar_systemsettings['CACHE']['TEMPLATE_AUTO_REFRESH'] == 1) {
                    if($cacheTemplate->isFileDirty($tmpTemplateFn)) {
                        $cacheTemplate->cacheFile($tmpTemplateFn);
                    }
                }

                $template_fn = '../cache/design/' . $tmpTemplateFn;
            }
        }

      $this->filename = $template_fn;
      if (!$assoc_loaded_templates[$template_fn])
	  $assoc_loaded_templates[$template_fn] =  (file_exists($template_fn)  ? implode('', file($template_fn))
          : "<br><i>no such file: $template_fn</i><br />"
        );
      $this->tpl_text = $assoc_loaded_templates[$template_fn];
      $this->table = $table;
	 //echo  ht(dump($assoc_loaded_templates));
    }

    function Template($template_fn, $table='')
    {
      global $assoc_loaded_templates;

	  $this->LoadText($template_fn, $table);
      $this->vars['sitetitle'] = $GLOBALS['sitetitle'];
      if (is_array ($ar = &$GLOBALS['nar_tplglobals']))
        $this->addvars($ar);
	 //echo  $template_fn."<br>";
    } // constructor Template

    function addvars($array, $s_prefix=false)
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

    function addlist($name, $array, $str_subtpl, $s_callback=NULL)
    {
      $ar_liste = array ();
      foreach($array as $i=>$row)
      {
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
        $tpl_tmp->addvars($row);
        $tpl_tmp->addvar('i', $i);
        $tpl_tmp->addvar('even', 1-((int)$i&1));
        $ar_liste[] = $tpl_tmp;
      }
      $this->addvar($name, $ar_liste);
    } // function Template->addlist

    function process_value($value)
    {
      // Wert ermitteln, rekursiv wenn noetig
      if (is_array ($value))
      {
        $tmp = array ();
        foreach($value as $v)
          $tmp[] = Template::process_value($v);
        return implode('', $tmp);
      }
      elseif (is_object($value))
        if (!strcasecmp('Template', ($class=get_class($value)))
          # php4: get_class = lower case; php5: tatsaechliche Schreibung!
          || is_subclass_of($value, 'Template'))
        {
          // inherit variables
          foreach ($this->vars as $k=>$v) {
              if (!is_object($v) && !is_array($v)
                  && /**/
                  !array_key_exists($k, $value->vars) /*/!isset($value->vars[$k])/**/
              ) {
                  $value->addvar($k, $v);
              }
          }
          // process
          if ($tmp = $this->vars['curframe'])
            $value->addvar('curframe', $tmp);
          return $value->process(false);
        }
        else
#          myerr("Template: $name is a non-template object (class='$class')");
          return "Object{$class}";
      else
        return $value;
    } // Template::process_value

    function getval($varname, $to_num = false)
    {
      // 2005-07-13: Funktionen schachteln
      if (preg_match('/\(/', $varname))
        return $this->process_text('{'. $varname. '}');
      // Wert einer Variable ermitteln; bei Array und Objekt: Ergebnis speichern
      $value = $this->vars[$varname];
      if (is_array ($value) || is_object($value) || '='==substr($value,0,1)) {
          $value = $this->vars[$varname] = Template::process_value($value);
      }
      $is_num = preg_match('/^('. PREG_FLT. ')$/', trim($value));
      if ($to_num && !$is_num)
        $value = remnoparse($value);

      return (!$to_num || $is_num ? $value : strlen($value));
    } // Template::getval

  function process_text($text, $dropempty = true) {

  #static $indent = '';echo $indent, $this->filename, "<br />\n";$indent .= '&nbsp;&nbsp;';mytime();
            // benoetigte Permissions abfragen --> als Vars definieren
            //$text = preg_replace('/{\s*loadperm\s*\((.*)\)\s*\}/Ue', '$this->tpl_loadperm("$1")', $text);
            $text = preg_replace_callback('/{\s*loadperm\s*\((.*)\)\s*\}/U', function($match) {
                return $this->tpl_loadperm($match[1]);
            }, $text);

            #var_dump($text);

            // {if (condition)} ... [{else} ... ]{/if}; may be nested
            while (false !== ($p_ende = strpos($text, '{endif}'))) {
                // Parameter ermitteln
                $match = substr($text, 0, $p_ende + 7);
                $p_start = -4;
                while (false !== ($tmp = strpos($match, '{if ', $p_start + 4))) $p_start = $tmp;
                if (0 > $p_start) break;
                $match = substr($match, $p_start);
                if (false === ($p_if_close = strpos($match, '}'))) myerr("Template: syntax error at '$part'");
                $length = strlen($match);
                if (false !== ($p_else = strpos($match, '{else}'))) {
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
                    return $this->getval($match[0], true);
                }, $cond);
                // wahr oder falsch?
  #echo "$cond\n";
                  if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $cond)) {
                      $if = strspn($cond, '-:0') != strlen($cond); // date/datetime
                  } else {
                      try {
                          $success = eval("\$if=$cond; return true;"); // sonstige
                          if ($success !== true) {
                              $error = error_get_last();
                              eventlog("error", "Fehler beim Auflösen des if-blocks '".$cond."' in Template '".$this->filename."'", $error["message"]);
                          }
                      } catch (Exception $e) {
                          eventlog("error", "Fehler beim Auflösen des if-blocks '".$cond."' in Template '".$this->filename."'");
                      }
                  }
  #echo "$strcond: $cond : ", ($if ? 'true':'false'), '<br />';
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
            $text = preg_replace_callback('/\{\=((' . PREG_FLT . '|\b\w+\b)(\s*(-|\+|&|\|)\s*(' . PREG_FLT . '|\b\w+\b))*)\}/', function($match) {
                return $this->tpl_calc($match[1]);
            }, $text);
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


    function process($dropempty=true)
    {
        global $nar_tplglobals;
#echo ht(dump($nar_tplglobals));
        if (is_array($nar_tplglobals))
            $this->addvars($nar_tplglobals);
#echo ht(dump($this->filename)), ht(dump($this->vars)), '<hr />';
        $text = $this->process_text($this->tpl_text, $dropempty);

        if ($this->isTemplateRecursiveParsable) {
            $text = str_replace(array('^', '°'), array('{', '}'), $text);
        }
        if ($this->isTemplateCached) {
            $text = $this->process_text($text);
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
        $y_z = $y_now + (false!==strpos($range, '>') ? 5 : 0);
        $y_a = $y_now - (false!==strpos($range, '<') ? 5 : 0);
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
    function tpl_htm_raw($param) { return htmlentities($this->getval($param)); }
    function tpl_text($param) { return addnoparse(nl2br(stdHtmlentities($this->getval($param)))); }
    function tpl_noparse($param) { return addnoparse(stdHtmlentities($this->getval($param))); }	//Definiert einen nicht zu parsenden Bereich
    function tpl_url($param) { return addnoparse(rawurlencode($this->getval($param))); }
    function tpl_urllabel($param) { return addnoparse(chtrans($this->getval($param))); }
    function tpl_js($param) { return addnoparse(js_quote($this->getval($param))); }
	function tpl_int($param) { return addnoparse((int)$this->getval($param)); }
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
      function tpl_shorten($param)
      {
          list($text, $length) = explode(',', $param);
          $text = $this->parseTemplateString($text);
          $length = (int)$this->parseTemplateString($length
          );
          if ($length <= 0) {
              $length = 100;
          }
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

    function subtpl_scanparams(&$subtpl_params, &$ar)
    // aux function for tpl_subtpl and tpl_content
    {
      foreach($subtpl_params as $vardef)
      {
        $vardef = trim($vardef);
        if ('*'==$vardef)
        {
          foreach ($this->vars as $k=>$v)
            if (!is_object($v) && !array_key_exists($k, $ar))
              $ar[$k] = $v;
        }
        elseif (false!==($p = strpos($vardef, '=')))
          $ar[trim(substr($vardef, 0, $p))] = substr($vardef, $p+1);
        else
          $ar[trim($vardef)] = $this->vars[$k];
      }
    }

    function tpl_subtpl($param) // filename, params
      /* params:
        *         alle Variablen aus aktuellem Template vererben, die weder Object noch Array sind
        varname   benannte Variable aus aktuellem Template vererben
        name=val  Variable mit konstantem Wert definieren
      */
    {
      $subtpl_params = explode(',', $param);
      $sub_tpl = new Template($filename = trim(array_shift($subtpl_params)));
#echo ht(dump($subtpl_params));
      if ($b_noscript = count($subtpl_params) && 'noscript'==trim($subtpl_params[0]))
        array_shift($subtpl_params);
#echo "sub_tpl ( $param ) : ", dump($b_noscript), '<br />';
      $this->subtpl_scanparams($subtpl_params, $sub_tpl->vars);

      // Skript da? ausfuehren!
      preg_match('%^tpl/\w+/(.*)\.htm$%', $filename, $ar_match);
      if (!$b_noscript && $ar_match[1] && file_exists($s_script = 'tpl/'. $ar_match[1]. '.php'))
      {
        extract ($GLOBALS);
        $tpl_content = &$sub_tpl;
        include $s_script;
      }

      return $sub_tpl->process();
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

	function tpl_content_page($param)
    {
      global $s_lang,$ab_path,$ar_nav,$id_nav,$ar_byname;
      if(!$param)
	  {
	    if($ar_nav[$id_nav]['FK_INFOSEITE'])
		  $file=$ar_nav[$id_nav]['FK_INFOSEITE'];
	  }
	  else
	  	require_once $ab_path.'cache/info.'.$s_lang.'.php';
	    $file = $ar_byname[$param];
#echo $param." :: ".$file."<hr />";
#echo ht(dump($ar_byname));
	  if($file)
	  {
	    $tpl_tmp = new Template($ab_path."cache/info/".$s_lang.".".$file.".htm");
		return $tpl_tmp->process();
		#return file_get_contents("cache/info/".$s_lang.".".$file.".htm");
	  }
	  else
	    return (!SILENCE ? '<div class="hinweis"><span class="error">Fehlender Content-Bereich &quot;'.$param.'&quot;</span></div>' : false);
#return $GLOBALS['db']->fetch_atom("select T1 from string_c where V1='".$param."'");
    }

    function tpl_calc($param)
    {
       /* $a = preg_replace('/('. PREG_FLT. ')|\b\w+\b/', '{$0}', $param);
        $b = preg_replace('/\{('. PREG_FLT. ')\}/', '$1', $a);
        $c = preg_replace('/{(\w+)}/e', '$this->getval("$1", false)', $b);
        var_dump($a,$b,$c);
*/
      $param = preg_replace_callback('/{(\w+)}/', function($match) {
                return $this->getval($match[1], false);
            },
            preg_replace('/\{('. PREG_FLT. ')\}/', '$1',
                preg_replace('/('. PREG_FLT. ')|\b\w+\b/', '{$0}', $param)
            )
        );
       // var_dump($param);
      do
        $param = preg_replace_callback('/^\s*('. PREG_FLT. ')\s*([-\/\^|&!*+%]|&~|\|\||&&|!=)\s*('. PREG_FLT. ')/', function($match) {
            return eval('return '.$match[1].$match[3].$match[4].';');
        }, $tmp = $param);
      /*
        $param = preg_replace(
          '/^\s*('. PREG_FLT. ')\s*([-\/\^|&!*+%]|&~|\|\||&&|!=)\s*('. PREG_FLT. ')/e',
          '$1$3$4', $tmp = $param);
      */
      while ($tmp!=$param);
      return $param;
    } // Template::tpl_calc

    function tpl_date_format($param)
    {
      list($param, $format) = explode(',', $param);
      $param = $this->getval(trim($param));
      $format = trim($format);
      if (preg_match('/^[0-9]+$/', $param)) {
          // Pure number
          return date($format, (int)$param);
      } else {
          $time = strtotime($param);
          return date($format, $time);
      }
    } // Template::tpl_date_format

      function tpl_totime($param)
      {
          list($param, $withtime) = explode(',', $param);
          $param = $this->getval(trim($param));
          $time = explode(" ",$param);
          $time_parts = explode(":",$time[1]);
          return $time_parts[0].":".$time_parts[1];
      }
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
        return false;
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
      return sprintf("%0.${digits}f", $this->tpl_calc($param));
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

      function tpl_checked_ex($param)
      {
  /**/
        list($p1, $p2, $s) = explode(',', $param);
        if (!$s) $s = 'checked ';
        $curval = $this->parseTemplateString($p1);
        $chkval = $this->parseTemplateString($p2);
        return ($curval==$chkval ? $s : '');
      }# {checked_ex({wert1},wert2)}
      
      function tpl_var_resolve($param) {
        list($varName, $varPrefix, $varSuffix, $varPrefixGlobal) = explode(',', $param);
        $varValue = $this->parseTemplateString($varName);
        do {
            $varPrefixMatch = (empty($varPrefix) || (strpos($varName, $varPrefix) === 0) ? true : false);
            $varSuffixMatch = (empty($varSuffix) || (strpos($varName, $varSuffix) === (strlen($varName) - strlen($varSuffix))) ? true : false);
            $varPrefixGlobal = (empty($varPrefixGlobal) ? "" : $varPrefixGlobal);
            if ($varPrefixMatch && $varSuffixMatch) {
                $varValue = $this->parseTemplateString("{" . $varPrefixGlobal . substr($varName, strlen($varPrefix), -strlen($varSuffix)) . "}");
            }
        } while ($varPrefixMatch && $varSuffixMatch);
        return $varValue;
      }

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
    <option '. ($selected==$k ? 'selected ':''). 'value="'
      . stdHtmlentities($k). '">'. stdHtmlentities($v). '</option>';
      return '<select name="'. $str_fieldname. '" id="'
        . strtolower($str_fieldname). '" '. $str_morehtm. '>'. implode('', $ar_tmp). '
  </select>';
    } // end method tpl_select

    function tpl_print_fk($param) // tabelle[, feldname[, label-spalte[,nulltext]]]
    {
      global $db, $langval;
      list($str_table, $str_fieldname, $str_labelcol, $str_nulltext)
        = explode(',', stripslashes($param));
      $str_table = trim($str_table);
      if (!($str_fieldname = trim($str_fieldname)))
        $str_fieldname = 'FK_'. strtoupper($str_table);
      $selected = $this->getval($str_fieldname);
      if (!($str_labelcol = trim($str_labelcol)))
        $str_labelcol = 'LABEL';
      if ($selected)
        $ret = $db->fetch_atom($db->lang_select($str_table, $str_labelcol). "
          where ". (preg_match('/^nav/', $str_table) ? 'ID_NAV':'ID_'. strtoupper($str_table)). "=". $selected);
      else
        $ret = false;
      return ($ret ? $ret : ($str_nulltext ? $str_nulltext : '---'));
    } // end method tpl_print_fk

    function tpl_lookup($param) // art[, spalte[, fl_admin=false[, morehtm[,ordercol[,nulltext]]]]]
    // wenn FL_ADMIN, wird VALUE statt LABEL angezeigt
    {
      list($str_art, $str_fieldname, $fl_admin, $str_morehtm, $str_ordercol, $s_nulltext) = explode(',', $param);
      return $this->tpl_select('lookup, '. ($str_fieldname ? $str_fieldname : 'LU_'. strtoupper($str_art))
        . ','. ($fl_admin ? 'VALUE' : ''). ",art='". $str_art. "',$str_ordercol,$str_morehtm,$s_nulltext");
    } // function tpl_lookup

    function tpl_print_lu($param) // art[,spalte[,nulltext[,labelspalte]]]
    {
	//echo '!berni! check me! lib.template.php   tpl_print_lu';
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
        : false
      );
      return ($ret ? $ret : ($str_nulltext ? $str_nulltext : '---'));
    } // end method tpl_print_lu

    function is_intext($str_varname)
    {
      return false!==(strpos($tpl_main->tpl_text, '{'. $str_varname. '}'));
    }

    function tpl_pageref($s_ident)
    {
      if ($this)
        $s_ident = $this->process_text($s_ident);
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
      return 'index.php?page='. $s_ident;
/**/
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

	  function tpl_thumbnail($param) {
		  global $ab_path;
		  $tmp = explode(',', $param);

		  $file = $tmp['0'];
		  if (strpos($file, '{') !== FALSE) {
			  $file = $this->parseTemplateString($file);


		  } elseif (substr($file, 0, 1) == '"') {
			  $file = substr($file, 1, -1);
		  } else {
			  $file = $this->getval($file);
			  if (substr($file, 0, 1) != '/') $file = '/' . $file;
		  }
		  $ab_baseurl = $this->tpl_uri_baseurl("/");
          if ((strpos($file, $ab_baseurl) === 0)) {
              $file = preg_replace("/".preg_quote($ab_baseurl, "/")."/", "/", $file, 1);
            }

		  if (isset($tmp['1']) == TRUE && trim($tmp['1']) != 'null') {
			  $width = $tmp['1'];
		  } else {
			  $width = NULL;
		  }
		  if (isset($tmp['2']) && trim($tmp['2']) != 'null') {
			  $height = $tmp['2'];
		  } else {
			  $height = NULL;
		  }

		  if ($tmp['3'] == 'crop') {
			  $crop = TRUE;
		  } else {
			  $crop = FALSE;
		  }

		  require_once $ab_path . '/sys/lib.imagecache.php';

		  $imagecache = new Hostbar_Imagecache();
		  $file = $imagecache->cache($file, $width, $height, $crop);
          $file = str_replace($GLOBALS["ab_path"], "", $file);

		  return $this->tpl_uri_baseurl($file);
	  }

    function tpl_forward($param)
    {
      forward("index.php?$param");
    }

    function shownav($n_from, $n_to, $b_showall, $s_type, $id_parent,
		$ar_nav_custom=NULL, $nar_pageallow_custom=NULL)
    {
      global $ar_navpath, $ar_nav, $nar_pageallow;
      static $cache = array ();
	  if (!is_null($ar_nav_custom)) $ar_nav=$ar_nav_custom;
	  if (!is_null($nar_pageallow_custom)) $nar_pageallow=$nar_pageallow_custom;
      $s_param = (int)$n_from.':'.(int)$n_to.':'.$b_showall.':'.$s_type.':'.$id_parent;
      if ($cache[$s_param]) return $cache[$s_param];
#if (!$n_from && !$s_type) echo ht(dump($ar_navpath));
#echo "<b>shownav($n_from, $n_to, $b_showall, $s_type, $id_parent)</b><br />";
      $i=0;
      $ar_res = array ();
#if (!$n_from && !$s_type) echo ht(dump($nar_pageallow));
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
              elseif ($nar_pageallow['admin/'. $kid['IDENT']])
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
        if ($row['IDENT'] && $nar_pageallow['admin/'. $row['IDENT']])
        {
          $tpl_tmp = new Template('skin/nav'. (int)$n_from
            . ($s_type ? '.'. $s_type : ''). '.htm', 'nav');

          $tpl_tmp->addvars($row);
          $tpl_tmp->addvar('i', $i);
          $tpl_tmp->addvar('POS', $i+1);
          $tpl_tmp->addvar('is_inpath', $fl_current = in_array ($row['ID_NAV'], $ar_navpath));
          $tpl_tmp->addvar('is_current', $tmp = (end($ar_navpath)==$row['ID_NAV']));

          $tpl_tmp->addvar('PAGE', $s_page =
            ($row['ALIAS'] ? 'page='. $row['ALIAS'] :
            ($row['IDENT'] ? 'page='. $row['IDENT'] :
            'nav='. $row['ID_NAV']
          )));

          $tpl_tmp->addvar('href', 'index.php?'. $s_page);

          $ar_res[] = $tpl_tmp->process();
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

    function tpl_nav($str_params)
    {
#echo "<b>tpl_nav($str_params)</b><br />";
      global $ar_navpath;
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
      else
        return implode('', $this->shownav($n_from, $n_to, $b_showall, $s_type, (int)$ar_navpath[$n_from-1]));
    }

    function showembnav($uint_level, $uint_to, $fl_showall, $row_parent, $s_suffix='')
    {
      global $db, $ar_navpath, $ar_nav, $nar_pageallow;
      static $ok = NULL;
      if (is_null($ok)) $ok = array_keys($nar_pageallow);
      $uint_parent = (int)(is_array($row_parent) ? $row_parent['ID_NAV'] : $row_parent);
      $ar_result = array();
#echo ht(dump($ok));
      foreach($ar_nav[$uint_parent]['KIDS'] as $id)
      {
        $row = $ar_nav[$id];
		#echo ht(dump($row));
        if (!$row['B_VIS'] || ($row['IDENT'] && !in_array("admin/".$row['IDENT'], $ok)))
		{
		  if(!in_array("admin/".$row['IDENT'], $ok))
		    ;//echo "continue: "."admin/".$row['IDENT']." OK: ".ht(dump($ok))."<br>";
		  continue;
		}
        $tpl_nav = new Template("skin/nav$uint_level$s_suffix.htm", 'nav');
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
###
        if (!$row['IDENT'])
        {
          $q = $row['KIDS'];
          while ($id_kid = array_shift($q))
          {
            $kid = $ar_nav[$id_kid];
            if ($kid['B_VIS'])
            {
              if (!$kid['IDENT'])
                $q = array_merge($kid['KIDS'], $q);
              elseif ($nar_pageallow['admin/'. $kid['IDENT']])
              {
                $row['IDENT'] = $kid['IDENT'];
                $row['ALIAS'] = $kid['ALIAS'];
                break;
              } // elseif
            } // kis is visible
          } // while
		 } // kein ident
###
        $tpl_nav->addvar('href', (1
          ? $s = preg_replace('%^/+%', '', preg_replace_callback('%index/(.*)$%', function ($match) {
                    return ($match[1] ? $match[1] : "index.php");
                },
            ($row['ident_path'] ? (
              ($p = strpos($row['ident_path'], '/'))
                ? ''
                : ''
            ). 'index.php?page=' : 'index.php?page=')

            . ($row['ALIAS'] ? $row['ALIAS'] : $row['IDENT'])
            . ($row['PARENT'] ? ''  : '')
          ))
          : 'index.php?'. $s_page
        ));

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
          $ar_sub = false;
        $ar_result[] = $tpl_nav;
/*
        if ($ar_sub && !$fl_inssub)
          foreach($ar_sub as $subrow)
            $ar_result[] = $subrow;
*/
      }
      return $ar_result;
    }


    function tpl_navemb($str_params)
    {
      global $group, $ar_navpath;
      list($uint_from, $uint_to, $fl_showall, $s_suffix) = explode(',', $str_params);
if ($s_suffix) $s_suffix = '.'. $s_suffix;
#echo ht(dump($s_suffix));
      if ('*'==$uint_to) $uint_to = -1;
      elseif (!$uint_to) $uint_to = $uint_from;
      return $this->process_value($this->showembnav($uint_from, $uint_to, $fl_showall,
        (int)$ar_navpath[$uint_from-1], $s_suffix));
    } // DirTemplate::tpl_navemb


    function tpl_list($param)
      # $str_itemtpl, $str_sqlselect)
    {
      global $db;
      $p = strpos($param, ',');
      $str_itemtpl = substr($param, 0, $p);
      $res = $db->querynow($sql=substr($param, $p+1));
#die(ht($sql));
      $ar_ret = array ();
      for ($i=0; $row = mysql_fetch_assoc($res['rsrc']); $i++)
      {
        $tpl_tmp = new DirTemplate($this->ar_dirs, $str_itemtpl);
        $tpl_tmp->addvars($row);
        $tpl_tmp->addvar('i', $i);
        $ar_ret[] = $tpl_tmp->process();
      }
      return implode('', $ar_ret);
    } // DirTemplate::tpl_list

    function tpl_curref()
    {
      return $GLOBALS['s_curref'];
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
#	  echo ht(dump($n_val));
      $this->addvar('perm_'. $s_ident, $n_val);
      $this->addvar('perm_'. $s_ident. '_R', $n_val & PERM_READ);
      $this->addvar('perm_'. $s_ident. '_C', $n_val & PERM_CREATE);
      $this->addvar('perm_'. $s_ident. '_E', $n_val & PERM_EDIT);
      $this->addvar('perm_'. $s_ident. '_D', $n_val & PERM_DEL);
#      $this->addvar('perm_'. $s_ident. '_S', $n_val & PERM_SHOW);
      return '';
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
      $return = '<script type="text/javascript" src="'.$GLOBALS["originalSystemSettings"]['SITE']['BASE_URL'].'tinymce/jscripts/tiny_mce/tiny_mce.js'.'"></script>';
    $editor_is_set = true;
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

    theme_advanced_buttons1 : "bold,italic,underline,undo,redo,link,unlink,image,forecolor,removeformat,cleanup,code",
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

    function tpl_editor($params)
    {
      global $nar_systemsettings,$editor_is_set;
      $arParams = explode(",", $params);
      $css = "";
      list($param,$width,$height) = explode(',', $params);
      if (count($arParams) > 3) {
          $css = $this->parseTemplateString( implode(",", array_splice($arParams, 3)) );
      }
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
	    $return = '<script type="text/javascript" src="'.$GLOBALS["originalSystemSettings"]['SITE']['BASE_URL'].'tinymce/jscripts/tiny_mce/tiny_mce.js'.'"></script>';
		$editor_is_set = true;
	  } // erstes mal geladen

	  $return .= '<script type="text/javascript">
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
		forced_root_block: false,
		verify_html : false,
		verify_css_classes : false,
		elements : "ajaxfilemanager",
		file_browser_callback : "ajaxfilemanager",
		plugins : "safari,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template",

		// Theme options
		theme_advanced_buttons1 : "code,save,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,styleselect,formatselect,fontselect,fontsizeselect",
		theme_advanced_buttons2 : "cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,link,unlink,anchor,image,cleanup,|,insertdate,inserttime,preview,|,forecolor,backcolor",
		theme_advanced_buttons3 : "tablecontrols,|,hr,removeformat,visualaid,|,sub,sup,|,charmap,emotions,media,advhr,|,ltr,rtl,|,fullscreen",
		theme_advanced_buttons4 : "insertlayer,moveforward,movebackward,absolute,|,styleprops,|,cite,abbr,acronym,del,ins,attribs,|,visualchars,nonbreaking",
		theme_advanced_toolbar_location : "top",
		theme_advanced_toolbar_align : "left",
		theme_advanced_statusbar_location : "bottom",
		theme_advanced_resizing : true,

		// Example content CSS (should be your site CSS)
		content_css : "'.($css ? $css : '/skin/style.css').'?" + new Date().getTime()
	});
		function ajaxfilemanager(field_name, url, type, win) {
			var ajaxfilemanagerurl = "/tinymce/ajaxfilemanager/ajaxfilemanager.php?editor=tinymce";
			switch (type) {
				case "image":
					break;
				case "media":
					break;
				case "flash":
					break;
				case "file":
					break;
				default:
					return false;
			}
            tinyMCE.activeEditor.windowManager.open({
             file : ajaxfilemanagerurl,
             title : "My File Browser",
             width : 720,  // Your dimensions may differ - toy around with them!
             height : 500,
             resizable : "yes",
             inline : "yes",  // This parameter only has an effect if you use the inlinepopups plugin!
             close_previous : "no"
            }, {
            window : win,
            input : field_name
            });
            return false;
		}
		tinyMCE.execCommand("mceAddControl", true, "'.$param.'");
</script>';

	  $return .= "\n\n";

	  $return .= '<textarea name="'.$param.'" id="'.$param.'">'.$value.'</textarea>';

	  return $return;
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
      
      function tpl_less_color_hex($params) {
          return $this->tpl_color_hex( $this->tpl_var_resolve($params.",@,,variables_") );
      }
      
      function tpl_color_hex($params) {
          $ar_params = explode(",", $params);
          $color = $this->parseTemplateString($ar_params[0]);
          if (preg_match("/^#([0-9a-f]){1}([0-9a-f]){1}([0-9a-f]){1}$/i", $color, $ar_matches)) {
              // Single digit colors (e.g. #f00 / #fff), convert to #rrggbb
              return "#".$ar_matches[1].$ar_matches[1].$ar_matches[2].$ar_matches[2].$ar_matches[3].$ar_matches[3];
          } else {
              return $color;
          }
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
          
          include $GLOBALS["ab_path"]. 'cache/nav1.'.$GLOBALS["s_lang"].'.php';
          include $GLOBALS["ab_path"]. 'cache/nav1.php';
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
/*
  global $assoc_assoc_loaded_templates;
  if (SESSION)
    session_register('assoc_loaded_templates');
  if (!$assoc_assoc_loaded_templates)
    $assoc_loaded_templates = array ();
	*/
#}
#$ar_initfn[] = 'tpl_init';
?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */


#  $tpl_content->addvar('fnfpage', $str_fnfpage);
  $tpl_content->addvar('is_home', !$str_fnfpage || 'home'==$str_fnfpage);
if (0 && !$SILENCE)
{
  preg_match_all('/^((fk|id|do)(\_|.*)|(page|nav|path))$/im',
    implode("\n", array_keys($_REQUEST)),
    $matches
  );
  $tpl_content->tpl_text .= "<hr />$_SERVER[REQUEST_URI]<hr />". implode("<br />\n",
    preg_replace('/^.*$/e', '"$0 = \$_REQUEST[$0]"', $matches[1])
  );
}
?>
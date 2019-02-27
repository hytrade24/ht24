<?php
                if (($s_lang = $_REQUEST["lang"]) || (SESSION && ($s_lang = $_SESSION["lang"])))
                {
                  @include "cache/lang.$s_lang.php";
                  if (!$lang_list)
                    $s_lang = false;
                }
                if (!$s_lang)
                {
                  $s_lang = "de";
                  @include "cache/lang.$s_lang.php";
                }
                $langval = $lang_list[$s_lang]["BITVAL"];
                if (SESSION)
                  $_SESSION["lang"] = $s_lang;
                else
                  $ar_urlrewritevars["lang"] = $s_lang;
                ?>
            
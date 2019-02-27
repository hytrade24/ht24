<?php
/* ###VERSIONSBLOCKINLCUDE### */

$tpl_content->addvar('IDENT', $s_page);

if (($id_kat = $ar_params[1]) && ($liste = @file_get_contents('cache/faq.' . $id_kat . '.' . $s_lang . '.htm'))) {
  $tpl_content->addvar('ID_FAQKAT', $id_kat);
} else {
  $liste = file_get_contents('cache/faq.main.' . $s_lang . '_'.$GLOBALS["id_nav"].'.htm');
}
$tpl_content->addvar('liste', $liste);

$tpl_content->addvar('id_kat', $id_kat);
?>
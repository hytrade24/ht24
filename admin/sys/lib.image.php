<?php
/* ###VERSIONSBLOCKINLCUDE### */


function quickReduce($ar_file, $w, $h, $trg = false, $new_name = false)
{
    global $nar_systemsettings;

    $binConvert = $nar_systemsettings['SYS']['PATH_CONVERT'];

    if (!is_array($ar_file))
        die("ar-file has to be an array!");
    //echo ht(dump($ar_file));
    if (!$trg)
        $trg = $GLOBALS['ab_path'] . "uploads/images/";
    else
        $trg = $GLOBALS['ab_path'] . $trg;
    if (!$new_name)
        $new_name = time() . "." . $ar_file['name'];
    $target = $trg . $new_name;
    $org = getimagesize($ar_file['tmp_name']);
    if (($org[0] < $w && $org[1] < $h) || $h == 0 || $w == 0) {
        $w = $org[0];
        $h = $org[1];
    }
    system($str = "$binConvert '" . $ar_file['tmp_name'] . "' -geometry " . $w . "x" . $h . " '" . $target . "'\n");
    return str_replace($GLOBALS['ab_path'], '/', $target);
} // quicReduce()

?>
<?php
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 6.4.2
 */

function escape_csv_value($value, $seperator = ',') {
    $value = str_replace('"', '""', $value);
    if (preg_match('/'.$seperator.'/', $value) ||  preg_match("/\r/", $value) ||  preg_match("/\n/", $value) || preg_match('/"/', $value)) {
        return '"'.$value.'"';
    } else {
        return $value;
    }
}
function csvEscapeString($text, $seperator = ',') {
    return str_replace($seperator, '\\'.$seperator, mysql_escape_string($text));
}

global $lang_list;

if (!empty($_POST)) {
    $seperator = ",";
    $newline = "\n";
    switch ($_POST['format']) {
        default:
        case 'windows':
            $seperator = ";";
            $newline = "\r\n";
            break;
        case 'linux':
            $seperator = ";";
            $newline = "\n";
            break; 
        case 'mac':
            $seperator = ";";
            $newline = "\r";
            break;
    }
    $langvalTarget = false;
    $queryLang = "";
    if (!empty($_POST['language'])) {
        $langTarget = $lang_list[ $_POST['language'] ];
        $langvalTarget = $langTarget['BITVAL'];
        $queryLang = "AND s.BF_LANG=if(k.BF_LANG_KAT & ".$langvalTarget.", ".$langvalTarget.", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))";
    }
    $ar_kat = $db->fetch_table("
	SELECT
		k.LEVEL, k.ID_KAT, k.KAT_TABLE, k.LU_KATART, s.BF_LANG as LANGVAL, s.V1, s.V2, s.T1
	FROM `kat` k
	RIGHT JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=k.ID_KAT ".$queryLang."
	WHERE k.ROOT=1
	ORDER BY k.LFT");

    $csv = "";
    foreach ($ar_kat as $index => $row) {
        $data = '"'.$row["V1"].'"';
        $data .= $seperator.escape_csv_value($row["V2"], $seperator);
        $data .= $seperator.escape_csv_value($row["T1"], $seperator);
        $data .= $seperator.escape_csv_value($row["ID_KAT"], $seperator);
        $data .= $seperator.escape_csv_value( ($langvalTarget === false ? $row["LANGVAL"] : $langvalTarget), $seperator );
        $data .= $seperator.escape_csv_value($row["KAT_TABLE"], $seperator);
        $data .= $seperator.escape_csv_value($row["LU_KATART"], $seperator);
        $csv .= str_repeat($seperator, $row["LEVEL"]).$data.$newline;
    }

    header('Content-type: application/csv');
    header('Content-Disposition: attachment; filename="categories.csv"');
    die($csv);
}

$tpl_content->addlist("languages", array_values($lang_list), "tpl/de/kat_export.row_lang.htm");

?>
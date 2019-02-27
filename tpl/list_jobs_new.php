<?php
/* ###VERSIONSBLOCKINLCUDE### */

$arSettings = array(
    "PAGE"      => ($tpl_content->vars["PAGE"] > 0 ? (int)$tpl_content->vars["PAGE"] : 1),
    "PERPAGE"   => ($tpl_content->vars["PERPAGE"] > 0 ? (int)$tpl_content->vars["PERPAGE"] : 8),
    "TEMPLATE"  => (!empty($tpl_content->vars["TEMPLATE"]) ? (int)$tpl_content->vars["TEMPLATE"] : "default")
                    // TODO: Sicherstellen dass der Parameter "TEMPLATE" reiner Text ist (z.B. '../example' verhindern)
);
$hash = md5(serialize($arSettings));

$cacheListFilename = $ab_path.'cache/marktplatz/list_jobs.'.$hash.'.htm';
$cachePagerFilename = $ab_path.'cache/marktplatz/list_jobs.'.$hash.'.pager.htm';
$timeCache = @filemtime($cacheListFilename);
$timeNow = time();
$timeDiff = (($timeNow - $timeCache) / 60);

if(!file_exists($cacheListFilename) || ($timeDiff > 60)) {
    // Load template
    $tpl_content->LoadText("tpl/".$s_lang."/list_jobs_new.".$arSettings["TEMPLATE"].".htm");
    // Read list of entries (e.g. clubs)
    require_once 'sys/lib.job.php';
    $jobManagement = JobManagement::getInstance($db);
    $jobManagement->setLangval($langval);
    $searchParameter = array(
        "OFFSET"    => (($arSettings["PAGE"] - 1) * $arSettings["PERPAGE"]),
        "LIMIT"     => $arSettings["PERPAGE"]
    );

    $arListe = $jobManagement->fetchAllByParam($searchParameter);
    if (!empty($arListe)) {
        // Add entries to template
        foreach ($arListe as $listeIndex => $listeValues) {
            if ($listeValues['CLUB_LOGO'] != "") {
                $arListe[$listeIndex]['CLUB_LOGO'] = 'cache/club/logo/'.$listeValues['CLUB_LOGO'];
            }
        }

        // $countJobs = $jobManagement->countByParam($searchParameter);

        $tpl_content->addlist("liste", $arListe, "tpl/".$s_lang."/list_jobs_new.".$arSettings["TEMPLATE"].".row.htm");
        //$tpl_content->isTemplateRecursiveParsable = TRUE;
    }
    $result = $tpl_content->process();
    // Write to cache file
    @file_put_contents($cacheListFilename, $result);
    chmod($cacheListFilename, 0777);
} else {
    $result = @file_get_contents($cacheListFilename);
    $tpl_content->tpl_text = $result;
}
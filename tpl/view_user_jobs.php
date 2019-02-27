<?php
/* ###VERSIONSBLOCKINLCUDE### */

#$SILENCE = false;
require_once 'sys/lib.job.php';

$jobManagement = JobManagement::getInstance($db);
$jobManagement->setLangval($langval);

// Parameters
$userId = ((int)$ar_params[2] ? (int)$ar_params[2] : null);
$id_kat = ((int)$ar_params[3] ? (int)$ar_params[3] : 0);
$npage = ((int)$ar_params[4] ? (int)$ar_params[4] : 1);
$id_job = ((int)$ar_params[5] ? (int)$ar_params[5] : 0);

$perpage = 10;
$limit = ($perpage * $npage) - $perpage;


$user_ = $db->fetch1("select VORNAME,NACHNAME,NAME,CACHE,STAMP_REG,LASTACTIV,URL,STRASSE,PLZ,ORT,LU_PROFESSION,ID_USER,UEBER, ROUND(RATING) as lastrate,TIMESTAMPDIFF(YEAR,GEBDAT,CURDATE()) as age,TEL from user where ID_USER='" . $userId . "'");

$tpl_content->addvar("active_jobs", 1);

if (($userId != null) && ($user != null)) {
  if ($id_job > 0) {
    $query = "SELECT
    		j.*, sj.*,
    		j.STREET as JOB_STREET,
    		j.ZIP as JOB_ZIP,
    		j.CITY as JOB_CITY,
    		(SELECT V1 FROM string WHERE S_TABLE='country' AND BF_LANG=" . $langval . " AND  FK=j.FK_COUNTRY) as JOB_COUNTRY
    	FROM `job` j
    	LEFT JOIN `string_job` sj ON
    		sj.S_TABLE='job' AND sj.FK=j.ID_JOB AND
    		sj.BF_LANG=if(j.BF_LANG_JOB & " . $langval . ", " . $langval . ", 1 << floor(log(j.BF_LANG_JOB+0.5)/log(2)))
    	WHERE j.ID_JOB=" . (int)$id_job . " AND j.OK = 3";
    $ar_job = $db->fetch1($query);

    if ($ar_job != null) {
      $tpl_content->addvars($ar_job);
    }
  }

  $searchParameter['LIMIT'] = $perpage;
  $searchParameter['OFFSET'] = $limit;
  $searchParameter['PUBLISHED'] = true;
  $searchParameter['FK_AUTOR'] = (int)$userId;
  $searchParameter['EXCLUDE_ID'] = (int)$id_job;
  $searchParameter['JOIN_VENDOR'] = true;

  $jobs = $jobManagement->fetchAllByParam($searchParameter);
  $countJobs = $jobManagement->countByParam($searchParameter);

  $all = $countJobs; //$db->fetch_atom("SELECT FOUND_ROWS()");
  $tpl_content->addlist("liste", $jobs, $ab_path . 'tpl/' . $s_lang . '/jobs.row.htm');

  $pager = htm_browse_extended($all, $npage, "view_user_jobs," . chtrans($user_['NAME']) . "," . $userId . "," . $id_kat . ",{PAGE}", $perpage);
  $tpl_content->addvar("pager", $pager);

  $tpl_content->addvar("t_" . $view, 1);
  $tpl_content->addvar("UID", $uid);

  $tpl_content->addvars($user_, 'USER_');

} else {
  $nullUser = $db->fetch_blank('user');
  $tpl_content->addvars($nullUser);
}

$res = $db->fetch_table( $q = "
	select
		s.V1, s.FK
	from
		`kat` t
	left join
		string_kat s on s.S_TABLE='kat' and s.FK=t.ID_KAT
		and s.BF_LANG=if(t.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
	where
		t.B_VIS=1
		and ROOT=6 and LFT <> 1
	order by
		s.V1");


$tpl_content->addlist("liste_category_rows",$res,"tpl/".$s_lang."/category.row.left.htm");
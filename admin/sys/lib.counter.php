<?php
/* ###VERSIONSBLOCKINLCUDE### */



function commentCountNews($jumpTo = '', $limit = 0, $step = 100) {
	global $db;
	$news = $db->fetch_table ( "select n.*,count(DISTINCT t.ID_COMMENT_THREAD) threads,
    count(DISTINCT c.ID_COMMENT) comments
    from news n
    left join comment_thread t on n.ID_NEWS=t.FK and t.S_TABLE='news'
  left join comment c on t.ID_COMMENT_THREAD=c.FK_COMMENT_THREAD
    group by ID_NEWS
  order by STAMP DESC limit " . $limit . "," . $step );
	if (! count ( $news ))
		return 'ready';
	
		#echo $limit+$step;
	for($i = 0; $i < count ( $news ); $i ++) {
		if ($news [$i] ['PCOUNT'] != $news [$i] ['comments'] || $news [$i] ['TCOUNT'] != $news [$i] ['threads'])
			$db->update ( "news", array ("ID_NEWS" => $news [$i] ['ID_NEWS'], "PCOUNT" => $news [$i] ['comments'], "TCOUNT" => $news [$i] ['threads'] ) );
	}
	return $limit + $step;
}

?>
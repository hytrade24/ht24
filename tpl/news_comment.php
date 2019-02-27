<?php

#echo ht(dump($ar_params));

$id = (int)($ar_params['1'] ? $ar_params['1'] : $_REQUEST['FK']);

$ar_artikel = $db->fetch1("select t.*, s.V1, s.V2
		from `news` t
		left join string_c s on s.S_TABLE='news' and s.FK=t.ID_NEWS
		and s.BF_LANG=if(t.BF_LANG_C & 128, 128, 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))
		where t.ID_NEWS=".(int)$id." and OK=3");

if(!empty($ar_artikel)) {
	// artikel ins Template
	$tpl_content->addvars($ar_artikel);

	// includes
	include $ab_path."sys/lib.newcomment.php";
	include $ab_path."sys/lib.bbcode.php";

	// save
	if(count($_POST)) {
		// init
		$bbcode = new bbcode();
		$comment = new comment($id, "news");

		if(!isset($_POST['PREV'])) {
		 	// nicht vorschaul
	 		$comment->addComment($_POST['BODY'], NULL);
	 		$comment->checkErrors();
	 		#echo ht(dump($comment));
	 		if(!empty($comment->err_out)) {
		 		# die("HIER ODER WAS?");
	 			$tpl_content->addvar('err',implode('<br />- ', $comment->err_out)); // Diese im Template ausgeben
	 			$tpl_content->addvars($_POST);
		 	} else {
	 			comment_mail($id, "news");
	 			$count = $db->fetch_atom("select count(*) from kommentar_news where FK=".$id);
	 			$db->querynow("update news set PCOUNT=".$count." where ID_NEWS=".$id);
	 			$db->querynow("update news set LAST_COMMENT=NOW() where ID_NEWS=".$id);
		 		// keine fehler -> forward
	 			die(forward("/news/kommentare,".$id.",,ok.htm"));
		 	}
	 	} else {
		 	// vorschau
	 		$tpl_content->addvars($_POST);
	 		$comment->preview($_POST['BODY']);
	 		$tpl_content->addvar("PREVIEW", $comment->preview_text);
		}
	} // post
	//kat tree
	#require_once $ab_path.'sys/lib.news.php';
	#$categoryTree = getNewsCategoryJSONTree(array($ar_artikel["FK_KAT"]));
	#$tpl_main->addvar("CATEGORY_JSON_TREE", $categoryTree);
} // artikel ist gültig

?>
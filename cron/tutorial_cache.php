<?php
/* ###VERSIONSBLOCKINLCUDE### */



 global $db;

 function thumbnail(&$row, $i) {
    $h = explode("/", $row['FK_KAT']);
 	$row['THUMB'] = strtolower($h[0]).".jpg";
 }

 $res_lang = $db->querynow("select * from lang where B_PUBLIC=1");
 while($langrow = mysql_fetch_assoc($res_lang['rsrc']))
 {
   $s_lang = $langrow['ABBR'];
   $langval = $langrow['BITVAL'];

   $tuts = $db->fetch_table("select t.*,t.ID_TUTORIAL_LIVE as ID_TUTORIAL, u.NAME as UNAME, u.CACHE,
    s.T1 as ARIANE, t.FK_KAT
     from tutorial_live t
      left join `user` u on t.FK_USER=u.ID_USER
	  left join tree_tutorial k on ID_TREE_TUTORIAL=t.FK_KAT
	  left join string_tree_tutorial s on s.FK=t.FK_KAT and
	     s.BF_LANG=if(k.BF_LANG_TREE_TUTORIAL &  ".$langval.", ".$langval.", 1 << floor(log(k.BF_LANG_TREE_TUTORIAL+0.5)/log(2)))
	 where OK=3
	 order by STAMP_UPDATE DESC LIMIT 6");

   $tpl = new Template($ab_path."tpl/".$s_lang."/tutorial_neu.htm");
   $tpl->addlist("liste", $tuts, $ab_path."tpl/".$s_lang."/tutorial_neu.row.htm", "thumbnail");
   file_put_contents($ab_path."cache/tutorial_neu.".$s_lang.".htm", $tpl->process());
   chmod($ab_path."cache/tutorial_neu.".$s_lang.".htm", 0777);
 } // while sprachen

?>
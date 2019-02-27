<?php
/* ###VERSIONSBLOCKINLCUDE### */



#$SILENCE = false;

  function get_ad(&$row, $i) {
    global $db;
    if ($row["FK_AD"] > 0) {
    	$ar_ad = $db->fetch1("
	    		SELECT
	    		 	a.*,
	    			m.NAME as MANUFACTURER
				FROM `ad_master` a
				LEFT JOIN `manufacturers` m ON m.ID_MAN=a.FK_MAN
				WHERE ID_AD_MASTER=".$row["FK_AD"]);
    	if (is_array($ar_ad)) {
	    	$row = array_merge($row, $ar_ad);
    	}
    }
  }
  
  $action = $_REQUEST["action"];
  $target_user = $_REQUEST["user"];
  $target_user_from = $_REQUEST["user_from"];
  
  if ($action == "remove") {
    $id_ad_sold_rating = $_REQUEST["rating"];
    $ar_ad_sold_rating = $db->fetch1("SELECT *
    		FROM `ad_sold_rating`
    		WHERE ID_AD_SOLD_RATING=".$id_ad_sold_rating);
    if (!empty($ar_ad_sold_rating)) {
      $db->querynow("UPDATE `ad_sold_rating` SET RATING=0 WHERE ID_AD_SOLD_RATING=".$id_ad_sold_rating);
      die(forward("index.php?page=userratings&lang=".$s_lang."&user=".$target_user));
    }
  }
  
  $perpage = 20; // Elemente pro Seite
  $limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);
  $where = array();
  if ($target_user > 0)
    $where[] = "r.FK_USER=".$target_user;
  if ($target_user_from > 0)
    $where[] = "r.FK_USER_FROM=".$target_user_from;
  
  $all = $db->fetch_atom("
  	SELECT count(*)
  		FROM `ad_sold_rating` r
  			".(!empty($where) ? "WHERE ".implode(' AND ', $where) : ""));
  $ratings = $db->fetch_table("
  	SELECT r.*, s.STAMP_BOUGHT, s.FK_AD,
  		(SELECT NAME FROM `user` WHERE ID_USER=r.FK_USER_FROM) as USERNAME_FROM,
  		(SELECT NAME FROM `user` WHERE ID_USER=r.FK_USER) as USERNAME
  		FROM `ad_sold_rating` r
  			LEFT JOIN `ad_sold` s ON r.FK_AD_SOLD=s.ID_AD_SOLD
  		".(!empty($where) ? "WHERE ".implode(' AND ', $where) : "")."
  		ORDER BY s.STAMP_BOUGHT DESC
  		LIMIT ".$limit.",".$perpage);
  
	$tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], 
		"index.php?page=".$tpl_content->vars['curpage']."&user=".$target_user."&user_from=".$target_user_from."&npage=", $perpage));
  $tpl_content->addlist("liste", $ratings, $ab_path.'admin/tpl/de/userratings.row.htm', 'get_ad');
?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */


$SILENCE=false;

echo "<h1>Ich hab doch gesagt nicht anklicken :-)</h1>";

if($_REQUEST['work'])
{

	if(!$_REQUEST['limit'])
    	$_REQUEST['limit']=0;

	$limit = $_REQUEST['limit'];
   	$perpage = 10;

   	### aufräumen
   	if($_REQUEST['kill'] == 'all')
   	{
   		### Kill old users
   		$res = $db->querynow("
   			SELECT
   				u.ID_USER,
   				u.`NAME`,
				t.ID_TUTORIAL
   			FROM
   				`user` u
			LEFT JOIN
				tutorial t on u.ID_USER = t.FK_USER
   			WHERE
   				`NAME` LIKE '%@'
   		");
   		$in = array();
   		while($row = mysql_fetch_assoc($res['rsrc']))
   		{
			if($row['ID_TUTORIAL'] >0)
			{
				echo $row['ID_USER']." :: ".$row['NAME']." hat TUTORIAL<br />";
				continue;
			}
   			$in[] = $row['ID_USER'];
   			echo $row['ID_USER']." :: ".$row['NAME']." wird gelöscht!<br />";
   		}
   		// TODO welche Foreign keys müssen gekillt werden?
   		$in_str = implode(",", $in);
   		#echo ht(dump($in));
   		#die("System hold");

   		### userdaten löschen
   		$liste_tabellen = "comment_ipcheck
competence
contact:FK_USER_FROM:FK_USER_TO
contact_pending:FK_USER_FROM:FK_USER_TO
geodb_usercache:ID
handbuch
img
job
job2gebot
job2pdf
job2question
job2user
job2watch
job_live
kommentar_handbuch
kommentar_news
kommentar_script
kommentar_tutorial
mail:FK_USER_TO:FK_USER_FROM
my_msg:FK_USERID_TO:FK_USERID_FROM
nl_recp
pageperm2user
perm2user
role2user
user2img
user_views
useronline:ID_USER
usersettings";

   		$hack = explode("\n", $liste_tabellen);
   		#echo ht(dump($hack)); die();
   		for($i=0; $i<count($hack); $i++)
   		{
   			#echo $hack[$i]. "<br />";
   			if(strstr($hack[$i], ":"))
   			{

   				$felder = explode(":", $hack[$i]);
   				//die(ht(dump($felder)));
   				$or = array();
   				$n = count($felder);
   				for($k=1; $k<$n; $k++)
   				{
   					$or[] = $felder[$k] ." IN (".$in_str.")";
   				}
   				#die(ht(dump($or)));
   				$where = implode(" OR ", $or);
   				$hack[$i] = $felder[0];
   			}
   			else
   				$where = "FK_USER IN (".$in_str.")";
   			$query = "delete from `".trim($hack[$i])."` WHERE ".$where;
   			mysql_query($query) or die("ERROR:<p>".mysql_error()."</p>IN<p>".$query."</p>");
   			#echo "<hr />".$query;
   		}

   		#die("Noch nicht wirklich löschen :-)");
   		#forward("index.php?page=script_import");

   		$res = $db->querynow("delete from user where ID_USER IN (".$in_str.")");
   		if(!empty($res['str_error']))
   			die(ht(dump($res)));

    	$db->querynow("truncate script_work");
     	$db->querynow("truncate script");
     	$db->querynow("truncate string_script_work");
     	$db->querynow("truncate string_script");
		$db->querynow("truncate kommentar_script");
		$db->querynow("truncate rating_script");
		$db->querynow("truncate scriptclick");
		$db->querynow("truncate scriptview");
		$db->querynow("truncate scripttop");
	 	die("KILLALL");
   	} // alles löschen

   	$res = $db->querynow("select * from `links` order by LKID DESC limit ".$limit.", ".$perpage);

   	$langval_bak = 128;
   	$s_lang_bak = "de";

   	if(!$res['int_result'])
    	die("Import abgeschlossen!");
	 $nn = 0;
   	while($row = mysql_fetch_assoc($res['rsrc']))
   	{
     	#echo ht(dump($row));

	 	### user suchen
	 	if(!$row['LKemail'])
	   		$row['LKemail'] = 'bschmalenberger@ebiz-consult.de';

	 	$fk_user = $db->fetch_atom("select ID_USER from `user` where EMAIL='".$row['LKemail']."'");
	 	//echo ht(dump($lastresult));
	 	if(!$fk_user)
	 	{
	   		#echo "user muss neu";
	   		$hack = explode("@", $row['LKemail']);
	   		$name = $hack[0]."@";
	   		$n = $db->fetch_atom("select count(*) from `user` where `NAME` LIKE '".$hack[0]."%'");
	   		#echo ht(dump($lastresult));
	   		if($n)
	   		{
		 		$name = $hack[0].(($n+$n)+2)."@";
		 		#echo "<hr />".$name;
	   		}
	   		$userarray = array
	   		(
	     		'EMAIL' => $row['LKemail'],
		 		'NAME' => $name,
		 		'PASS' => createpass(time()),
	   			'FK_LANG' => 1,
	   			'STAT' => 1,
	   			'CACHE' => 'A',
	   			'LASTACTIV' => '2009-05-08',
	   			'STAMP_REG' => date('Y-m-d')
	   		);
	   		#echo ht(dump($userarray));
	   		$fk_user = $db->update("user", $userarray);
	   		if(!$fk_user)
	   		{
	     		echo ht(dump($userarray));
				echo ht(dump($lastresult));
		 		die("Usererstellen geht nicht!");
	   		}
	   		else
	     		$db->querynow("insert into role2user set FK_USER=".$fk_user.", FK_ROLE=3");
	 	} // user unbekannt

	 	### kategorie
	 	$basekat = $db->fetch_atom("select FK from string_tree_script where V1='".$row['LKArt']."'");
	 	echo ht(dump($lastresult));
	 	$fk_kat = $db->fetch_atom("select k.ID_TREE_SCRIPT
	  		from string_tree_script s
	   		left join tree_script k on k.PARENT=".$basekat." and s.FK=k.ID_TREE_SCRIPT
	  		where s.V1='".$row['LKTyp']."' and k.ID_TREE_SCRIPT IS NOT NULL");
	 $nn++;
	 echo "<p>".$nn."</p>";
	 	if(!$fk_kat)
	 	{
	   		$fk_kat = 0;
	 	}
	 	### sprache
	 	if($row['LKlanguage'] != "DE")
	 	{
	   		$langval = 64;
	   		$s_lang = 'en';
	 	} // nicht deutsch

	 	### array für db

	 	$row['LKdescription'] = str_replace("[b]", "<b>", $row['LKdescription']);
	 	$row['LKdescription'] = str_replace("[/b]", "</b>", $row['LKdescription']);
	 	$row['LKdescription'] = str_replace("[i]", "<b>", $row['LKdescription']);
	 	$row['LKdescription'] = str_replace("[/i]", "</b>", $row['LKdescription']);
	 	$row['LKdescription'] = str_replace("[u]", "<u>", $row['LKdescription']);
	 	$row['LKdescription'] = str_replace("[/u]", "</u>", $row['LKdescription']);

	 	$row['LKdescription'] = stripslashes(nl2br($row['LKdescription']));

	 	$new = array
	 	(
	   		'FK_USER' => $fk_user,
	   		'FK_KAT' => $fk_kat,
	   		'PAID' => $row['lkpaid'],
	   		'V1' => $row['LKTitel'],
	   		'V2' => substr(strip_tags($row['LKdescription']), 0, 255),
	   		'T1' => $row['LKdescription'],
	   		'STAMP' => date("Y-m-d", $row['LKDate']),
	   		'LINK_DSC' => $row['LkLink'],
	  		 'OK' => ($row['LKAktiv'] ? 3 : 1),
	   		'COMM_SITE' => $row['KLpaidsite']
	 	);

	 	$id = $db->update("script_work", $new);
	 	$db->querynow("insert into script set ID_SCRIPT=".$id);
	 	if($new['OK'] == 3)
	 	{
	   		$new['ID_SCRIPT'] = $id;
	 		$db->update("script", $new);
	 	} // ist aktiv

		$langval = $langval_bak;
	 	$s_lang = $s_lang_bak;

	 	echo ht(dump($new));
		#die("zwischenstopp!");
   	} // while

   	echo '<meta http-equiv="refresh" content="2;URL=index.php?page=script_import&work=1&limit='.($limit+$perpage).'">';

} // iregdnwas amchen

die("<p>Script stopp!</p>");

?>
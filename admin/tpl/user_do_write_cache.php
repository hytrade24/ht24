<?php
/* ###VERSIONSBLOCKINLCUDE### */

  


$perpage=100;

(int)$all = $db->fetch_atom('select  count(*)
				from user where STAT < 2'); #Anzahl der User ermitteln
(int)$pages_to_go=ceil($all/$perpage); #Anzahl der Seiten die benötigt werden
(int)$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);#limit errechnen
(int)$pages_left=$pages_to_go-($limit/$perpage);

	if (($pages_left)>0) 
	{
		(int)$npage=$_REQUEST['npage']+1;
		//forward('index.php?nav='.$id_nav.'&npage='.$npage.'&running=1&frame=iframe', 0,false, true, 1);
		$ar_data = $db->fetch_table('select  ID_USER,CACHE
				from user where STAT < 2 order by ID_USER
	 			LIMIT '.$limit.','.$perpage);
		foreach($ar_data as $i=>$row) 
		{
					$data = $db->fetch1('select  *
							from usersettings where FK_USER='.$row['ID_USER']);
					if (empty($data))
					 {
							$res2 = $db->querynow('INSERT INTO `usersettings` (`FK_USER`) values ('.$row['ID_USER'].')'); //User anlegen
							$data = $db->fetch1('select  *
							from usersettings where FK_USER='.$row['ID_USER']);
					}
					
					if (!is_dir($ab_path.$GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$row['CACHE'].'/'.$data['FK_USER']))
					{
						@mkdir ($ab_path.$GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$row['CACHE'].'/'.$data['FK_USER'], 0777);  //Users Cacheverzeichnis
						@chmod ($ab_path.$GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$row['CACHE'].'/'.$data['FK_USER'], 0777);  // rechte richig setzen
					}
			      $s_code = '<?'. 'php $useroptions = '. php_dump($data, 0). '; ?'. '>';
      			  $fp = fopen($ab_path.$GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$row['CACHE'].'/'.$data['FK_USER']."/useroptions.php", 'w');
      			  fputs($fp, $s_code);
      			  fclose ($fp);
				  
				  
				  if (!file_exists($ab_path.$GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$row['CACHE'].'/'.$data['FK_USER']."/".$data['FK_USER'].".jpg")) {
						copy($ab_path."uploads/users/no.jpg", $ab_path.$GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$row['CACHE'].'/'.$data['FK_USER']."/".$data['FK_USER'].".jpg"); 
						copy($ab_path."uploads/users/no_s.jpg", $ab_path.$GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$row['CACHE'].'/'.$data['FK_USER']."/".$data['FK_USER']."_s.jpg"); 
					}
		
		}
		$tpl_content->addvars(array ('running' => 1, 
									'npage' => $npage,
									'mpage' => $pages_left));
	}
	else
	{
		$tpl_content->addvar('running',0);
		//forward('index.php?page=user_do_write_cache&frame=iframe', 0,'self');
		#forward('index.php?page=searching_index', 0,'top');
	}


?>
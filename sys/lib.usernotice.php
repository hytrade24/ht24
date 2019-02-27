<?php
/* ###VERSIONSBLOCKINLCUDE### */


	class usernotice
	{
		var $db = NULL;
		var $uid = NULL;
		var $langval = NULL;
		var $ar_msg = array();
		var $err = false;
		
		function usernotice()
		{
			$this->db = &$GLOBALS['db'];
			$this->uid = &$GLOBALS['uid'];
			$this->langval = &$GLOBALS['langval'];
			$this->ar_msg = get_messages('USERNOTICE');
			//alte nachrichten löschen
			$this->deleteNotice();
		}
		
		function addNotice($an, $SYS_NAME, $was)
		{
			$q = "insert into usernotice (FK_USER, FK_CONTENT, SYS_NAME, NEU, DATUM, EXPIRE)
			      values (".(int)$an.", ".(int)$was.", '".$SYS_NAME."', 1, now(), date_add(now(), INTERVAL 7 DAY))";
			$this->db->querynow($q);
		}
		
		function deleteNotice($id = 0)
		{
				if ($id != 0)
				{
					if ($this->db->fetch_atom("select FK_USER from usernotice where ID_USERNOTICE = ".(int)$id) == $this->uid)
						$q = "delete from usernotice where ID_USERNOTICE = ".(int)$id;
					else
						$this->err = true;
				}
				else
					$q = "delete from usernotice where EXPIRE < now() and FK_USER = ".(int)$id;
				$del = $this->db->querynow($q);
				if ($del['strerror'] == "")
					return true;
				else
					return false;
		}
		
		function updateNotice()
		{
			//`NEU` auf 0 setzen
			$this->db->querynow("update usernotice set NEU = 0 where FK_USER = ".$this->uid);
		}
		
		function readNotice($all = false)
		{
			if ($all)
				$res = $this->db->fetch_table("select * from USERNOTICE where FK_USER = ".$this->uid." order by DATUM desc");
			else
				$res = $this->db->fetch_table("select * from USERNOTICE where FK_USER = ".$this->uid." order by DATUM desc limit 0,10");
			if ($res)
			{
				$ar_output = array();
				$ar_tmp = array();
				foreach($res as $notice)
				{
					$ar_tmp['DATUM'] = $notice['DATUM'];
					$ar_tmp['ID_USERNOTICE'] = $notice['ID_USERNOTICE'];
					$ar_tmp['NOTICE'] = "";
					if ($notice['NEU'])
						$ar_tmp['NOTICE'] = "(<span style='color:#E20000;'>neu</span>) ";
					switch($notice['SYS_NAME'])
					{
						case 'F_INVITE_SEND':
						case 'F_REUQEST_GET':
						case 'INVITE_ACK':
						case 'INVITE_CANCEL':
						case 'REQUEST_ACK':
						case 'REQUEST_CANCEL':
						case 'U_INVITE_GET':
						case 'U_REQUEST_SEND':
							$who = $this->db->fetch_atom("select FK_USER from einladung where ID_EINLADUNG = ".$notice['FK_CONTENT']);
							if ($who == $this->uid)
								$arg = 1;
							else
								$arg = 0;
							$ar_tmp['NOTICE'] .= ' <a href="/account/account_firma_invite,show,'.$notice['FK_CONTENT'].','.$arg.'.htm">'.$this->ar_msg[$notice['SYS_NAME']].'</a>';
							break;
						case 'REZENSION_DENY':
							$ar_tmp['NOTICE'] .= ' <a href="/rezension_edit,'.$notice['FK_CONTENT'].','.$this->uid.'.htm">'.$this->ar_msg[$notice['SYS_NAME']].'</a>';
							break;
						case 'REZENSION_ACK':
							$ar_tmp['NOTICE'] .= ' <a href="/rezension_show,'.$notice['FK_CONTENT'].','.$this->uid.'.htm">'.$this->ar_msg[$notice['SYS_NAME']].'</a>';
							break;
						case 'REZENSION_CHECK':
							$ar_tmp['NOTICE'] .= ' <a href="/rezension_edit,'.$notice['FK_CONTENT'].','.$this->uid.'.htm">'.$this->ar_msg[$notice['SYS_NAME']].'</a>';
							break;
						case 'SKRIPT_DENY':
							$ar_tmp['NOTICE'] .= ' <a href="#">'.$this->ar_msg[$notice['SYS_NAME']].'</a>';
							break;
						case 'SKRIPT_ACK':
							$ar_tmp['NOTICE'] .= ' <a href="#">'.$this->ar_msg[$notice['SYS_NAME']].'</a>';
							break;
						case 'TUTORIAL_DENY':
							$ar_tmp['NOTICE'] .= ' <a href="/tuorial_insert2,'.$notice['FK_CONTENT'].','.$this->uid.',1.htm">'.$this->ar_msg[$notice['SYS_NAME']].'</a>';
							break;
						case 'TUTORIAL_CHECK':
							$ar_tmp['NOTICE'] .= ' <a href="/tuorial_insert2,'.$notice['FK_CONTENT'].','.$this->uid.',1.htm">'.$this->ar_msg[$notice['SYS_NAME']].'</a>';
							break;
						case 'TUTORIAL_ACK':
							$ar_tmp['NOTICE'] .= ' <a href="/tutorial_show,'.$notice['FK_CONTENT'].','.$this->uid.',1.htm">'.$this->ar_msg[$notice['SYS_NAME']].'</a>';
							break;
						case 'FIRMA_QUIT':
							$ar_tmp['NOTICE'] .= ' <a href="/user,'.$notice['FK_CONTENT'].','.$this->uid.',1.htm">'.$this->ar_msg[$notice['SYS_NAME']].'</a>';
							break;
					}
					$ar_output[] = $ar_tmp;
				}
			}
			//echo ht(dump($ar_output));
			return $ar_output;
		}
	
	
	} //end class
?>
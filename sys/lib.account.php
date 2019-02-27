<?php
/* ###VERSIONSBLOCKINLCUDE### */


	class account
	{
	
		var $db = NULL, $uid = NULL, $f_id = NULL;
		var $is_admin = false, $is_coadmin = false;
		
		function account($uid)
		{
			$this->db = &$GLOBALS['db'];
			$this->uid = (int)$uid;
			$this->fetch_user_attr();
		}
		
		function fetch_user_attr()
		{
			$this->get_firma_id();

			$query = 'select FK_EDITOR, FK_OWNER from firma where ID_FIRMA = '.(int)$this->f_id;
			$res = $this->db->fetch1($query);
			
			if ($res)
			{
				if ($res['FK_OWNER'] == $this->uid || $res['FK_EDITOR'] == $this->uid)
					$this->is_coadmin = true;
			
				if ($res['FK_OWNER'] == $this->uid)
					$this->is_admin = true;
			}
		}
		
		function del_user_img()
		{
			if ($id_img = $this->db->fetch_atom("select ID_IMG from img where FK_USER = ".$this->uid." and MODUL = 'account'"))
			{
				$img = $this->db->fetch1("select * from img where ID_IMG = ".$id_img);
				@unlink($img['SRC']);
				if(!empty($img['SRC_T']))
				{
					@unlink($img['SRC_T']);
				}
				$del = $this->db->querynow("delete from img where ID_IMG = ".$id_img);
			}
		}
		
		function set_user_img_lock()
		{
			$ar_syssettings = $GLOBALS['nar_systemsettings'];
			if ($ar_syssettings['USER']['CHECK_USERIMG'])
				$query = 'update user set IMG_UNLOCK = 0 where ID_USER = '.$this->uid;
			else
				$query = 'update user set IMG_UNLOCK = 1 where ID_USER = '.$this->uid;
			$this->db->querynow($query);
		}
		
		function check_user_email($str_mail)
		{
			$str_mail_escaped = "'".mysql_real_escape_string(stripslashes($str_mail))."'";
			return $this->db->fetch_atom("select count(EMAIL) from user where EMAIL = ".$str_mail_escaped." and ID_USER <> ".$this->uid);
		}
		
		function get_user_profil($was)
		{
			switch ($was)
			{
				case 'allgemein':
					$q = "select i.SRC, i.WIDTH, i.HEIGHT, u.NAME, u.VORNAME, u.NACHNAME, u.LU_ANREDE, u.EMAIL, u.STRASSE, u.PLZ, u.ORT, u.FK_COUNTRY, u.FK_MUTTERSPRACHE, u.GEBDAT, u.BERUF, u.BESCHREIBUNG from user u
								left join img i on i.FK_USER = ".$this->uid."
									and MODUL = 'account'
								where ID_USER = ".$this->uid;
					break;
				case 'kontakt':
					$q = 'select EMAIL, TEL, FAX, MOBIL, URL, ICQ, MSN, AIM, SKYPE, YAHOO	from user
									where ID_USER = '.$this->uid;
					break;
				case 'referenzen':
					$q = 'select REFERENZEN from user where ID_USER = '.$this->uid;
					break;
				case 'jobboerse':
					$q = "select t.*, s.V1, s.T1 from `job_gesuch` t 
								left join string_job_gesuch s on s.S_TABLE='job_gesuch' 
									and s.FK=t.ID_JOB_GESUCH 
									and s.BF_LANG=if(t.BF_LANG_JOB_GESUCH & ".$GLOBALS['langval'].", ".$GLOBALS['langval'].", 1 << floor(log(t.BF_LANG_JOB_GESUCH+0.5)/log(2)))
								where t.FK_USER = ".$this->uid;
					//$tmp = $this->db->fetch_table($q);
					break;
			}
			return $this->db->fetch1($q);
		}
		
		function get_firma_id($int_uid = NULL)
		{
			$query = 'select FK_FIRMA from user where ID_USER = ';
			if ($int_uid == NULL)
				$query .= $this->uid;
			else
				$query .= (int)$int_uid;
			$f_id = $this->db->fetch_atom($query);
			if ($int_uid == NULL)
				$this->f_id = $f_id;
			
			return $f_id;
		}
		
		function get_firma()
		{
			if ($this->f_id)
			{
				$query = "select i.SRC, i.WIDTH, i.HEIGHT,  f.*, f.FK_EDITOR as editor, f.FK_OWNER as owner, u.NAME as editor_name from firma f
									left join user u on u.ID_USER = f.FK_EDITOR
									left join img i on i.ID_IMG = f.FK_IMG
									where f.ID_FIRMA = ".(int)$this->f_id;
				return $this->db->fetch1($query);
			}
			else
			{
				return false;
			}
		}
		
		function check_firmenname($str_name, $not = NULL)
		{
			$name = "'".mysql_real_escape_string(stripslashes($str_name))."'";
			$query = 'select count(ID_FIRMA) from firma where FIRMENNAME='.$name;
			if ($not != NULL)
				$query .= ' and ID_FIRMA <> '.(int)$this->f_id;
			return (int)$this->db->fetch_atom($query);
		}
		
		function check_firma_email($str_mail, $not = NULL)
		{
			$email = "'".mysql_real_escape_string(stripslashes($str_mail))."'";
			$query = 'select count(ID_FIRMA) from firma where EMAIL='.$email;
			if ($not != NULL)
				$query .= ' and ID_FIRMA <> '.(int)$this->f_id;
      return (int)$this->db->fetch_atom($query);
		}
		
		function set_user2firma($id_firma)
		{
			$query = 'update user set FK_FIRMA = '.(int)$id_firma.' where ID_USER = '.$this->uid;
			$this->db->querynow($query);
		}
		
		function set_firma_img_lock()
		{
			$ar_syssettings = $GLOBALS['nar_systemsettings'];
			if ($ar_syssettings['USER']['CHECK_USERIMG'])
				$query = 'update firma set LOGO_UNLOCK = 0 where ID_FIRMA = '.(int)$this->f_id;
			else
				$query = 'update firma set LOGO_UNLOCK = 1 where ID_FIRMA = '.(int)$this->f_id;
			$this->db->querynow($query);
		}
				
		function del_firma_img()
		{
			if ($id_img = $this->db->fetch_atom("select FK_IMG from firma where ID_FIRMA = ".(int)$this->f_id))
			{
				$img = $this->db->fetch1("select * from img where ID_IMG = ".$id_img);
				@unlink($img['SRC']);
				if(!empty($img['SRC_T']))
				{
					@unlink($img['SRC_T']);
				}
				$del = $this->db->querynow("delete from img where ID_IMG = ".$id_img);
			}
		}
		
		function del_user2firma()
		{
			//prüfen, ob noch ne einladung vorhanden ist
			if (count($res_tmp = $this->db->fetch_table('select ID_EINLADUNG from einladung	where FK_FIRMA = '.(int)$this->f_id.'	and FK_USER = '.$this->uid)))
			{
				foreach ($res_tmp as $invite)
					$this->db->querynow('update einladung set STATUS = 3 where ID_EINLADUNG = '.(int)$invite['ID_EINLADUNG']);
			}
			
			$query = 'update user set FK_FIRMA = 0 where ID_USER = '.$this->uid;
			$del = $this->db->querynow($query);
			if ($del['str_error'] == "")
				return true;
			else
				return false;
				
			
		}
		
		function user_exists($str_username, $where = NULL)
		{
			$str_username_escaped = '"'.mysql_real_escape_string(stripslashes($str_username)).'"';
			$query = 'select ID_USER from user where NAME = '.$str_username_escaped;
			if ($where != NULL)
				$query .= ' and FK_FIRMA = '.(int)$this->f_id;
			return $this->db->fetch_atom($query);
		}
		
		function find_user($str_username, $where = NULL)
		{
			$str_username_escaped = "'".mysql_real_escape_string(stripslashes($str_username))."%'";
			$query = "select i.SRC, i.WIDTH, i.HEIGHT, u.NAME as search_name, u.ID_USER as search_id_user from user u
								left join img i on i.FK_USER = u.ID_USER
									and i.MODUL = 'account'
								where u.NAME like ".$str_username_escaped;
			if ($where != NULL)
				$query .= ' and FK_FIRMA = '.(int)$this->f_id;
			return $this->db->fetch_table($query);
		}
		
		function send_invite($id, $str_einladung, $an_firma = NULL)
		{
			$str_einladung_escaped = '"'.mysql_real_escape_string(stripslashes($str_einladung)).'"';
			$query = 'insert into einladung (FK_USER, FK_FIRMA, EINLADUNG, TYP, DATUM, PURGEDATUM)';
			if ($an_firma == NULL)
				$query .= ' values ('.(int)$id.', '.(int)$this->f_id.', '.$str_einladung_escaped.', 0, now(), date_add(now(), INTERVAL 14 DAY))';
			else
				$query .= ' values ('.$this->uid.', '.(int)$id.', '.$str_einladung_escaped.', 1, now(), date_add(now(), INTERVAL 14 DAY))';
			$insert = $this->db->querynow($query);
			
			if ($insert['str_error'] == "")
			{
				$this->write_notice($insert['int_result']);
				return $insert['int_result'];
			}
			else
				return false;
		}
		
		function invite_exists($id, $tmp = NULL)
		{
			if ($tmp == NULL)
				$query = 'select count(ID_EINLADUNG) from einladung where FK_USER = '.(int)$id.' and FK_FIRMA = '.(int)$this->f_id.' and TYP = 0';
			else
				$query = 'select count(ID_EINLADUNG) from einladung where FK_USER = '.$this->uid.' and TYP = 1';
			return $this->db->fetch_atom($query);
		}
		
		function get_invites($type, $from)
		{
			if ($from == 0)
				$query =  'select e.*, u.NAME as i_name, u.ID_USER as i_uid from einladung e
										left join user u on u.ID_USER = e.FK_USER
										where e.FK_FIRMA = '.(int)$this->f_id.'
										and e.TYP = '.(int)$type;
			elseif ($from == 1)
				$query = 'select e.*, f.FIRMENNAME, f.ID_FIRMA from einladung e
										left join firma f on f.ID_FIRMA = e.FK_FIRMA
										where e.FK_USER = '.$this->uid.'
										and e.TYP = '.(int)$type;
			$tmp = $this->db->fetch_table($query);
			return  $tmp;
		}
		
		function get_one_invite($i_id, $who)
		{
			if ($who == 0)
				$query = 'select e.*, e.FK_USER as fk_uid, u.NAME as i_name from einladung e
										left join user u on u.ID_USER = e.FK_USER
										where ID_EINLADUNG = '.(int)$i_id;
			else
				$query = 'select e.*, e.FK_USER as fk_uid, f.FIRMENNAME as i_name from einladung e
										left join firma f on f.ID_FIRMA = e.FK_FIRMA
										where ID_EINLADUNG = '.(int)$i_id;
			
			return $this->db->fetch_table($query);
		}
		
		function set_coadmin($c_uid)
		{
			$query = 'update firma set FK_EDITOR = '.(int)$c_uid.' where ID_FIRMA = '.(int)$this->f_id;
			$update = $this->db->querynow($query);
			if ($update['str_error'] == "")
				return true;
			else
				return false;
		}
		
		function unset_coadmin()
		{
			$query = 'update firma set FK_EDITOR = 0 where ID_FIRMA = '.(int)$this->f_id;
			$update = $this->db->querynow($query);
			if ($update['str_error'] == "")
				return true;
			else
				return false;
		}
		
		function coadmin_exists()
		{
			return $this->db->fetch_atom('select FK_EDITOR from firma where ID_FIRMA = '.(int)$this->f_id);
		}
		
		function set_admin($a_id)
		{
			$query = 'update firma set FK_OWNER = '.(int)$a_id.' where ID_FIRMA = '.(int)$this->f_id;
			$update = $this->db->querynow($query);
			if ($update['str_error'] == "")
				return true;
			else
				return false;
		}
		
		function user_can_cancel_invitation($i_id)
		{
			$query = 'select FK_FIRMA, FK_USER from einladung where ID_EINLADUNG = '.(int)$i_id;
			$res = $this->db->fetch1($query);
			if ($res)
			{
				if ($res['FK_FIRMA'] == $this->f_id)
					return true;
				if ($res['FK_USER'] == $this->uid)
					return true;
				else
					return false;
			}
			else
				return false;
		}
		
		function cancel_invitation($i_id)
		{
			$query = 'update einladung set STATUS = 1 where ID_EINLADUNG = '.(int)$i_id;
			$update = $this->db->querynow($query);
			if ($update['str_error'] == "")
			{
				$this->write_notice($i_id);
				return true;
			}
			else
				return false;
		}
		
		function firma_exists($str_firmenname)
		{
			$str_firmenname_escaped = '"'.mysql_real_escape_string(stripslashes($str_firmenname)).'"';
			$query = 'select ID_FIRMA from firma where FIRMENNAME = '.$str_firmenname_escaped;
			return $this->db->fetch_atom($query);
		}
		
		function find_firma($str_firmenname)
		{
			$str_firmenname_escaped = "'%".mysql_real_escape_string(stripslashes($str_firmenname))."%'";
			$query = "select i.SRC, i.WIDTH, i.HEIGHT, f.ID_FIRMA as search_id_firma, f.FIRMENNAME as search_firmenname from firma f
								left join img i on i.ID_IMG = f.FK_IMG
								where f.FIRMENNAME like ".$str_firmenname_escaped;
			return $this->db->fetch_table($query);
		}
		
		function user_can_accept_invitation($i_id)
		{
			$query = 'select TYP, FK_FIRMA, FK_USER from einladung where ID_EINLADUNG = '.(int)$i_id;
			$res = $this->db->fetch1($query);
			if ($res)
			{
				if ($res['TYP'] == 0 && $res['FK_USER'] == $this->uid)
					return true;
				elseif ($res['TYP'] == 1 && $res['FK_FIRMA'] == (int)$this->f_id)
					return true;
				else
					return false;
			}
			else
				return false;
		}
		
		function accept_invitation($i_id)
		{
			$ar_tmp = $this->db->fetch1('select TYP, FK_USER, FK_FIRMA from einladung where ID_EINLADUNG = '.(int)$i_id);
			if ($ar_tmp['TYP'] == 1)
			{
				$query = 'update user set FK_FIRMA = '.(int)$ar_tmp['FK_FIRMA'].' where ID_USER = '.(int)$ar_tmp['FK_USER'];
				$this->db->querynow($query);
			
				$query = 'update einladung set STATUS = 1 where FK_USER = '.(int)$ar_tmp['FK_USER'].' and ID_EINLADUNG <> '.(int)$i_id;
				$this->db->querynow($query);
			}
			else
			{
				$query = 'update user set FK_FIRMA = '.(int)$ar_tmp['FK_FIRMA'].' where ID_USER = '.$this->uid;
				$this->db->querynow($query);
			
				$query = 'update einladung set STATUS = 1 where FK_USER = '.$this->uid.' and ID_EINLADUNG <> '.(int)$i_id;
				$this->db->querynow($query);
			}
			
			$query = 'update einladung set STATUS = 2 where ID_EINLADUNG = '.(int)$i_id;
			$this->db->querynow($query);
			
			$this->write_notice($i_id);
			
			//firmen id aktualiseren
			$this->get_firma_id();
			
		}
		
		function user_can_resend_invitation($i_id)
		{
			$query = 'select TYP, FK_FIRMA, FK_USER from einladung where ID_EINLADUNG = '.(int)$i_id;
			$res = $this->db->fetch1($query);
			if ($res)
			{
				if ($res['TYP'] == 0 && $res['FK_FIRMA'] == (int)$this->f_id)
					return true;
				elseif ($res['TYP'] == 1 && $res['FK_USER'] == $this->uid)
					return true;
				else
					return false;
			}
			else
				return false;
		}
		
		function resend_invitation($i_id)
		{
			$query = 'update einladung set STATUS = 0 where ID_EINLADUNG = '.(int)$i_id;
			$update = $this->db->querynow($query);
			if ($update['str_error'] == "")
			{
				$this->write_notice($i_id);
				return true;
			}
			else
				return false;
		}
		
		function user_can_watch_invitation($i_id)
		{
			return $this->user_can_cancel_invitation($i_id);
		}
		
		function get_sprachen()
		{
			$q = "select t.*, u.WERT, s.V2 from `country` t 
						left join string s on s.S_TABLE='country'
						  and s.FK=t.ID_COUNTRY
							and s.BF_LANG=if(t.BF_LANG & ".$GLOBALS['langval'].", ".$GLOBALS['langval'].", 1 << floor(log(t.BF_LANG+0.5)/log(2)))
						left join user_stuff u on u.FK = t.ID_COUNTRY 
							and u.FK_TYP = 'FREMDSPRACHEN' 
							and u.FK_USER = ".$this->uid."
						where t.B_VIS = 1
						order by s.V2";
			return $this->db->fetch_table($q);
		}
		
		function update_profil($was, $ar_data)
		{
			$ar_data['ID_USER'] = $this->uid;
			switch ($was)
			{
				case 'allgemein':
				case 'kontakt':
				case 'referenzen':
					return $this->db->update('user', $ar_data);
					break;
				case 'jobboerse':
					if ($j_id = $this->db->fetch_atom("select ID_JOB_GESUCH from job_gesuch where FK_USER = ".$this->uid))
						$ar_data['ID_JOB_GESUCH'] = $j_id;
					$ar_data['STAMP'] = date('Y-m-d H:i:s');
					return $this->db->update('job_gesuch', $ar_data);
					break;
				case 'progsprachen':
				case 'fremdsprachen':
					$f = $this->db->querynow("delete from user_stuff where FK_TYP = '".$was."' and FK_USER = ".$this->uid);
					$ar_val = array();
					foreach ($ar_data[strtoupper($was)] as $key => $val)
						$ar_values[] = "(".$this->uid.", ".$key.", '".strtoupper($was)."', ".(int)$val.")";
					$f = $this->db->querynow("insert into user_Stuff (FK_USER, FK, FK_TYP, WERT)
																	values ".implode(",", $ar_values));
					break;
					
			}
		
		}
		
		function write_notice($id_einladung)
		{
            global $ab_path;
			require_once($ab_path."sys/lib.usernotice.php");
			$notice = new usernotice();
			//$an, $SYS_NAME, $was
			$q = "select * from einladung where ID_EINLADUNG = ".(int)$id_einladung;
			$res = $this->db->fetch1($q);
			if ($res)
			{
				if ($res['TYP'] == 0)	//einladung von firma an user
				{
					//alle user ids holen der leutz, die in der firma sind
					$tmp = $this->db->fetch_table("select ID_USER from user where FK_FIRMA = ".(int)$res['FK_FIRMA']." and ID_USER <> ".(int)$res['FK_USER']);
					switch ($res['STATUS'])
					{
						case 0:	//neue nachricht
							//nachricht an eingeladenen user schicken
							$notice->addNotice($res['FK_USER'], "U_INVITE_GET", $id_einladung);
							//nachricht an alle mitglieder der firma senden
							foreach ($tmp as $user)
								$notice->addNotice($user['ID_USER'], "F_INVITE_SEND", $id_einladung);
							break;
						case 1:	//einladung abgebrochen
							//dem eingeladenen user bescheid sagen
							$notice->addNotice($res['FK_USER'], "INVITE_CANCEL", $id_einladung);
							//allen in firma davon erzählen .. 
							foreach ($tmp as $user)
								$notice->addNotice($user['ID_USER'], "INVITE_CANCEL", $id_einladung);
							break;
						case 2:	//einladung angenommen
							//dem eingeladenen user bescheid sagen
							$notice->addNotice($res['FK_USER'], "INVITE_ACK", $id_einladung);
							//allen in firma davon erzählen .. 
							foreach ($tmp as $user)
								$notice->addNotice($user['ID_USER'], "INVITE_ACK", $id_einladung);
							break;
					}
				}
				elseif ($res['TYP'] == 1)	//anfrage von user an firma
				{
					//alle user ids holen der leutz, die in der firma sind
					$tmp = $this->db->fetch_table("select ID_USER from user where FK_FIRMA = ".(int)$res['FK_FIRMA']." and ID_USER <> ".(int)$res['FK_USER']);
					switch ($res['STATUS'])
					{
						case 0:	//user hat anfrage verschickt
							//dem user die notice schrieben
							$notice->addNotice($res['FK_USER'], "U_REQUEST_SEND", $id_einladung);
							//allen in firma davon erzählen .. 
							foreach ($tmp as $user)
								$notice->addNotice($user['ID_USER'], "F_REUQEST_GET", $id_einladung);
							break;
						case 1:	//anfrage abgebrochen
							//suer notice
							$notice->addNotice($res['FK_USER'], "REQUEST_CANCEL", $id_einladung);
							//allen in firma davon erzählen .. 
							foreach ($tmp as $user)
								$notice->addNotice($user['ID_USER'], "REQUEST_CANCEL", $id_einladung);
							break;
						case 2:	//anfrage angenommen
							//dem user die notice schrieben
							$notice->addNotice($res['FK_USER'], "REQUEST_ACK", $id_einladung);
							//allen in firma davon erzählen .. 
							foreach ($tmp as $user)
								$notice->addNotice($user['ID_USER'], "REQUEST_ACK", $id_einladung);
							break;
					}
				}
			}
							
		}
	//end class	
	}
?>
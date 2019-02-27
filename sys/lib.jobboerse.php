<?php
/* ###VERSIONSBLOCKINLCUDE### */


	class jobboerse
	{
		var $db;
		var $langval;
		var $err = array();
		
		function jobboerse()
		{
			$this->db = &$GLOBALS['db'];
			$this->langval = &$GLOBALS['langval'];
		}
		
		function readNewest($was, $limit = 15, $join = false)
		{
			$str_join1 = "";
			$str_join2 = "";
			if ($join)
			{
				$str_join1 = "u.ID_USER, u.NAME, u.PLZ, u.ORT, ";
				$str_join2 = "left join user u on u.ID_USER = t.FK_USER ";
			}
			if ($was == 'suche')
			{
				$q = "select ".$str_join1."t.*, s.V1, s.T1 from `job_gesuch` t 
							left join string_job_gesuch s on s.S_TABLE='job_gesuch' 
								and s.FK=t.ID_JOB_GESUCH 
								and s.BF_LANG=if(t.BF_LANG_JOB_GESUCH & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_JOB_GESUCH+0.5)/log(2)))
							".$str_join2."
							where t.B_VIS = 1
							order by t.STAMP desc
							limit 0,".$limit;
				$res = $this->db->fetch_table($q);
			}
			if ($was = 'biete')
			{
				
			}
			
			return $res;
		}
		
		function readJob($was, $reiter, $id)
		{
			if ($was == 'suche')
			{
				if ($reiter == 'allgemein')
				{
					$q = "select u.ID_USER as J_ID_USER, u.NAME, u.EMAIL, u.VORNAME, u.NACHNAME, u.PLZ, 
								u.ORT, u.GEBDAT, u.BERUF, t.FK_ARBEITSZEIT, t.FK_MOBILITAET, t.FK_ARBEITSRADIUS
								, s.V1 as TITEL, s.T1 as TEXT, t.* from `job_gesuch` t 
								left join string_job_gesuch s on s.S_TABLE='job_gesuch' 
									and s.FK=t.ID_JOB_GESUCH 
									and s.BF_LANG=if(t.BF_LANG_JOB_GESUCH & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_JOB_GESUCH+0.5)/log(2)))
								left join user u on u.ID_USER = t.FK_USER
								where t.B_VIS = 1
								and t.ID_JOB_GESUCH = ".(int)$id;
					$res = $this->db->fetch1($q);
					$q = "select s.V1 from `selection_stuff` t 
								left join string_selection_stuff s on s.S_TABLE='selection_stuff' 
									and s.FK=t.ID_SELECTION_STUFF 
									and s.BF_LANG=if(t.BF_LANG_SELECTION_STUFF & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_SELECTION_STUFF+0.5)/log(2)))
								where t.ID_SELECTION_STUFF in (".(int)$res['FK_ARBEITSZEIT'].",".(int)$res['FK_MOBILITAET'].",".(int)$res['FK_ARBEITSRADIUS'].")";
					$res2 = $this->db->fetch_table($q);
					$tmp = array('ARBEITSZEIT' => $res2[0]['V1'],
												'MOBILITAET' => $res2[1]['V1'],
												'ARBEITSRADIUS' => $res2[2]['V1']);
					$res = array_merge($res, $tmp);
				}
				if ($reiter == 'details')
				{
					$q = "select u.NAME, u.ID_USER as J_ID_USER, t.ID_JOB_GESUCH, sc.V2 as MUTTERSPRACHE, s.V1 as TITEL, u.TEL, u.FAX, u.MOBIL, u.REFERENZEN from `job_gesuch` t 
								left join string_job_gesuch s on s.S_TABLE='job_gesuch' 
									and s.FK=t.ID_JOB_GESUCH 
									and s.BF_LANG=if(t.BF_LANG_JOB_GESUCH & 128, 128, 1 << floor(log(t.BF_LANG_JOB_GESUCH+0.5)/log(2)))
								left join user u on u.ID_USER = t.FK_USER
								left join `country` c on c.ID_COUNTRY = u.FK_MUTTERSPRACHE
								left join string sc on sc.S_TABLE='country' 
									and sc.FK=c.ID_COUNTRY and sc.BF_LANG=if(c.BF_LANG & 128, 128, 1 << floor(log(c.BF_LANG+0.5)/log(2)))
								where t.B_VIS = 1
								and t.ID_JOB_GESUCH = ".(int)$id;
					$res = $this->db->fetch1($q);
				}
				if ($reiter == 'kontakt')
				{
					$q = "select u.NAME as E_NAME, t.ID_JOB_GESUCH, s.V1 as TITEL, '".$GLOBALS["user"]["NAME"]."' as A_NAME from `job_gesuch` t 
								left join string_job_gesuch s on s.S_TABLE='job_gesuch' 
									and s.FK=t.ID_JOB_GESUCH 
									and s.BF_LANG=if(t.BF_LANG_JOB_GESUCH & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_JOB_GESUCH+0.5)/log(2)))
								left join user u on u.ID_USER = t.FK_USER
								where t.ID_JOB_GESUCH = ".(int)$id;
					$res = $this->db->fetch1($q);
					$q = "select s.V1 BETREFF, s.T1 as TEXT from `mailvorlage` t 
										left join string_mail s on s.S_TABLE='mailvorlage' 
											and s.FK=t.ID_MAILVORLAGE 
											and s.BF_LANG=if(t.BF_LANG_MAIL & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_MAIL+0.5)/log(2)))
										where t.SYS_NAME = 'JOBBOERSE_SUCHE'";
					$mail = $this->db->fetch1($q);
					$mail['TEXT'] = parse_mail($mail['TEXT'],$res);
					$res = array_merge($mail, $res);
				}
			}
			if ($was = 'biete')
			{
			
			}
			return $res;
		}
		
		function readSelectionStuff($u_id, $was)
		{
			$string = "";
			$bf_lang = "";
			if ($was == 'progsprachen')
			{
				$table = "selection_stuff";
				$string = "_".$table;
				$bf_lang = "_SELECTION_STUFF";
			}
			elseif ($was == 'fremdsprachen')
				$table = "country";
			
			$q = "select s.V1, s.V2 from user_stuff us
						left join ".$table." t on t.ID_".strtoupper($table)." = us.FK
						left join string".$string." s on s.S_TABLE='".$table."' 
							and s.FK=t.ID_".strtoupper($table)." 
							and s.BF_LANG=if(t.BF_LANG".$bf_lang." & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG".$bf_lang."+0.5)/log(2)))
						where us.FK_USER = ".(int)$u_id."
						and us.WERT <> 0
						and us.FK_TYP = '".strtoupper($was)."'";
						
			return $this->db->fetch_table($q);
		}
		
		function editJob($ar_data, $schritt)
		{
            global $ab_path;
			$ar_msg = get_messages("JOBBOERSE");
			if ($schritt == 1)
			{
				if ($ar_data['FIRMENNAME'] == "")
					$this->err[] = $ar_msg['KEIN_FIRMENNAME'];
				if ($ar_data['ANSCHRIFT'] == "")
					$this->err[] = $ar_msg['KEINE_ANSCHRIFT'];
				if ($ar_data['ANSPRECHP'] == "")
					$this->err[] = $ar_msg['KEIN_ANSPRECHPARTNER'];
				if ($ar_data['EMAIL'] == "")
					$this->err[] = $ar_msg['KEINE_EMAIL'];
				if ($ar_data['EMAIL'] != "" && !validate_email($ar_data['EMAIL']))
					$this->err[] = $ar_msg['INVALID_EMAIL'];
				if ($ar_data['TEL'] == "")
					$this->err[] = $ar_msg['KEIN_TEL'];
				if ($ar_data['WEBSEITE'] != "")
				{
					$ar_data['WEBSEITE'] = preg_replace('(^\w+://(.*)$)', '$1', $ar_data['WEBSEITE']);
					if (!validate_url('http://'. $ar_data['WEBSEITE']))
						$this->err[] = $ar_msg['INVALID_URL'];
				}
				if (!empty($ar_data['ID_JOB_BIETE']))
				{
					if ($this->db->fetch_atom("select FK_USER from job_biete where ID_JOB_BIETE = ".(int)$ar_data) != $GLOBALS['uid'])
						$this->err[] = $ar_msg['KEIN_RECHT'];
				}
				if (!empty($ar_data['LOGO']['tmp_name']) && !count($this->err))
				{
					if ($img = $this->db->fetch1("select ID_IMG, SRC, SRC_T from img where MODUL = 'jobboerse' and FK_USER = ".$GLOBALS['uid']))
					{
						@unlink($img["SRC"]);
						@unlink($img["SRC_T"]);
						$this->db->querynow("delete from img where ID_IMG = ".(int)$img['ID_IMG']);
					}

					require_once($ab_path."sys/lib.media.php");
					$ar_data['FK_IMG'] = upload_image($ar_data['LOGO'], 'images/jobboerse', 'jobboerse');
				}
			}
			
			if ($schritt == 2)
			{
				if ($ar_data['T1'] == "")
					$this->err[] = $ar_msg['KEIN_TITEL'];
				if ($ar_data['V1'] == "")
					$this->err[] = $ar_msg['KEIN_TEXT'];
				if (!count($err))
				{
					date_implode($ar_data, 'START');
					date_implode($ar_data, 'ENDE');
				}
			}
			
			if ($schritt == 3)
			{
				//...
			}
			
			if (count($this->err))
				return false;
			else
				return $this->db->update("job_biete", $ar_data);
		
		}
		
		function deleteJob()
		{
		
		}
	
	
	
	//end class
	}


?>
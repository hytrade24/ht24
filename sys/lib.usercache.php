<?php
/* ###VERSIONSBLOCKINLCUDE### */


	class usercache
	{
		var $db = NULL, $uid = NULL, $s_lang = NULL, $langval = NULL;
		var $LAND = NULL, $res_b = array(), $admin = false, $ar_img = array(), $ar_img_t = array();

		function usercache($uid, $admin = false)
		{
			$this->db = &$GLOBALS['db'];
			$this->uid = $uid;
			$this->s_lang = &$GLOBALS['s_lang'];
			$this->langval = &$GLOBALS['langval'];
			$this->admin = $admin;
			$this->fetch_data();

		}

		function fetch_data()
		{
			if ($this->db->fetch_atom("select count(ID_IMG) from img where MODUL = 'account' and FK_USER = ".$this->uid))
			{
				$query = "select i.SRC_T, i.SRC, i.WIDTH, i.HEIGHT, i.WIDTH_T, i.HEIGHT_T, u.*, u.ID_USER as user_id from user u
										left join img i on i.FK_USER = u.ID_USER
										where i.MODUL = 'account'
										and u.ID_USER = ".$this->uid;
			}
			else
			{
				$query = "select *, ID_USER as user_id from user
										where ID_USER = ".$this->uid;
			}
			//main data
			$this->res_b = $this->db->fetch_table($query);
		}

		function get_image()
		{
			if (!count($this->ar_img_t))
			{
				//src, width, height for small user image
				if ($this->res_b[0]['SRC_T'] != NULL && $this->res_b[0]['IMG_UNLOCK'] != 0)
				{
					$this->ar_img_t = array('img_src' => $this->res_b[0]['SRC_T'],
													'img_width' => $this->res_b[0]['WIDTH_T'],
													'img_height' => $this->res_b[0]['HEIGHT_T']);
				}
				else
				{
					if ($this->admin)
						$this->ar_img_t = array('img_src' => '../bilder/no_image_small.jpg',
														'img_width' => 80,
														'img_height' => 80);
					else
						$this->ar_img_t = array('img_src' => 'bilder/no_image_small.jpg',
														'img_width' => 80,
														'img_height' => 80);
				}
			}

			if (!count($this->ar_img))
			{
				//src, width, height for big user image
				if ($this->res_b[0]['SRC'] != NULL && $this->res_b[0]['IMG_UNLOCK'] != 0)
				{
					$this->ar_img = array('img_src' => $this->res_b[0]['SRC'],
													'img_width' => $this->res_b[0]['WIDTH'],
													'img_height' => $this->res_b[0]['HEIGHT']);
				}
				else
				{
					if ($this->admin)
						$this->ar_img = array('img_src' => '../bilder/no_image_big.jpg',
														'img_width' => 120,
														'img_height' => 120);
					else
						$this->ar_img = array('img_src' => 'bilder/no_image_big.jpg',
														'img_width' => 120,
														'img_height' => 120);
				}
			}
		}

		function get_country()
		{
			//get country
			$query = "select t.*, s.V1 as LAND from `country` t
										left join string s on s.S_TABLE='country'
										and s.FK=t.ID_COUNTRY
										and s.BF_LANG=if(t.BF_LANG & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG+0.5)/log(2)))
										where t.ID_COUNTRY = ".$this->res_b[0]['FK_COUNTRY'];

			$res_tmp = $this->db->fetch_table($query);
			$this->LAND = $res_tmp[0]['LAND'];
		}

		function create_all()
		{
			$this->get_image();
			$this->create_all_uboxes();
			$this->create_all_details();
		}

		function create_all_uboxes()
		{
			$this->get_image();
			$this->ubox1();
			$this->ubox2();
			$this->ubox3();
			$this->ubox4();
			$this->ubox5();
		}

		function create_all_details()
		{
			$this->detail_allgemein();
			$this->detail_beitraege();
			$this->detail_jobboerse();
			$this->detail_kontakt();
			$this->detail_referenzen();
			$this->detail_bewertungen();
		}

		function create_detail($detail)
		{
			switch ($detail)
			{
				case 'allgemein':
					$this->detail_allgemein();
					break;
				case 'beitraege':
					$this->detail_beitraege();
					break;
				case 'jobboerse':
					$this->detail_jobboerse();
					break;
				case 'kontakt':
					$this->detail_kontakt();
					break;
				case 'referenzen':
					$this->detail_referenzen();
					break;
				case 'bewertungen':
					$this->detail_bewertungen();
					break;
				default:
					return false;
					break;
			}
		}

		function create_ubox($type)
		{
			switch ($type)
			{
				case 1:
					$this->get_image();
					$this->ubox1();
					break;
				case 2:
					$this->get_image();
					$this->ubox2();
					break;
				case 3:
					$this->get_image();
					$this->ubox3();
					break;
				case 4:
					$this->get_image();
					$this->ubox4();
					break;
				case 5:
					$this->get_image();
					$this->ubox5();
					break;
				default:
					return false;
					break;
			}
		}

		function ubox1()
		{
            global $ab_path;

			$tpl_tmp = new Template($ab_path."tpl/".$this->s_lang."/ubox1.htm");
			//prüfen welche messenger einen eintrag haben + diese auch darstellen
			if ($this->res_b[0]['ICQ'] != NULL)
				$tpl_tmp->addvar('icq', 1);
			if ($this->res_b[0]['MSN'] != NULL)
				$tpl_tmp->addvar('msn', 1);
			if ($this->res_b[0]['AIM'] != NULL)
				$tpl_tmp->addvar('aim', 1);
			if ($this->res_b[0]['SKYPE'] != NULL)
				$tpl_tmp->addvar('skype', 1);
			if ($this->res_b[0]['YAHOO'] != NULL)
				$tpl_tmp->addvar('yahoo', 1);

			$tpl_tmp->addvars($this->ar_img);
			$tpl_tmp->addvars($this->res_b[0]);

			$content = $tpl_tmp->process();

			$file_name = $ab_path.'cache/usercache/ubox/ubox'.$this->uid.'_1.php';
			$fp = fopen($file_name, w);
			fwrite($fp, $content);
			fclose($fp);
			chmod($file_name, 0777);
		}

		function ubox2()
		{
            global $ab_path;
			$tpl_tmp = new Template($ab_path."tpl/".$this->s_lang."/ubox2.htm");
			$tpl_tmp->addvars($this->ar_img_t);
			$tpl_tmp->addvars($this->res_b[0]);
			$content = $tpl_tmp->process();

			$file_name = $ab_path.'cache/usercache/ubox/ubox'.$this->uid.'_2.php';
			$fp = fopen($file_name, w);
			fwrite($fp, $content);
			fclose($fp);
			chmod($file_name, 0777);
		}

		function ubox3()
		{
            global $ab_path;
			$tpl_tmp = new Template($ab_path."tpl/".$this->s_lang."/ubox3.htm");
			$tpl_tmp->addvars($this->ar_img_t);
			$tpl_tmp->addvars($this->res_b[0]);
			$content = $tpl_tmp->process();

			$file_name = $ab_path.'cache/usercache/ubox/ubox'.$this->uid.'_3.php';
			$fp = fopen($file_name, w);
			fwrite($fp, $content);
			fclose($fp);
			chmod($file_name, 0777);
		}

		function ubox4()
		{
            global $ab_path;
			$tpl_tmp = new Template($ab_path."tpl/".$this->s_lang."/ubox4.htm");
			$tpl_tmp->addvars($this->ar_img);
			$tpl_tmp->addvars($this->res_b[0]);
			$content = $tpl_tmp->process();

			$file_name = $ab_path.'cache/usercache/ubox/ubox'.$this->uid.'_4.php';
			$fp = fopen($file_name, w);
			fwrite($fp, $content);
			fclose($fp);
			chmod($file_name, 0777);
		}

		function ubox5()
		{
            global $ab_path;
			$tpl_tmp = new Template($ab_path."tpl/".$this->s_lang."/ubox5.htm");
			$tpl_tmp->addvars($this->ar_img_t);
			$tpl_tmp->addvars($this->res_b[0]);
			$content = $tpl_tmp->process();

			$file_name = $ab_path.'cache/usercache/ubox/ubox'.$this->uid.'_5.php';
			$fp = fopen($file_name, w);
			fwrite($fp, $content);
			fclose($fp);
			chmod($file_name, 0777);
		}

		function detail_allgemein()
		{
            global $ab_path;

			if ($this->LAND == NULL)
			{
				$this->get_country();
			}
			$tpl_tmp = new Template($ab_path."tpl/".$this->s_lang."/user_allgemein.htm");
			$firma = $this->db->fetch_atom('select FIRMENNAME from firma where ID_FIRMA = '.(int)$res_b[0]['FK_FIRMA']);
			$tpl_tmp->addvar('FIRMENNAME', $firma);
			$tpl_tmp->addvars($this->res_b[0]);
			$tpl_tmp->addvar('LAND', $this->LAND);

			$content = $tpl_tmp->process();

			$file_name = $ab_path.'cache/usercache/ubox/ubox'.$this->uid.'_allgemein.php';
			$fp = fopen($file_name, w);
			fwrite($fp, $content);
			fclose($fp);
			chmod($file_name, 0777);
		}

		function detail_beitraege()
		{
            global $ab_path;

			$tpl_tmp = new Template($ab_path."tpl/".$this->s_lang."/user_beitraege.htm");
			//tutorials
			$query = "select t.*, s.V1, s.V2 from `tutorial` t
									left join string_tutorial s on s.S_TABLE='tutorial'
									and s.FK=t.ID_TUTORIAL
									and s.BF_LANG=if(t.BF_LANG_TUTORIAL & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_TUTORIAL+0.5)/log(2)))
									where t.FK_UID = ".$this->uid."
									and t.STATUS = 1
									order by DATUM
									limit 5";
			$res_tmp = $this->db->fetch_table($query);
				$tpl_tmp->addlist('tutorial', $res_tmp, $ab_path.'tpl/'.$this->s_lang.'/user_beitraege.tutorial.htm');

			$tpl_tmp->addvar('NAME', $this->res_b[0]['NAME']);
			$content = $tpl_tmp->process();

			$file_name = $ab_path.'cache/usercache/ubox/ubox'.$this->uid.'_beitraege.php';
			$fp = fopen($file_name, w);
			fwrite($fp, $content);
			fclose($fp);
			chmod($file_name, 0777);
		}

		function detail_jobboerse()
		{
            global $ab_path;

			$content = "<p>Jobbörse: Existiert noch nicht.</p>";

			$file_name = $ab_path.'cache/usercache/ubox/ubox'.$this->uid.'_jobboerse.php';
			$fp = fopen($file_name, w);
			fwrite($fp, $content);
			fclose($fp);
			chmod($file_name, 0777);
		}

		function detail_kontakt()
		{
            global $ab_path;

			if ($this->LAND == NULL)
			{
				$this->get_country();
			}
			$tpl_tmp = new Template($ab_path."tpl/".$this->s_lang."/user_kontakt.htm");
			$tpl_tmp->addvars($this->res_b[0]);
			$tpl_tmp->addvar('LAND', $this->LAND);

			$content = $tpl_tmp->process();

			$file_name = $ab_path.'cache/usercache/ubox/ubox'.$this->uid.'_kontakt.php';
			$fp = fopen($file_name, w);
			fwrite($fp, $content);
			fclose($fp);
			chmod($file_name, 0777);
		}

		function detail_referenzen()
		{
            global $ab_path;

			$tpl_tmp = new Template($ab_path."tpl/".$this->s_lang."/user_referenzen.htm");
			$tpl_tmp->addvars($this->res_b[0]);

			$content = $tpl_tmp->process();

			$file_name = $ab_path.'cache/usercache/ubox/ubox'.$this->uid.'_referenzen.php';
			$fp = fopen($file_name, w);
			fwrite($fp, $content);
			fclose($fp);
			chmod($file_name, 0777);
		}

		function detail_bewertungen()
		{
            global $ab_path;

			$content = "<p>Bewertungen: Existiert noch nicht.</p>";

			$file_name = $ab_path.'cache/usercache/ubox/ubox'.$this->uid.'_bewertungen.php';
			$fp = fopen($file_name, w);
			fwrite($fp, $content);
			fclose($fp);
			chmod($file_name, 0777);
		}

	//end class
	}
?>
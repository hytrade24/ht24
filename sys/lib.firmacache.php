<?php
/* ###VERSIONSBLOCKINLCUDE### */



	class firmacache
	{
		var $db = NULL, $f_id = NULL, $s_lang = NULL, $langval = NULL;
		var $res = array(), $ar_img = array(), $LAND = NULL;
		
		function firmacache($f_id)
		{
			$this->f_id = (int)$f_id;
			$this->db = &$GLOBALS['db'];
			$this->s_lang = &$GLOBALS['s_lang'];
			$this->langval = &$GLOBALS['langval'];
			$this->get_data();
		}
		
		function get_data()
		{
			$query = 'select * from firma where ID_FIRMA = '.$this->f_id;
			$this->res = $this->db->fetch1($query);
		}
		
		function get_img()
		{
			if ($this->res['FK_IMG'] && $this->res['LOGO_UNLOCK'] != 0)
			{
				$query = 'select SRC, WIDTH, HEIGHT from img where MODUL = "firma" and ID_IMG = '.(int)$this->res['FK_IMG'];
				$this->ar_img = $this->db->fetch1($query);
			}
			else
			{
				$this->ar_img = false;
			}
		}
		
		function get_country()
		{
			//get country
			$query = "select t.*, s.V1 as LAND from `country` t 
										left join string s on s.S_TABLE='country' 
										and s.FK=t.ID_COUNTRY 
										and s.BF_LANG=if(t.BF_LANG & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG+0.5)/log(2)))
										where t.ID_COUNTRY = ".$this->res['FK_COUNTRY'];	
				
			$res_tmp = $this->db->fetch_table($query);
			$this->LAND = $res_tmp[0]['LAND'];
		}
		
		function create_all()
		{
			$this->detail_allgemein();
			$this->detail_details();
			$this->detail_mitarbeiter();
			$this->detail_referenzen();
		}
		
		function detail_allgemein()
		{
            global $ab_path;
			if ($this->LAND == NULL)
				$this->get_country();
			if (!count($this->ar_img))
				$this->get_img();
			
			$tpl_tmp = new Template($ab_path."tpl/".$this->s_lang."/firma_allgemein.htm");
			$tpl_tmp->addvars($this->res);
			$tpl_tmp->addvar('LAND', $this->LAND);
			if ($this->ar_img)
				$tpl_tmp->addvars($this->ar_img);
			$content = $tpl_tmp->process();
			$fp = fopen($ab_path.'cache/firmacache/firma'.$this->f_id.'_allgemein.php', w);
			fwrite($fp, $content);
			fclose($fp);
		}
		
		function detail_details()
		{
            global $ab_path;
			if ($this->LAND == NULL)
				$this->get_country();
			
			$tpl_tmp = new Template($ab_path."tpl/".$this->s_lang."/firma_details.htm");
			$tpl_tmp->addvars($this->res);
			$tpl_tmp->addvar('LAND', $this->LAND);
			$content = $tpl_tmp->process();
			$fp = fopen($ab_path.'cache/firmacache/firma'.$this->f_id.'_details.php', w);
			fwrite($fp, $content);
			fclose($fp);
		}
		
		function detail_mitarbeiter()
		{
            global $ab_path;
			//mitarbeiter liste holen
			$query = 'select ID_USER, NAME from user where FK_FIRMA = '.$this->f_id.' order by NAME';
			$ar_mitarbeiter = $this->db->fetch_table($query);
			
			$tpl_tmp = new Template($ab_path."tpl/".$this->s_lang."/firma_mitarbeiter.htm");
			$tpl_tmp->addvars($this->res);
			$tpl_tmp->addlist('liste', $ar_mitarbeiter, "tpl/".$this->s_lang."/firma_mitarbeiter.row.htm");
			$content = $tpl_tmp->process();
			$fp = fopen($ab_path.'cache/firmacache/firma'.$this->f_id.'_mitarbeiter.php', w);
			fwrite($fp, $content);
			fclose($fp);
		}
		
		function detail_referenzen()
		{
            global $ab_path;
			$tpl_tmp = new Template($ab_path."tpl/".$this->s_lang."/firma_referenzen.htm");
			$tpl_tmp->addvars($this->res);
			$content = $tpl_tmp->process();
			$fp = fopen($ab_path.'cache/firmacache/firma'.$this->f_id.'_referenzen.php', w);
			fwrite($fp, $content);
			fclose($fp);
		}
	//end class
	}
?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */


 #die("stop");
 class baum
 {
   
     var $db;
     var $table;
     var $active_node = array();
     var $affected = array();
     var $ar_baum = array();
     var $kids = array();
     var $parent = 0;
     var $level = 1;
	 var $langval;
	 var $ar_parents = array();
	 var $ar_path = array();
	 var $ebene = -1;
	 var $ar_striche = array();
	 var $tree_complete = array();
	 var $ar_baum_all=array();
	 var $ar_path_all = array();
	 var $copy_first_node = true;	//brauch ich fÃ¼r das kopieren von knoten
	 var $sysname = NULL;
   
   function baum($table,$node = false)
   {
     $this->db = &$GLOBALS['db'];
		 $this->langval = &$GLOBALS['langval'];
		 $this->table = $table;
		 $this->read_activeNode($node);
		 $this->sysname = "kat_".$this->table;
   # echo "THIS TABLE IST JETZT: ".$this->table."\n";
   }
	 
	 function read_activeNode($node)
	 {
		if ($node)
		{
			$this->active_node = $this->db->fetch1("select t.*,s.V1 from tree_".$this->table." t
																							left join string_tree_".$this->table." s on s.S_TABLE='tree_".$this->table."' 
																							and s.FK=t.ID_TREE_".strtoupper($this->table)." 
																							and s.BF_LANG=if(t.BF_LANG_TREE_".strtoupper($this->table)." & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_TREE_".strtoupper($this->table)."+0.5)/log(2)))		
																							where ID_TREE_".strtoupper($this->table)." = ".$node);
		}
		else
		{
			$this->active_node = array(
					 "ID_TREE_".strtoupper($this->table) => 0
				 );
		}
	 }
	 
   function read($id = false,$level = 0,$move=false)
   {
     #echo $id." :: LEVEL :: ".$level."<br>";
     if($id)
	 {
	   $this->ar_baum[] = $this->db->fetch1("select t.*, s.V1, ".$level." as level, 1 as is_current,
	   ".$this->active_node['ID_TREE_'.strtoupper($this->table)]." as ACTIVE_NODE
	   ".($move ? ",ID_TREE_".strtoupper($this->table)." as ID, ".$_REQUEST['ID_TREE_'.strtoupper($this->table)]." as ID_TREE_".strtoupper($this->table) : '')."
		 from `tree_".$this->table."` t 
	   left join string_tree_".$this->table." s 
	    on s.S_TABLE='tree_".$this->table."' 
		and s.FK=t.ID_TREE_".strtoupper($this->table)." 
		and s.BF_LANG=if(t.BF_LANG_TREE_".strtoupper($this->table)." & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_TREE_".strtoupper($this->table)."+0.5)/log(2)))
		where t.ID_TREE_".strtoupper($this->table)."=".$id);
		
		//pfad lesen
		$this->readPath($this->ar_baum[0]['ID_TREE_'.strtoupper($this->table)]);
	 }	 
	 if($id)
	   $level+=1;
	 $where = ($id ? " where  t.PARENT = ".$id : " where t.PARENT = 0");
	 
	 $query = "select t.*, s.V1, ".$level." as level,
	 ".$this->active_node['ID_TREE_'.strtoupper($this->table)]." as ACTIVE_NODE
	 ".($move ? ",t.ID_TREE_".strtoupper($this->table)." as ID, ".$_REQUEST['ID_TREE_'.strtoupper($this->table)]." as ID_TREE_".strtoupper($this->table) : '')."
	  ,count(distinct kid.PARENT) as kidcount	  
		from `tree_".$this->table."` t 
	   left join string_tree_".$this->table." s 
	    on s.S_TABLE='tree_".$this->table."' 
		and s.FK=t.ID_TREE_".strtoupper($this->table)." 
		and s.BF_LANG=if(t.BF_LANG_TREE_".strtoupper($this->table)." & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_TREE_".strtoupper($this->table)."+0.5)/log(2)))
	   left join tree_".$this->table." kid on t.ID_TREE_".strtoupper($this->table)." = kid.PARENT
		
		".$where."
	  group by t.ID_TREE_".strtoupper($this->table)."
		order by V1";
	 #die(dump($query));
	 $res = $this->db->querynow($query);
	 #echo ht(dump($res));
		 while($row = mysql_fetch_assoc($res['rsrc']))
		 {
			 #echo $row['V1']."\n";
			 
			 #echo '<a href="index.php?page=branchen&ID_INDUSTRYTREE='.$row['ID_INDUSTRYTREE'].'">'.$row['V1']."</a><br />";
			 $this->ar_baum[] = $row;
			 if(in_array($ropw['ID_TREE_'.strtoupper($this->table)], $this->affected) || $row['ID_TREE_'.strtoupper($this->table)] == $this->active_node)
				 $this->read($row['ID_TREE_'.strtoupper($this->table)], ($level+1));
		 }
		 if (($last=count($this->ar_baum)-1) != -1)
			$this->ar_baum[$last]['endoftree']=1;
   } // read
   
   function getAffected($id)
   {
     $this->affected[] = $id;
	 $parent = $this->db->fetch_atom("select PARENT from tree_".$this->table." where ID_TREE_".strtoupper($this->table)." = ".$id);
	 if($parent)
	 {
	   $this->affected[] = $parent;
	   $this->getAffected($parent);
     }
   }
   
   function hideNode($id)
   {
     $res = $this->db->querynow("update tree_".$this->table." set VISIBILITY=0 where ID_TREE_".strtoupper($this->table)."=".$id);
	 todo("Kategorien neu cachen", "cron/recache_kat.php", NULL, NULL, NULL, $this->sysname);
   }
   
   function showNode($id)
   {
     $res = $this->db->querynow("update tree_".$this->table." set VISIBILITY=1 where ID_TREE_".strtoupper($this->table)."=".$id);
     todo("Kategorien neu cachen", "cron/recache_kat.php", NULL, NULL, NULL, $this->sysname);
   }   
   
   function insert($ar)
   {
     $ar['PARENT']=$ar['ID_TREE_'.strtoupper($this->table)];
		 $ar['ID_TREE_'.strtoupper($this->table)]=NULL;
		 $this->db->update("tree_".$this->table, $ar);
     todo("Kategorien neu cachen", "cron/recache_kat.php", NULL, NULL, NULL, $this->sysname);
   }
   
   function moveNode($von,$nach)
   {
     $this->db->querynow("update tree_".$this->table." set
	   PARENT=".(int)$nach." where ID_TREE_".strtoupper($this->table)." = ".$von);
     todo("Kategorien neu cachen", "cron/recache_kat.php", NULL, NULL, NULL, $this->sysname);
   }
   
   function getKids($id)
   {
     $res = $this->db->querynow("select ID_TREE_".strtoupper($this->table)." from tree_".$this->table."
	   where PARENT=".$id);
		 while($row = mysql_fetch_assoc($res['rsrc']))
		 {
			 $this->kids[] = $row['ID_TREE_'.strtoupper($this->table)];
			 $this->getKids($row['ID_TREE_'.strtoupper($this->table)]);
		 }
   }
	 
	 function getParent()
	 {
		$query = "select t.*, s.V1 from tree_".$this->table." t 
							left join string_tree_".$this->table." s on s.S_TABLE='tree_".$this->table."' 
							and s.FK=t.ID_TREE_".strtoupper($this->table)." 
							and s.BF_LANG=if(t.BF_LANG_TREE_".strtoupper($this->table)." & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_TREE_".strtoupper($this->table)."+0.5)/log(2)))
							where ID_TREE_".strtoupper($this->table)."=".$this->active_node['PARENT'];
		return $this->db->fetch1($query);
	 }
	 
	 function delNode($id, $c_nodes = false)
	 {
		if (!$c_nodes)
		{
			$this->db->querynow("update tree_".$this->table." set PARENT = ".$this->active_node['PARENT']."
			where PARENT=".$this->active_node['ID_TREE_'.strtoupper($this->table)]);
			$this->db->delete("tree_".$this->table, $id);
		}
		else
		{
			$this->getKids($id);
			$this->kids[] = $id;
			$this->db->querynow("delete from string_tree_".$this->table." where FK in(".implode(",", $this->kids).")");
			$this->db->querynow("delete from tree_".$this->table." where ID_TREE_".strtoupper($this->table)." in(".implode(",", $this->kids).")");
		}
		
		$this->db->querynow("delete from string_tree_".$this->table." where FK NOT in (select  ID_TREE_".strtoupper($this->table)." from tree_".$this->table.")");
		
	   todo("Kategorien neu cachen", "cron/recache_kat.php", NULL, NULL, NULL, $this->sysname);
	 }
	 
	 function updateNode($ar)
	 {
		$this->db->update("tree_".$this->table, $ar);
		$this->read_activeNode($_POST['ID_TREE_'.strtoupper($this->table)]);
	   todo("Kategorien neu cachen", "cron/recache_kat.php", NULL, NULL, NULL, $this->sysname);
	 }
	 
	function cache_tree()
	{
		$this->db->querynow("truncate treekid_".$this->table);
		$this->fill_treekid();
	}
	
	function fill_treekid($id = 0)
	{
		$query = "select ID_TREE_".strtoupper($this->table).", VISIBILITY, PARENT from tree_".$this->table." 
		            where PARENT = ".(int)$id."
								order by ID_TREE_".strtoupper($this->table);
		$res = $this->db->fetch_table($query);
		if (count($res))
		{
			foreach($res as $node)
			{
				if ($node['VISIBILITY'] == 1)
				{
					$this->ar_parents[] = $node['ID_TREE_'.strtoupper($this->table)];
					$this->fill_treekid($node['ID_TREE_'.strtoupper($this->table)]);
				}
			}
		}
		else
		{
			for ($i = 0; $i < count($this->ar_parents); $i++)
				$this->db->querynow("insert into treekid_".$this->table." (ID_CAT, FK_CAT)
				                      values (".(int)$this->ar_parents[$i].", ".(int)$id.")");
				
			unset($this->ar_parents[(count($this->ar_params)-1)]);
		}
	}
	
	function readPath($id)
	{
		$q = "select t.*, s.V1 from `tree_".$this->table."` t 
					left join string_tree_".$this->table." s on s.S_TABLE='tree_".$this->table."' 
					and s.FK=t.ID_TREE_".strtoupper($this->table)."
					and s.BF_LANG=if(t.BF_LANG_TREE_".strtoupper($this->table)." & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_TREE_".strtoupper($this->table)."+0.5)/log(2)))		
					where t.ID_TREE_".strtoupper($this->table)." = ".(int)$id;
					
		//die($q);
		$res = $this->db->fetch1($q);
		
		#die(dump($res));
		
		if ($res)
		{
			if($res['ID_TREE_'.strtoupper($this->table)])
			{
			  #echo dump($res);
			  #echo "call : ".$id."\n\n";
			  $this->ar_path[] = $res['V1'];			  
			  $this->ar_path_all[] = $res;
			  $this->readPath($res['PARENT']);
		    }
		}
		else
		{
			$this->ar_path[] = "Wurzel";
			$this->ar_path = array_reverse($this->ar_path);
		}
		#$this->ar_path_all[] = $res;
	}
	
	function printTree()
	{
		$this->tree_complete[] = '<div><img src="gfx/path.66.png">Wurzel</div>';
		$this->readTree(0);
		return $this->tree_complete;
	}
	
	function readTree($id)
	{
		
		$q = "select t.*, s.V1, count(distinct kid.ID_TREE_".strtoupper($this->table).") as kidcount 
		
		from `tree_".$this->table."` t 
					
					left join string_tree_".$this->table." s on s.S_TABLE='tree_".$this->table."' 
					and s.FK=t.ID_TREE_".strtoupper($this->table)." 
					and s.BF_LANG=if(t.BF_LANG_TREE_".strtoupper($this->table)." & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_TREE_".strtoupper($this->table)."+0.5)/log(2)))
					left join tree_".$this->table." kid on kid.PARENT = t.ID_TREE_".strtoupper($this->table)."
					where t.PARENT = ".(int)$id."
					group by t.ID_TREE_".strtoupper($this->table)."
					order by s.V1";
		
		$res = $this->db->fetch_table($q);
		#die(dump($q));
		if ($res)
		{
			
			$tiefe = count($res);
			$this->ebene++;
			$this->ar_striche[$this->ebene] = 1;
			$ar_tmp = array();
			$ar_tmp2 = array();
			for ($i = 0; $i < $tiefe; $i++)
			{
				unset($ar_tmp);
				unset($ar_tmp2);
				
				
				//echo $res[$i]['V1']."\n";
				
				if($res[$i]['ID_TREE_SCRIPT'] > 0)
				{
				  $mykids = array();
				  $mykids[] = $res[$i]['ID_TREE_SCRIPT'];
				  $check = $this->db->querynow("select ID_TREE_SCRIPT from tree_script where `PARENT`=".$res[$i]['ID_TREE_SCRIPT']);
				  while($rowx=mysql_fetch_row($check['rsrc']))
				    $mykids[] = $rowx[0];
				  $res[$i]['scount'] = $this->db->fetch_atom("select count(ID_SCRIPT) from script
				    where OK=3 and FK_KAT IN(".implode(',', $mykids).")");
				} // ist ein script
				
				if($res[$i]['ID_TREE_TUTORIAL'] > 0)
				{
				  $mykids = array();
				  $mykids[] = $res[$i]['ID_TREE_TUTORIAL'];
				  $check = $this->db->querynow("select ID_TREE_TUTORIAL from tree_tutorial where `PARENT`=".$res[$i]['ID_TREE_TUTORIAL']);
				  while($rowx=mysql_fetch_row($check['rsrc']))
				    $mykids[] = $rowx[0];
				  $res[$i]['scount'] = $this->db->fetch_atom("select count(ID_TUTORIAL_LIVE) from tutorial_live
				    where OK=3 and FK_KAT IN(".implode(',', $mykids).")");
				} // ist ein script
				
				if($res[$i]['ID_TREE_JOB'] > 0)
				{
				  $mykids = array();
				  $mykids[] = $res[$i]['ID_TREE_JOB'];
				  $check = $this->db->querynow("select ID_TREE_JOB from tree_job where `PARENT`=".$res[$i]['ID_TREE_JOB']);
				  while($rowx=mysql_fetch_row($check['rsrc']))
				    $mykids[] = $rowx[0];
				  $res[$i]['scount'] = $this->db->fetch_atom("select count(ID_JOB_LIVE) from job_live
				    where OK=3 and (STATUS&1)=1 and FK_KAT IN(".implode(',', $mykids).")");
				  
				} // ist ein script				
				
				#echo $res[$i]['V1'].":: ".count($mykids)."\n";
				/*
				$ar_tmp[] = '<div>';
				for ($j = 0; $j < $this->ebene; $j++)
				{
					if ($this->ar_striche[$j] == 1)
						$ar_tmp[] = '<img src="gfx/path.3.png" width="19" height="17">';
					else
						$ar_tmp[] = '<img src="gfx/path.0.png" width="19" height="17">';
				}
				if ($i == $tiefe-1)
				{
					$this->ar_striche[$this->ebene] = 0;
					$ar_tmp[] = '<img src="gfx/path.9.png" width="19" height="17">';
				}
				else
				{
					$ar_tmp[] = '<img src="gfx/path.11.png" width="19" height="17">';
				}
				if ($res[$i]['kidcount'])
					$ar_tmp[] = '<img src="gfx/path.22.png" width="19" height="17">';
				else
					$ar_tmp[] = '<img src="gfx/path.20.png" width="19" height="17">';
				$ar_tmp[] = $res[$i]['V1']."</div>";
				
				*/
				
				//$this->tree_complete[] = implode("", $ar_tmp);
				$this->ar_baum_all[] = $res[$i];

				if ($res[$i]['kidcount'])
					$this->readTree($res[$i]['ID_TREE_'.strtoupper($this->table)]);
				if ($i == $tiefe-1)
					$this->ebene--;
			}
		}
	}

	function copyNode($was, $wohin)
	{
		$q = "select t.*, s.V1, kid.ID_TREE_".strtoupper($this->table)." as ID_kid from `tree_".$this->table."` t 
					left join string_tree_".$this->table." s on s.S_TABLE='tree_".$this->table."' 
						and s.FK=t.ID_TREE_".strtoupper($this->table)." 
						and s.BF_LANG=if(t.BF_LANG_TREE_".strtoupper($this->table)." & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_TREE_".strtoupper($this->table)."+0.5)/log(2)))
					left join tree_".$this->table." kid on kid.PARENT = t.ID_TREE_".strtoupper($this->table)."
					where t.ID_TREE_".strtoupper($this->table)." = ".(int)$was."
					order by kid.ID_TREE_".strtoupper($this->table);
		$res = $this->db->fetch_table($q);
		
		if ($this->copy_first_node && $wohin != 0)
		{
			$tmp_wohin = $wohin;
			$wohin = 0;
			$this->copy_first_node = false;
		}

		$q_ins = "insert into tree_".$this->table." (PARENT, BF_LANG_TREE_".strtoupper($this->table).", VISIBILITY)
								values (".(int)$wohin.", ".$this->langval.", ".$res[0]['VISIBILITY'].")";
		$ins_id = $this->db->querynow($q_ins);
		
		$q_str = "insert into string_tree_".$this->table." (S_TABLE, FK, BF_LANG, V1)
								values ('tree_".$this->table."', ".(int)$ins_id['int_result'].", ".$this->langval.", '".$res[0]['V1']."')";
		$t = $this->db->querynow($q_str);

		foreach ($res as $node)
		{
			if ($node['ID_kid'] != NULL)
				$this->copyNode($node['ID_kid'], $ins_id['int_result']);
		}
		if (isset($tmp_wohin))
			$this->db->querynow("update tree_".$this->table." set PARENT = ".(int)$tmp_wohin." where ID_TREE_".strtoupper($this->table)." = ".(int)$ins_id['int_result']);
	
	  todo("Kategorien neu cachen", "cron/recache_kat.php", NULL, NULL, NULL, $this->sysname); 
	
	}
    
 } // class
 
?>
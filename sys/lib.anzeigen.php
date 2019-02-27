<?php
/* ###VERSIONSBLOCKINLCUDE### */


  function items_callback(&$row, $i)
  {
    global $nar_systemsettings;
    if ($row['SET_OPTIONS'])
    {
      $ar_tmp = explode(",", $row['SET_OPTIONS']);
      foreach ($ar_tmp as $k=>$v)
        $row[$v] = true;
    }
#    $row['BORDERCOLOR'] = $nar_systemsettings['ANZEIGE']['BORDERCOLOR']; # anzeigenmarkt.php (per style class)
#    $row['BORDERWIDTH'] = $nar_systemsettings['ANZEIGE']['BORDERWIDTH']; # anzeigenmarkt.php (per style class)
    $row['HIGHLIGHT'] = $nar_systemsettings['ANZEIGE']['HIGHLIGHT'];
    $row['THUMBSIZE'] = $nar_systemsettings['ANZEIGE']['THUMBSIZE'];
    $row['THUMBPATH'] = $nar_systemsettings['ANZEIGE']['USERPATH'];
#    foreach ($nar_systemsettings['ANZEIGE'] as $k=>$v) $row[$k] = $v;
  }
  
  function anzeigen_free($id_user = -1)
  {
  	global $db, $uid, $nar_systemsettings;
  	$id_user = ($id_user > 0 ? $id_user : $uid);
  	if ($nar_systemsettings["MARKTPLATZ"]["FREE_ADS"]) {
  		// Anzeigen sind allgemein kostenlos
  		return true;
  	} else {
  		// Globales "Kostenlos-Flag" nicht gesetzt
  		$user_free = $db->fetch_atom("
  			SELECT
  				FREE_ADS
  			FROM
  				`usergroup`
  			WHERE
  				ID_USERGROUP=(SELECT FK_USERGROUP FROM `user` WHERE ID_USER=".$id_user.")");
  		if ($user_free) {
  			// Benutzergruppe darf kostenlos anzeigen schalten
  			return true;
  		} else {
	  		// Benutzergruppe darf keine kostenlosen anzeigen schalten
	  		$user_free_days = $db->fetch_atom("
	  			SELECT
	  				FREE_DAYS
	  			FROM
	  				`usergroup`
	  			WHERE
	  				ID_USERGROUP=(SELECT FK_USERGROUP FROM `user` WHERE ID_USER=".$id_user.")");
	  		if ($user_free_days > 0) {
	  			// Kostenloser Testzeitraum ist vorhanden
	  			$free_days_gone = $db->fetch_atom("SELECT DATEDIFF(NOW(), STAMP_REG) FROM `user` WHERE ID_USER=".$id_user);
	  			if ($free_days_gone < $user_free_days) {
	  				// Kostenloser Testzeitraum ist noch gültig!
	  				return true;
	  			}
	  		} 
  		}
  	}
  	return false;
  }

  function anzeigen_constraint($search = array (), $kat)
  {
    global $db;
    static $nar_msg = NULL;
    if (is_null($nar_msg)) $nar_msg = get_messages('FORMS');

    $id_kat = &$kat['ID_KAT'];

    $tmp = ($uid
      ? $db->fetch_nar("select FK_ROLE,FK_ROLE from role2user where FK_USER=". $uid)
      : array (1)
    );

    // hat der User Zugriff auf die Kategorie?
    if ($n_roles = count($tmp))
    {
      if ($db->fetch_atom("select count(*) from katperm2role
        where FK_KAT=". $id_kat. " and FK_ROLE in (". implode(', ', $tmp). ")")
        == $n_roles
      )
        return false;
    }
    else
      return false;

    $nar_val = (is_array ($search['attr']) ? $search['attr'] : array ());

// Einschraenkung ueber Bereiche/SEL/MSEL --------------------------------------
    $lu_attr_typ = $db->fetch_nar($db->lang_select('lookup', 'ID_LOOKUP, VALUE'). "
      where art='attr_type'");
    $lft = (int)$db->fetch_atom("select g.LFT from kat k, attr_group g
      where g.ROOT=".$kat['ROOT']." and ". $kat['LFT']. " between k.LFT and k.RGT
        and FK_ATTR_GROUP=ID_ATTR_GROUP and k.ROOT=". $kat['ROOT']. "
      order by g.RGT asc limit 1");

    $nar_attr_group = $db->fetch_nar($db->lang_select("attr_group","ID_ATTR_GROUP,ID_ATTR_GROUP"). "
      where ".$lft." between LFT and RGT order by LFT");

    if (count($nar_attr_group))
    {
      $ar_data = $db->fetch_table($db->lang_select('attr', '*,B_MANDATORY,B_SEARCH,g.LFT'). '
        left join attr2group z on z.FK_ATTR=t.ID_ATTR
        left join attr_group g on ID_ATTR_GROUP=FK_ATTR_GROUP
        where '. $lft. ' between LFT and RGT
        order by LFT', 'ID_ATTR');
        // B_SEARCH=0 rauswerfen
        $ar_tmp = $ar_data; $ar_data = array ();
        foreach($ar_tmp as $k=>$v) if ($v['B_SEARCH']) $ar_data[$k] = $v;
    }
    else
      $ar_data = array ();
#echo listtab($ar_data);
    $ar_constraint = $ar_val = $nar_opts = array ();
    if (count($ar_data))
    {
      $res = $db->querynow($db->lang_select('attr_option', 'ID_ATTR_OPTION, FK_ATTR, VALUE, LABEL'). '
        where FK_ATTR in ('. implode(', ', array_keys($ar_data)). ')
        order by FK_ATTR, POS'
      );
      $fk_old = -1;
      do
      {
        list ($id_attr_opt, $fk_attr, $s_value, $s_label) = mysql_fetch_row($res['rsrc']);
        $nar_opts[$fk_attr][$id_attr_opt] = $s_value; // fuer Such-Query (Bereiche)
        if ($fk_attr != $fk_old)
        {
          if ($fk_old>0)
          {
            $ar_tmp[] = '</select>';
#          $ar_tmp[] = '</td></tr>';
            array_unshift($ar_tmp, '<div class="attropt">');
            $ar_tmp[] = '</div>';
            $ar_constraint[$fk_old] = $ar_tmp;
#echo dump($fk_old), dump($fk_attr), '<br />';
          }
#        $ar_tmp = array ('<tr><th>'. stdHtmlentities($ar_data[$fk_attr]['V1']). '</th></tr><tr><td>');
          $ar_tmp = array (''. stdHtmlentities($ar_data[$fk_attr]['V1']). '<br />');
          $fk_old = $fk_attr;
/**/
          $ar_tmp[] = '<select id="a'. $fk_attr. '" name="s_attr['. $fk_attr. ']">
  <option value="">'. $nar_msg['select_none']. '</option>';
        }
        if ($id_attr_opt)
        {
          $ar_tmp[] = '
  <option '. (
    (is_array ($nar_val[$fk_attr]) ? in_array ($id_attr_opt, $nar_val[$fk_attr]) : $id_attr_opt==$nar_val[$fk_attr])
    ? 'selected ' : ''). 'value="'. $id_attr_opt. '">'. stdHtmlentities($s_label). '</option>';
/*/
        }
        if ($id_attr_opt)
        {
          $b_checked = is_array ($nar_val[$fk_attr]) && in_array ($id_attr_opt, $nar_val[$fk_attr]);
#if (1==$fk_attr && $b_checked) echo implode(', ', $nar_val[$fk_attr]), ': ', $id_attr_opt, '<br />';
          if ($b_checked)
            $ar_val[$fk_attr][] = $id_attr_opt;
          $ar_tmp[] = '<input type="checkbox" class="nob" id="a'. $id_attr_opt
            . '" name="s_attr['. $fk_attr. '][]" '. ($b_checked ? 'checked ' : '')
            . 'value="'. $id_attr_opt. '"/>&nbsp;<label for="a'. $id_attr_opt. '">'
            . stdHtmlentities($s_label). '</label><br />';
/**/
        }
      } while ($id_attr_opt);
#echo ht(dump($nar_opts));
      // INT/FLT-Felder ohne Optionen
#      $ar_moreattr = array_diff(array_keys($ar_data), array_keys($ar_constraint));
      $ar_attrlist = array ();
#echo php_dump(array_keys($ar_constraint));
#echo ht(dump($ar_data));
#echo ht(dump($nar_val)), '<hr>';
      foreach($ar_data as $fk_attr => $attr)
        if ($ar_constraint[$fk_attr])
        {
          $ar_attrlist[] = $ar_constraint[$fk_attr];
          if ($nar_val[$fk_attr])
            $ar_val[$fk_attr] = '='. $nar_val[$fk_attr];
        }
        else
        {
          $ar_attrlist[] = ''. stdHtmlentities($attr['V1']). '<br />'. '
            <input type="text" size="4" id="a'. $fk_attr. 'a" name="s_attr['. $fk_attr. 'a]" value="'. stdHtmlentities($nar_val[$fk_attr.'a']). '" />
            bis <input type="text" size="4" id="a'. $fk_attr. 'z" name="s_attr['. $fk_attr. 'z]" value="'. stdHtmlentities($nar_val[$fk_attr.'z']). '" /> '
            . stdHtmlentities($attr['V2']);
          if ($tmp = $nar_val[$fk_attr.'a'])
            if ($t2 = $nar_val[$fk_attr.'z'])
              $ar_val[$fk_attr] = ' between '. $tmp. ' and '. $t2;
            else
              $ar_val[$fk_attr] = '>='. $tmp;
          else
            if ($t2 = $nar_val[$fk_attr.'z'])
              $ar_val[$fk_attr] = '<='. $t2;
        }
#echo '<hr>', ht(dump($ar_attrlist));
      $ar_constraint = &$ar_attrlist;
#echo ht(dump($ar_constraint));
    }

// Kategorien ermitteln --------------------------------------------------------
    $ar_where = array ('BF_VIS=3 and (now() between STAMP_START and STAMP_END)');
    $nar_kat = $db->fetch_nar("select k.LFT, k.ID_KAT, ifnull(min(p.B_VIS),1) B_SHOW from kat k
      left join kat p on p.ROOT=k.ROOT and k.LFT between p.LFT and p.RGT
      where k.ROOT=1 and k.LFT"
      . ($id_kat && $kat ? " between ". $kat['LFT']. " and ". $kat['RGT'] : '=1'). '
      group by k.ID_KAT having B_SHOW'
    );
    if (!$GLOBALS['katperms']) $GLOBALS['katperms'] = katperm_read();
    $nar_kat = array_intersect($nar_kat, array_keys($GLOBALS['katperms']));
    $ar_where[] = (count($nar_kat) ? 'FK_KAT in ('. implode(', ', $nar_kat). ')' : '0');

    // Attribute
#echo ht(dump($ar_val)), '<hr />';
#echo ht(dump($ar_where));
#echo ht(dump($ar_val));
    $ar_joins = array ();
#echo '<h1>huhu</h1>';
    if (count($ar_val))
    {
      $nar_attr = $db->fetch_table("select a.*, if('MSEL'=u.VALUE, 'sel', lower(u.VALUE)) as TYPE
        from attr a, lookup u where u.ID_LOOKUP=a.LU_TYPE", 'ID_ATTR');
      foreach($ar_val as $fk_attr => $ar_opts)
      {
        if (!is_array ($ar_opts))
        {
          $s_type = $nar_attr[$fk_attr]['TYPE'];
          if ($ar_opts)
          {
#echo 'huhu', $fk_attr, ht(dump($ar_opts));
            $ar_joins[] = '
              left join attr_val_'. $s_type. ' a'. $fk_attr. '
                on a'. $fk_attr. ".S_TABLE='anzeige' and a"
              . $fk_attr. '.FK=ID_ANZEIGE and a'. $fk_attr. '.FK_ATTR='. $fk_attr;
            $ar_where[] = '(a'. $fk_attr. '.VALUE is null or a'
              . $fk_attr. '.VALUE'. $ar_opts. ')';
          }
        }
        elseif (count($nar_opts[$fk_attr]) > count($ar_opts))
        {
          $s_type = $nar_attr[$fk_attr]['TYPE'];
          $ar_joins[] = '
            left join attr_val_'. $s_type. ' a'. $fk_attr
              . ' on a'. $fk_attr. ".S_TABLE='anzeige' and a"
              . $fk_attr. '.FK=ID_ANZEIGE and a'. $fk_attr. '.FK_ATTR='. $fk_attr;
          switch($s_type)
          {
            case 'int':
            case 'flt':
              $ar_range = $ar_or = array ();
              $min = 0;
              foreach($nar_opts[$fk_attr] as $id_opt=>$max)
              {
                if (in_array ($id_opt, $ar_opts))
                  if (($n=count($ar_range)) && $ar_range[$n-1][1]==$min)
                    $ar_range[$n-1][1] = $max;
                  else
                    $ar_range[] = array ($min, $max);
                $min = $max;
              }
              foreach($ar_range as $range)
                if ($range[0])
                  $ar_or[] = '(a'. $fk_attr. '.VALUE>'. $range[0]
                    . ' and a'. $fk_attr. '.VALUE<='. $range[1]. ')';
                else
                  $ar_or[] = 'a'. $fk_attr. '.VALUE<='. $range[1];
              $ar_where[] = '('. implode(' or ', $ar_or). ' or a'. $fk_attr. '.VALUE is null)';
              break;
            case 'sel':
              $ar_opts[] = 'null';
              $ar_where[] = 'a'. $fk_attr. '.VALUE'. (count($ar_opts)>1
                ? ' in ('. implode(', ', $ar_opts). ')'
                : '='. $ar_opts[0]
              );
          } // end switch (s_type)
        }
      }
#echo ht(dump($ar_joins));
    }

    $s_where = (count($ar_joins)
      ? implode('', $ar_joins). '
        where '. implode('
          and ', $ar_where)/*. '
        group by ID_ANZEIGE'*/
      : '
        where '. implode('
          and ', $ar_where)
    );
#echo ht(dump($s_where));
    return array (
      $ar_constraint,
      $s_where
    );
  }

  function anzeigen_delete($anz)
  {
    global $db, $nar_systemsettings;
    if (is_array ($anz) && !empty($anz))
      $anz = implode(",", $ar = $anz);

    $liste=$db->fetch_table("select * from img where FK_ANZEIGE in(".$anz.")");
    $err=array ();
    for ($i=0; $i<count($liste); $i++)
    {
      $un = @unlink($nar_systemsettings['ANZEIGE']['USERPATH']."/".$liste[$i]['SRC']);
      if ($un)
        $un = @unlink($nar_systemsettings['ANZEIGE']['USERPATH']."/thumbs/".$liste[$i]['SRC']);
      if ($un)
        $del[]=$liste[$i]['ID_IMG'];
     else
       $err[] = array ("ID" => $liste[$i]['ID-IMG'], "SRC" => $liste[$i]['SRC']);
    }
    if (count($del))
      $db->querynow("delete from img where ID_IMG in(".implode(",", $del).")");
    if (count($err))
     kmail('ebiz-trader', 0, 'Dateifehler beim Bild loeschen', 
       "Eines oder mehrere Bilder konnten nicht geloescht werden: ".ht(dump($err))."
     ---\r\nDiese Mail wurde automatisch erstellt");
    if(!isset($ar))
    {
      $db->delete("anzeige",$anz);
      $db->querynow("delete from anzwatch where FK_ANZEIGE=".$anz); 
    }
    else
    {
      foreach($ar as $key => $value)
      {
        $de=$db->delete("anzeige", $value);
        $db->querynow("delete from anzwatch where FK_ANZEIGE=".$value);  
      }
    }
    return true;
  }

  function anzeigen_setstat($anz, $mode)
  {
    global $db;
    if($mode == "deak")
      $value=0;
    else
    {
      if($GLOBALS['nar_systemsettings']['ANZEIGE']['FREISCHALTEN'] == 1)
        $value =2;
      else
        $value=3;
    }
# echo ht(dump($anz)); 
    if(is_array ($anz) && !empty($anz))
      $anz = implode(",", $anz);
    else
      $anz = (int)$anz; 
    $db->querynow("update anzeige set BF_VIS=".$value." where ID_ANZEIGE IN(".$anz.")");
# echo ht(dump($GLOBALS['lastresult'])); 
    return true; 
  }
  
function anzImgDel($param)
{
 global $nar_systemsettings,$db;
 if(is_array($param))
   $param = implode(",", $param); 
 
 $im=$db->fetch_table("select * from img where ID_IMG in(".$param.")");
 for($i=0; $i<count($im); $i++)
 {
  $unl = unlink($nar_systemsettings['ANZEIGE']['USERPATH']."/thumbs/".$im[$i]['SRC']);
  if($unl)
    $unl = unlink($nar_systemsettings['ANZEIGE']['USERPATH']."/".$im[$i]['SRC']);  	
 }
 $db->querynow("delete from img where ID_IMG in(".$param.")"); 
   
}

?>
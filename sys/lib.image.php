<?php
/* ###VERSIONSBLOCKINLCUDE### */



class image
{
	### Vars
	### Private
	private $db = false;
	private $file_name;
	private $org_name = false;
	private $ar_img;
	private $ar_img_type = array();
	private $autostart = false;
	private $ar_bildformat = array();
	private $path = 'uploads/images';
	private $path_ok = false;

	### public
	public $err = array();
	public $img = false;
	public $thumb = false;

	public function image($id_bildformat = 0, $path = false, $autostart = false)
	{
	 $this->db = &$GLOBALS['db'];
	 $this->ar_img_type	= array
	 (
	 	1,2,3
	 );
	 $this->autostart=$autostart;
	 if($id_bildformat > 0)
	 {
	 	$this->ar_bildformat = $this->db->fetch1("select * from bildformat
	     where ID_BILDFORMAT=".(int)$id_bildformat);
	 } // bildformat
	 if($path)
	 $this->path = $path;
	} // image()

	### methoden
	public function check_file($file = false)
	{
	 $this->org_name = $file['name'];
	 if($file)
	 {
	 	$this->ar_img = getimagesize($file['tmp_name']);
	 	#echo ht(dump($this->ar_img));
	 	if(in_array($this->ar_img[2], $this->ar_img_type))
	 	{
	 		$this->file_name = $file['tmp_name'];
	 		if($this->autostart)
	 		{
		   $this->handle_img();
	 		} // autostart
	 	} // ist image
	 	else
	 	$this->err[] = "NOT_AN_IMG";
	 } // file ist true
	} // check_file()

	private function handle_img()
	{
		if(!empty($this->ar_bildformat))
	 {
	 	$this->calc_size();
	 	#echo "TEST: ".ht(dump($this->ar_img));
	 	$this->create();
	 	if($this->ar_bildformat['B_THUMB'])
	 	{
	 		$this->calc_size(true);
	 		$this->create(true);
	 	}
	 } // bild muss verarbeitet werden
	 else
	 $this->move_img();
	} // handle_img()

	private function move_img()
	{
		$this->check_path();
	 if($this->path_ok)
	 {
	 	$file_new_name = $this->create_filename();
	 	$move = @move_uploaded_file($this->file_name, $this->path."/".$file_new_name);
	 	if($move)
	 	{
	 		$this->img = $this->path."/".$file_new_name;
	 	}
	 	else
	 	{
	 		$this->err[] = "MOVE_FAILED";
	 		$this->img = false;
	 	}
	 } // path ok
	} // move_img()

	private function create_filename($thumb = false)
	{
		$hack = explode(".", $this->org_name);
		$c = count($hack)-1;

		$name = microtime();
		$name = str_replace(" ", "_", $name);
		return $file_new_name = ($thumb ? 'thumb_' : '').$name.".".$hack[$c];
	} // create_filename();

	private function create($thumb = false)
	{
        global $nar_systemsettings;

        $binConvert = $nar_systemsettings['SYS']['PATH_CONVERT'];

	 #echo ht(dump($this->ar_img));
	 $w = $this->ar_img['NEW_B'];
	 $h = $this->ar_img['NEW_H'];
	 $dst_x = $dst_y = 0;

	 if($this->ar_bildformat['T_QUADRAT'] >= 1)
	 {
	 	if(!$thumb && $this->ar_bildformat['T_QUADRAT'] == 2)
	 	{
	 		$w = $this->ar_bildformat['MAX_B'];
	 		$h = $this->ar_bildformat['MAX_H'];
	 		if($this->ar_img['NEW_B'] < $this->ar_img['NEW_H'])
	 		{
		   		$x = $w-$this->ar_img['NEW_B'];
		   		$dst_x = round($x/2);
	 		} // Hochkantbild
	 		else
	 		{
		   		$y = $h-$this->ar_img['NEW_H'];
		   		$dst_y = round($y/2);
	 		} // querformatbild
	 	} // großes bild
	 	elseif($thumb)
	 	{
	 		$w = $this->ar_bildformat['MAX_TB'];
	 		$h = $this->ar_bildformat['MAX_TH'];
	 		if($this->ar_img['NEW_B'] < $this->ar_img['NEW_H'])
	 		{
		   		$x = $w-$this->ar_img['NEW_B'];
		   		$dst_x = round($x/2);
		   		//echo $dst_x."<hr />";
	 		} // Hochkantbild
	 		else
	 		{
		   		$y = $h-$this->ar_img['NEW_H'];
		   		$dst_y = round($y/2);
		   		//echo $this->ar_img['NEW_H']."<hr />";
	 		} // querformatbild
	 	} // thumb
	 } // ränder füllen
     $backgroundOption = " -background \"rgb(255,255,255)\" -alpha remove -alpha off";
	 if ($this->ar_bildformat["LU_RGBFARBE"]) {
         $color = $GLOBALS['db']->fetch_atom("select VALUE from lookup where ID_LOOKUP=".$this->ar_bildformat['LU_RGBFARBE']);
         $hack = explode(".", $color);
         if (count($hack) > 3) {
             // Keep alpha
             $backgroundOption = " -background \"rgba(".(int)$hack[0].",".(int)$hack[1].",".(int)$hack[2].",".sprintf("%.2f", (float)$hack[3] / 255).")\" -alpha remove -alpha background";
         } else {
             $backgroundOption = " -background \"rgb(".(int)$hack[0].",".(int)$hack[1].",".(int)$hack[2].")\" -alpha remove -alpha background";
         }
     }
	 if($thumb)
	 {
	 	$new_name = $this->create_filename(true);
	 	//imagecopyresized($im, $im_old,$dst_x,$dst_y,0,0,$this->ar_img['NEW_B'], $this->ar_img['NEW_H'],$this->ar_img[0],$this->ar_img[1]);
	 	//$image = imagejpeg($im, $this->path."/".$new_name, 100);
	 	$this->thumb = $this->path."/".$new_name;
	 	$from = $this->file_name;
	 	$to = $this->thumb;
	 	$opt = $this->ar_bildformat;

	 	if($this->ar_bildformat['T_QUADRAT'] >= 1)
	 	{
	 		// quadrat
	 		if($this->ar_img[0] > $this->ar_img[1])
	 		{
	 			$str 	= "$binConvert -resize x".$opt['MAX_TH']." ".$from;
	 		} // querformat
	 		else
	 		{
	 			$str 	= "$binConvert -resize ".$opt['MAX_TB']."x ".$from;
	 		}	// hochkant
	 		$str   .= $backgroundOption." -gravity center  -crop ".$opt['MAX_TB']."x".$opt['MAX_TB']."+0+0 +repage   ".$to;
	        $str   .= " -density 72x72 -thumbnail ".$opt['MAX_TB']."x".$opt['MAX_TB']." ".$to;
	 	}
	 	else
	 	{
	 		// kein quadrat
	 		$handle_size = ($opt['MAX_TB']*3)."x".($opt['MAX_TH']*3);
	 		$str = "$binConvert -size ".$handle_size." ".$from.$backgroundOption." -density 72x72 -thumbnail ".$opt['MAX_TB']."x".$opt['MAX_TH']." ".$to;
	 	}
	 }
	 else
	 {
	 	$new_name = $this->create_filename();
	 	$from = $this->file_name;
	 	$to = $this->path.'/'.$new_name;
	 	$opt = $this->ar_bildformat;
		 
		if (($opt['MAX_B'] > 0) && ($opt['MAX_H'] > 0)) {
			$handle_size = "-size ".($opt['MAX_B'] > 0 ? $opt['MAX_B']*3 : "").($opt['MAX_H'] > 0 ? "x".$opt['MAX_H']*3 : "");
			$str = "$binConvert ".$handle_size." ".$from.$backgroundOption." -density 72x72 -thumbnail ".$opt['MAX_B']."x".$opt['MAX_H']." ".$to;
		 	$this->img = $this->path."/".$new_name;
		} else {
			$this->img = $this->path."/".$new_name;
			copy($from, $this->img);
		}
	 	//echo $str."<hr />";
	 } // kein thumb
        #eventlog("error", "IMAGEMAGICK DEBUG", $str);
    system($str);
    @chmod($to, 0777);
	} // create()

	private function check_path()
	{
		if(is_dir($this->path))
	 {
	 	if(is_writeable($this->path))
	 	{
	 		$this->path_ok = true;
	 	}
	 	else
	 	{
	 		$this->err[] = 'NOT_WRITEABLE';
	 		$this->path_ok = false;
	 	} // nicht schreibbar
	 } // is dir
	 else
	 {
	 	die("BAUSTELLE PFADE");
	 } // kein dir
	} // check_path()

	public function img_db()
	{
		echo "img_db() is a dummy!";
	} // img_db();

	private function calc_size($thumb = false)
	{
		if(!$thumb)
		{
			if($this->ar_bildformat['MAX_B'])
			{
			 if($this->ar_img[0] > $this->ar_bildformat['MAX_B'])
			 {
			 	$teiler = $this->ar_img[1]/$this->ar_img[0];
			 	$this->ar_img['NEW_B'] = $this->ar_bildformat['MAX_B'];
			 	$this->ar_img['NEW_H'] = round($this->ar_img['NEW_B']*$teiler);
			 } // ist größer
			 else
			 {
			 	$this->ar_img['NEW_B'] = $this->ar_img[0];
			 	$this->ar_img['NEW_H'] = $this->ar_img[1];
			 } // max nicht überschritten
			} // max. Breite gegeben
			else
			{
			 $this->ar_img['NEW_B'] = $this->ar_img[0];
			 $this->ar_img['NEW_H'] = $this->ar_img[1];
			}
			if($this->ar_bildformat['MAX_H'])
			{
			 if($this->ar_img['NEW_H'] > $this->ar_bildformat['MAX_H'])
			 {
			 	$teiler = $this->ar_img['NEW_B']/$this->ar_img['NEW_H'];
			 	$this->ar_img['NEW_H'] = $this->ar_bildformat['MAX_H'];
			 	$this->ar_img['NEW_B'] = round($this->ar_img['NEW_H']*$teiler);
			 } // ist größer
			} // max Höhe gegeben
		}
		### THUMB
		if($thumb)
		{
			if($this->ar_bildformat['B_THUMB'])
			{
			 if($this->ar_img[0] > $this->ar_bildformat['MAX_TB'])
			 {
			 	$teiler = $this->ar_img[1]/$this->ar_img[0];
			 	$this->ar_img['NEW_B'] = $this->ar_bildformat['MAX_TB'];
			 	$this->ar_img['NEW_H'] = round($this->ar_img['NEW_B']*$teiler);
			 } // ist größer
			 else
			 {
			 	$this->ar_img['NEW_B'] = $this->ar_img[0];
			 	$this->ar_img['NEW_H'] = $this->ar_img[1];
			 } // max nicht überschritten
			} // max. Breite gegeben
			else
			{
			 $this->ar_img['NEW_B'] = $this->ar_img[0];
			 $this->ar_img['NEW_H'] = $this->ar_img[1];
			}
			if($this->ar_bildformat['MAX_TH'] && $thumb)
			{
			 if($this->ar_img['NEW_H'] > $this->ar_bildformat['MAX_TH'])
			 {
			 	$teiler = $this->ar_img['NEW_B']/$this->ar_img['NEW_H'];
			 	$this->ar_img['NEW_H'] = $this->ar_bildformat['MAX_TH'];
			 	$this->ar_img['NEW_B'] = round($this->ar_img['NEW_H']*$teiler);
			 	#echo "<b>".ht(dump($this->ar_img));
			 } // ist größer
			} // max Höhe gegeben
		} // thumb!
	} // calc_size()

} // class image

?>
<?php
/* ###VERSIONSBLOCKINLCUDE### */


  function createPath($path, $writeable=false, $base = NULL)
  {
  	$path = str_replace($base, "", $path);
  	 
  	$hack = explode("/", trim($path));
  	 
  	for($i=0; $i<count($hack); $i++)
  	{		
  		if($hack[$i] === '')
  		{
  		   continue;
  		}
  		$run .= $hack[$i]."/";
  		if(!is_dir($base.$run))
  		{
  			var_dump(system("mkdir ".$base.$run."\n"));
  
  			if(!is_dir($base.$run))
  			{
  				die("system() not working in createPath()");
  			}
  			if($writeable)
  			{
  			   	system("chmod 0777 ".$base.$run."\n");
  				#die("here i am");
  			}
  		} // not a dir	   
  	} // for hack
  } // createPath()
?>
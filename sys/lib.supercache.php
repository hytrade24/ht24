<?php
/* ###VERSIONSBLOCKINLCUDE### */


class supercache {

	function supercache() 
	{
		
	}
	
	function cacheTutorials() 
	{
        global $ab_path, $db;
		require_once($ab_path.'sys/lib.tutorial.php');
		$myTut = new tutorial($uid);
		$allTutIds = $db->fetch_table("SELECT ID_TUTORIAL FROM tutorial WHERE STATUS=1");
		foreach($allTutIds as $thisID) 
		{
			$myTut->createHTMLfile( $thisID );
		}
	}
	
}

?>
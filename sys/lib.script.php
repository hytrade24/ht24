<?php
/* ###VERSIONSBLOCKINLCUDE### */



 class script
 {
	
	### Conf.	
	private $admin_freigabe = false;
	// ^^ auf true setzen wenn Adminfreigabe automatisch erfolgen soll
	private $admin_mail = false;    
	
	### private
	private $id_user;
	private $lang = 'de';
	private $langval = 128;
	
	### public
	public $ar_script = array();
	public $id_script = 0;
	
	### Konstruktor
	public function script($id_script=NULL)
	{
	  $this->id_user = ($GLOBALS['uid'] ? $GLOBALS['uid'] : 0);
	  if($script)
	    $this->id_script=$script;
	  $this->lang = $GLOBALS['s_lang'];
	  $this->langval = $GLOBALS['langval'];
	} // script()
	
	### methoden
	public function getScript($id=0)
	{
	   ### Holt ein Script aus der DB
	   ### Wird in ar_script abgelegt
	} // getScript()
	
	public function handleScript($what = 'update', $id=NULL, $ar = false)
	{
	  $seach_update = false;
	  
	  $this->checkScriptOwner();
	  
	  switch($what)
	  {
	    case 'update':
		 $this->updateScript($id);
		 break;
		case 'new': 
		 $this->newScript($ar);
		 break;
		case: 'delete':
		 $this->deleteScript($id);
		 break; 
	  } // switch
	} // handleScript
	
	private function checkScriptOwner()
	{
	
	} // checkScriptOwner
	
	private function updateScript()
	{
	
	} // updateScript()
	
	private function newScript()
	{
	
	} // newScript()
	
	private function scriptFreigabe()
	{
	
	} // scriptFreigabe()
	
	private function handleSearch($aktion = 'update')
	{
	
	} // handle seacrh()
	
	private function adminMail()
	{
	
	} // adminMail()
	
 } // class script
 
 
?>
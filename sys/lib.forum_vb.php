<?php
/* ###VERSIONSBLOCKINLCUDE### */

class ForumVB {
	private 	$vb_config 			= array();
	private		$errors_critical	= array();

	function __construct() {
        global $ab_path;

		if (empty($_SESSION["vb_api"])) {
			// Create new API-Session
			include $ab_path.'conf/inc.forum_vb.php';
			$this->vb_config = $ar_vb;
			$this->InitializeAPI("TestClient", "1.0", "Linux", "1.0", time());
			$_SESSION["vb_api"] = $this->vb_config;
		} else {
			$this->vb_config = $_SESSION["vb_api"];
		}
		$this->errors_critical = array("invalid_clientid", "invalid_accesstoken", "invalid_api_signature", "missing_api_signature");
	}

	private function ExecuteMethod($methodName, $methodParams = array(), $methodParamsPost = array()) {
		$methodParams['api_m'] = $methodName;
		$params = http_build_query($methodParams, '', '&');
		$sign = md5($params.$this->vb_config["API_TOKEN"].$this->vb_config["API_CLIENT_ID"].
					$this->vb_config["API_SECRET"].$this->vb_config["API_KEY"]);
		$methodParams['api_c'] = $this->vb_config["API_CLIENT_ID"];
		$methodParams['api_s'] = $this->vb_config["API_TOKEN"];
		$methodParams['api_v'] = $this->vb_config["API_VERSION"];
		$methodParams['api_sig'] = $sign;
		$params = http_build_query($methodParams, '', '&');

		if (!empty($methodParamsPost)) {
			// POST-REQUEST
			$url = $this->vb_config["API_URL"]."?".$params;
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($methodParamsPost, '', '&'));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$json_raw = curl_exec($ch);
		} else {
			// GET-REQUEST
			$url = $this->vb_config["API_URL"]."?".$params;
			$json_raw = @file_get_contents($url);
		}
		if ($json_raw !== false) {
			// Antwort erfolgreich abgerufen
			$json = json_decode($json_raw, true);
			if (!empty($json['response']['errormessage'][0]) && in_array($json['response']['errormessage'][0], $this->errors_critical)) {
				// Kritischen Fehler erhalten
				if ($json['response']['errormessage'][0] == "invalid_accesstoken") {
					// Remove API-Session
					unset($_SESSION["vb_api"]);
				}
	  			echo("<strong>FATAL ERROR: </strong>");
	  			die(var_dump($json));
				return false;
			}
			return $json;
		}
	}

	private function InitializeAPI($client_name, $client_version, $platform_name, $platform_version, $uniqueid) {
		$params = array(
			'api_m'				=> "api_init",
			'clientname'		=> $client_name,
			'clientversion'		=> $client_version,
			'platformname'		=> $platform_name,
			'platformversion'	=> $platform_version,
			'uniqueid'			=> $uniqueid
		);
		$url = $this->vb_config["API_URL"]."?".http_build_query($params, '', '&');
		$json_raw = @file_get_contents($url);
		if ($json_raw !== false) {
			// Antwort erfolgreich abgerufen
			$json = json_decode($json_raw, true);
			$this->vb_config["API_CLIENT_ID"] = $json["apiclientid"];
			$this->vb_config["API_TOKEN"] = $json["apiaccesstoken"];
			$this->vb_config["API_SECRET"] = $json["secret"];
			return true;
		} else {
			global $ab_path;
			require_once $ab_path."sys/lib.kernel.php";
			eventlog("error", "[VBulletin] Failed to initialize api!", var_export($json_raw, true));
			return false;
		}
	}

	public function Login($username, $password, $stay = true) {
		// https://www.vbulletin.com/forum/content.php/365-User-Related-Methods#login_login
		$result = $this->ExecuteMethod("login_login", array(), array(
			"vb_login_username"		=> $username,	// Username
			"vb_login_password"		=> $password	// User's password
		));
     #   var_dump($result); die();
		if (!empty($result["session"]) && ($result["response"]["errormessage"][0] == "redirect_login")) {
			// Login okay, set cookies
			$host = explode(".", $_SERVER['HTTP_HOST']);
			$userid = $result["session"]["userid"];
			$salt = $this->GetUserSalt($userid);
			$cookie_domain = ".".$host[count($host)-2].".".$host[count($host)-1];
			$cookie_pass = md5(md5(md5($password).$salt).$this->vb_config["COOKIE_SALT"]);
			setcookie('bb_userid', $userid, ($stay ? strtotime('+1 year') : NULL), '/', $cookie_domain);
			setcookie('bb_password', $cookie_pass, ($stay ? strtotime('+1 year') : NULL), '/', $cookie_domain);
			return true;
		} else {
			// Login failed
			global $ab_path;
			require_once $ab_path."sys/lib.kernel.php";
			eventlog("error", "[VBulletin] Login failed!", var_export($json_raw, true));
			return false;
		}
	}

	public function Logout() {
		// https://www.vbulletin.com/forum/content.php/365-User-Related-Methods#login_logout
		$result = $this->ExecuteMethod("login_logout");
		if (!empty($result["session"]) && ($result["response"]["errormessage"][0] == "cookieclear")) {
			// Login okay, set cookies
			$host = explode(".", $_SERVER['HTTP_HOST']);
			$cookie_domain = ".".$host[count($host)-2].".".$host[count($host)-1];
			setcookie('bb_userid', false, time()-86400, '/', $cookie_domain);
			setcookie('bb_password', false, time()-86400, '/', $cookie_domain);
			setcookie('bb_sessionhash', false, time()-86400, '/', $cookie_domain);
			// Remove API-Session
			unset($_SESSION["vb_api"]);
			return true;
		} else {
			// Logout failed
			global $ab_path;
			require_once $ab_path."sys/lib.kernel.php";
			eventlog("error", "[VBulletin] Logout failed!", var_export($json_raw, true));
			return false;
		}
	}

	public function RegisterUser($username, $email, $password, $options = array("adminemail" => 1, "showemail" => 0), $referer = "") {
		// https://www.vbulletin.com/forum/content.php/365-User-Related-Methods#register_addmember
		$result = $this->ExecuteMethod("register_addmember", array(), array(
			"agree"				=> 1,			// Accepted rules, to be checked before call!!
			"username"			=> $username,	// Username to be registered
			"email"				=> $email,		// User's email address
			"emailconfirm"		=> $email,		// User's email address
			"password"			=> $password,	// User's password
			"passwordconfirm"	=> $password,	// User's password
			"options"			=> $options		// Further options
		));
		return $result;
	}

	/**
	 * Returns the complete Forum rules as HTML string (UTF-8 !!)
	 */
	public function GetForumRules() {
		global $nar_systemsettings;
		$result = $this->ExecuteMethod("register");
		if (!empty($result['response']['errormessage'])) {
			// Fehler erhalten
			return false;
		}
		$rules = $result["vbphrase"]["forum_rules_registration"] . $result["vbphrase"]["forum_rules_description"];
		$rules = str_replace('%1$s', $nar_systemsettings["SITE"]["SITEURL"], $rules);
		$rules = str_replace('%2$s', "", $rules);
		$rules = str_replace('%3$s', "", $rules);
		$rules = str_replace('%4$s', $nar_systemsettings["SUPPORT"]["SP_EMAIL"], $rules);
		return $rules;
	}

	public function GetUserId($username) {
		global $db;
		return $db->fetch_atom("SELECT userid FROM `".mysql_escape_string($this->vb_config["DB_PREFIX"])."user`
			WHERE username='".mysql_escape_string($username)."'");
	}

	private function GetUserSalt($userid) {
		global $db;
		return $db->fetch_atom("SELECT salt FROM `".mysql_escape_string($this->vb_config["DB_PREFIX"])."user`
			WHERE userid='".mysql_escape_string($userid)."'");
	}

	public function SetUserPassword($userid, $password) {
		// TODO: Durch API-Funktion ersetzen
		global $db;
		$password_crypt = md5( md5($password) . $this->GetUserSalt($userid) );
		$res = $db->querynow("UPDATE `".mysql_escape_string($this->vb_config["DB_PREFIX"])."user` ".
			"SET password='".mysql_escape_string($password_crypt)."' WHERE userid=".(int)$userid);
		return $res['rsrc'];
	}

	public function SetUserEmail($userid, $email) {
		// TODO: Durch API-Funktion ersetzen
		global $db;
		$res = $db->querynow("UPDATE `".mysql_escape_string($this->vb_config["DB_PREFIX"])."user` ".
			"SET email='".mysql_escape_string($email)."' WHERE userid=".(int)$userid);
		return $res['rsrc'];
	}
}

?>
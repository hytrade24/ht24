<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once( $ab_path."sys/hybridauth/Hybrid/Auth.php" );

class UserAuthenticationManagement {
	private static $db;
	private static $instance = null;

	/**
	 * Singleton 
	 * 
	 * @param ebiz_db $db
	 * @return UserAuthenticationManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);
		
		return self::$instance;
	}

	public function connect($provider) {
		$hybridAuth = new Hybrid_Auth($this->getHybridAuthConfigurationPath());

		$adapter = Hybrid_Auth::authenticate( $provider );

		$user_profile = $adapter->getUserProfile();
		if($user_profile != null) {
			$userData = $this->findUserByProviderUid($provider,  $user_profile->identifier);

			return array(
				'userData' => $userData,
				'provider' => $provider,
				'socialMediaProfile' => $user_profile
			);
		}

		return null;
	}

	public function disconnect($userId, $provider) {
		return $this->getDb()->querynow("DELETE FROM user_authentication WHERE FK_USER = '".(int)$userId."' AND PROVIDER = '".mysql_real_escape_string($provider)."' LIMIT 1");
	}

	public function findByProviderUid($provider, $providerUid) {
		return $this->getDb()->fetch1("SELECT a.* FROM user_authentication a WHERE a.PROVIDER = '".mysql_real_escape_string($provider)."' AND a.PROVIDER_UID = '".mysql_real_escape_string($providerUid)."'  ");
	}

	public function findUserByProviderUid($provider, $providerUid) {
		return $this->getDb()->fetch1("SELECT u.* FROM user_authentication a JOIN user u ON u.ID_USER = a.FK_USER WHERE a.PROVIDER = '".mysql_real_escape_string($provider)."' AND a.PROVIDER_UID = '".mysql_real_escape_string($providerUid)."'  ");
	}

	public function fetchAllProvidersByUserId($userId) {
		return $this->getDb()->fetch_table("SELECT a.* FROM user_authentication a WHERE a.FK_USER = '".(int)$userId."' ");
	}

	public function getHybridAuthProviders() {
		$providers = array();
		$hybridAuthConfig = include $this->getHybridAuthConfigurationPath();

		foreach($hybridAuthConfig['providers'] as $key => $provider) {
			if($provider['enabled'] == true) {
				$providers[$key] = $provider;
			}
		}

		return $providers;
	}

	public function getHybridAuthConfigurationPath() {
		global $ab_path;

		return $ab_path . 'sys/hybridauth/config.php';
	}


	public function createUserAuthenticationForUserId($userId, $provider, $provider_uid, $data = array()) {
		return $this->getDb()->update('user_authentication', array(
			'FK_USER' => $userId,
			'PROVIDER' => $provider,
			'PROVIDER_UID' => $provider_uid,
			'EMAIL' => $data['email'],
			'DISPLAYNAME' => $data['displayName'],
			'FIRST_NAME' => $data['firstName'],
			'LAST_NAME' => $data['lastName'],
			'PROFILE_URL' => $data['profileURL'],
			'WEBSITE_URL' => $data['webSiteURL'],
			'STAMP_CREATED' => date("Y-m-d H:i:s")
		));
	}


	public function resetRegisterCookie() {
		unset($_SESSION['REGISTER_SOCIAL_MEDIA']);

		return true;
	}

	public function isSocialMediaLoginEnabled() {
		global $nar_systemsettings;

		return ($nar_systemsettings['SITE']['SOCIALMEDIA_LOGIN'] == 1);
	}

	/**
	 * @return ebiz_db $db
	 */
	public function getDb() {
		return self::$db;
	}
	
	/**
	 * @param ebiz_db $db
	 */
	public function setDb(ebiz_db $db) {
		self::$db = $db;
	}
	
	private function __construct() {
	}
	private function __clone() {
	}
}
<?php


abstract class Affiliate_Adapter_AbstractAffiliateAdapter implements Affiliate_Adapter_AffiliateAdapterInterface {


    protected $db;
	protected $adapterName;
	protected $affiliate;

    public function __construct() {
        global $db;

        $this->db = $db;
    }

	public function init($affiliate) {
		$this->affiliate = $affiliate;
	}


	public function cleanUp($adIds = array()) {
		$affiliate = $this->getAffiliate();
		$sqlWhere = "";
		if(count($adIds) > 0) {
			$sqlWhere .= " AND am.ID_AD_MASTER IN (".implode(",", $adIds).") ";
		}

		$oldAds = $this->getDb()->fetch_table("SELECT ID_AD_MASTER, AD_TABLE FROM ad_master am WHERE AFFILIATE_FK_AFFILIATE = '".$affiliate['ID_AFFILIATE']."' ".$sqlWhere);

		$touchedAdTables = array();
		$adPaths = array();

		foreach($oldAds as $key => $ad) {
			if(!in_array($ad['AD_TABLE'], $touchedAdTables)) {
				$touchedAdTables[] = $ad['AD_TABLE'];
			}

			$path = AdManagment::getAdCachePath($ad['ID_AD_MASTER'], FALSE);
			system("rm -r ".$path);
			$adPaths[] = '"'.$path.'"';
		}

		foreach($touchedAdTables as $key => $adTable) {
			$this->getDb()->querynow("
				DELETE
					a
				FROM ".$adTable." a
				JOIN ad_master am ON am.ID_AD_MASTER = a.ID_".strtoupper($adTable)."
				WHERE am.AFFILIATE_FK_AFFILIATE = '".$affiliate['ID_AFFILIATE']."' ".$sqlWhere."
			");
		}

		$this->getDb()->querynow($q = "
			DELETE
				am,ai, agt, ads, al
			FROM ad_master am
			LEFT JOIN ad_images ai ON am.ID_AD_MASTER = ai.FK_AD
			LEFT JOIN ad_agent_temp agt ON am.ID_AD_MASTER = agt.FK_ARTICLE
			LEFT JOIN ad_search ads ON am.ID_AD_MASTER = ads.FK_AD
			LEFT JOIN ad_likes al ON am.ID_AD_MASTER = al.FK_AD
			WHERE
				am.AFFILIATE_FK_AFFILIATE = '".$affiliate['ID_AFFILIATE']."' ".$sqlWhere."
		");

		/*$this->getDb()->update("affiliate", array(
			'ID_AFFILIATE' => $affiliate['ID_AFFILIATE'],
			'NUMBER_OF_ARTICLES' => 0
		));*/

	}

	protected function getCurrentAffiliateAds() {
		$affiliate = $this->getAffiliate();
		return $this->getDb()->fetch_nar("SELECT AFFILIATE_IDENTIFIER, ID_AD_MASTER FROM ad_master WHERE AFFILIATE_FK_AFFILIATE = '".$affiliate['ID_AFFILIATE']."' ");
	}


	protected function getFallbackCategory() {
		if($this->affiliate['FK_FALLBACK_KAT'] != NULL) {
			return $this->getDb()->fetch1("SELECT * FROM kat WHERE ID_KAT = '".(int)$this->affiliate['FK_FALLBACK_KAT']."'");
		}
		return NULL;
	}

	protected function getCategoryAliasTable() {
		$aliasCategoryMapping = array();

		$categoryAdTableMapping = $this->getDb()->fetch_nar("SELECT ID_KAT, KAT_TABLE FROM kat");

		$categoryAliases = $this->getDb()->fetch_table("SELECT * FROM affiliate_kat_alias WHERE FK_AFFILIATE = '".(int)$this->affiliate['ID_AFFILIATE']."'");
		foreach($categoryAliases as $key => $categoryAlias) {
			$aliases = explode("\r\n", $categoryAlias['ALIAS']);
			foreach($aliases as $akey => $alias) {
				$alias = trim($alias);
				if($alias != "" && !array_key_exists($alias, $aliasCategoryMapping)) {
					$aliasCategoryMapping[$alias] = array(
						'ID_KAT' => $categoryAlias['FK_KAT'],
						'KAT_TABLE' => $categoryAdTableMapping[$categoryAlias['FK_KAT']]
					);
				}
			}
		}

		return $aliasCategoryMapping;
	}

    /**
     * @return ebiz_db $db
     */
    public function getDb() {
        return $this->db;
    }

	public function setAdapterName($adapterName) {
		$this->adapterName = $adapterName;

		return $this;
	}

	public function getAdapterName() {
		return $this->adapterName;
	}

	public function getAffiliate() {
		return $this->affiliate;
	}

}
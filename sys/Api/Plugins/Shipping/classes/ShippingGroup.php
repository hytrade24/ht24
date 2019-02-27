<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 16.11.16
 * Time: 10:44
 */

class ShippingGroup implements Serializable, JsonSerializable {

  private $ident;
  private $custom;
  private $new;
  private $name;
  private $providers;
  private $regions;
  private $prices;
  
  private $lastError;
  
  function __construct($groupName, $arProviders = array(), $arRegions = array(), $arPrices = array(), $ident = null, $custom = false, $new = true) {
    $this->ident = ($ident === null ? sha1(uniqid()) : $ident);
    $this->custom = $custom;
    $this->new = ($new ? true : false);
    $this->name = $groupName;
    $this->providers = $arProviders;
    $this->regions = $arRegions;
    $this->prices = $arPrices;
    $this->lastError = null;
  }
  
  public static function fromAssoc($arGroup) {
    return new ShippingGroup($arGroup["name"], $arGroup["providers"], $arGroup["regions"], $arGroup["prices"], $arGroup["ident"], $arGroup["custom"], $arGroup["new"]);
  }
  
  public static function fromAssocList($arGroupList) {
    $arResult = array();
    foreach ($arGroupList as $groupIndex => $arGroup) {
      $arResult[] = self::fromAssoc($arGroup);
    }
    return $arResult;
  }
  
  public static function fromJson($jsonGroup) {
    $arGroup = json_decode($jsonGroup, true);
    if (!is_array($arGroup)) {
      // Failed to decode json!
      return null;
    }
    return self::fromAssoc($arGroup);
  }
  
  public static function fromJsonList($jsonGroupList) {
    $arGroupList = json_decode($jsonGroupList, true);
    if (!is_array($arGroupList)) {
      // Failed to decode json!
      return null;
    }
    return self::fromAssocList($arGroupList);
  }
  
  public function addProvider($ident) {
    $ident = trim($ident);
    if (empty($ident)) {
      $this->lastError = Translation::readTranslation("plugin", "shipping.error.provider.name", null, array(), "Bitte geben Sie einen Namen an!");
      return false;
    }
    if ($this->getProviderIndex($ident) !== null) {
      $this->lastError = Translation::readTranslation("plugin", "shipping.error.provider.duplicate", null, array(), "Ein Versand-Anbieter mit diesem Namen existiert bereits!");
      return false;
    }
    $this->providers[] = array(
      "IDENT"   => $ident,
      "TITLE"   => Api_LookupManagement::getInstance($GLOBALS["db"])->readByValue("VERSAND_ANBIETER", $ident)["V1"],
      "DEFAULT" => (empty($this->providers) ? true : false)
    );
    return true;
  }
  
  public function deleteProvider($ident) {
    $ident = trim($ident);
    $index = $this->getProviderIndex($ident);
    if ($index === null) {
      $this->lastError = Translation::readTranslation("plugin", "shipping.error.provider.not.found", null, array(), "Der gewählte Versand-Anbieter konnte nicht gefunden werden!");
      return false;
    }
    array_splice($this->providers, $index, 1);
    return true;
  }

  public function setDefaultProvider($ident) {
    $ident = trim($ident);
    $index = $this->getProviderIndex($ident);
    if ($index === null) {
      $this->lastError = Translation::readTranslation("plugin", "shipping.error.provider.not.found", null, array(), "Der gewählte Versand-Anbieter konnte nicht gefunden werden!");
      return false;
    }
    foreach ($this->providers as $providerIndex => $providerDetails) {
      $this->providers[$providerIndex]["DEFAULT"] = ($providerIndex == $index); 
    }
    return true;
  }

  public function addRegions($arCountryGroups, $arCountries) {
    return $this->addRegionCountryGroups($arCountryGroups) && $this->addRegionCountries($arCountries);
  }

  public function addRegionCountryGroups($arCountryGroups) {
    if (!array_key_exists("country_group", $this->regions)) {
      $this->regions["country_group"] = $arCountryGroups;
    } else {
      $this->regions["country_group"] = array_merge($this->regions["country_group"], $arCountryGroups);
    }
    return true;
  }

  public function addRegionCountries($arCountries) {
    if (!array_key_exists("country", $this->regions)) {
      $this->regions["country"] = $arCountries;
    } else {
      $this->regions["country"] = array_merge($this->regions["country"], $arCountries);
    }
    return true;
  }

  public function createNewIdent() {
    $this->ident = sha1(uniqid());
  }

  public function getIdent() {
    return $this->ident;
  }

  public function getName() {
    return $this->name;
  }
  
  public function getLastError() {
    return $this->lastError;
  }
  
  public function getProviderDefault() {
    foreach ($this->providers as $providerIndex => $providerDetails) {
      if ($providerDetails["DEFAULT"]) {
        return $providerDetails;
      }
    }
    return (!empty($this->providers) ? $this->providers[0] : null);
  }
  
  public function getProviderIndex($ident) {
    foreach ($this->providers as $providerIndex => $providerDetails) {
      if ($providerDetails["IDENT"] == $ident) {
        return $providerIndex;
      }
    }
    return null;
  }
  
  public function getProviders($countryId = null) {
    if ($countryId === null) {
      // Return plain list
      return $this->providers;
    } else {
      // Return list including the prices for the given country
      $arResult = array();
      foreach ($this->providers as $providerIndex => $providerDetails) {
        $providerDetails["PRICE"] = $this->getProviderPriceForCountry($providerDetails["IDENT"], $countryId);
      }

    }
  }

  public function getRegions() {
    return $this->regions;
  }

  public function getPriceMin() {
    $priceMin = null;
    if (array_key_exists("country", $this->prices)) {
      foreach ($this->prices["country"] as $countryId => $priceList) {
        foreach ($priceList as $providerIndex => $price) {
          if ($price == "-") {
            continue;
          }
          if (($priceMin === null) || ($price < $priceMin)) {
            $priceMin = $price;
          }
        }
      }
    }
    if (array_key_exists("country_group", $this->prices)) {
      foreach ($this->prices["country_group"] as $countryId => $priceList) {
        foreach ($priceList as $providerIndex => $price) {
          if ($price == "-") {
            continue;
          }
          if (($priceMin === null) || ($price < $priceMin)) {
            $priceMin = $price;
          }
        }
      }
    }
    return ($priceMin !== null ? $priceMin : 0);
  }

  public function getProviderPriceForCountry($providerIdent, $countryId) {
    if (array_key_exists("country", $this->prices) && array_key_exists($countryId, $this->prices["country"])
      && array_key_exists($providerIdent, $this->prices["country"][$countryId])) {
      if ($this->prices["country"][$countryId][$providerIdent] == "-") {
        return -1;
      } else {
        return (float)str_replace(",", ".", $this->prices["country"][$countryId][$providerIdent]);
      }
    }
    return null;
  }

  public function getProviderPriceForCountryGroup($providerIdent, $countryGroupId) {
    if (array_key_exists("country_group", $this->prices) && array_key_exists($countryGroupId, $this->prices["country_group"])
      && array_key_exists($providerIdent, $this->prices["country_group"][$countryGroupId])) {
      if ($this->prices["country_group"][$countryGroupId][$providerIdent] == "-") {
        return null;
      } else {
        return (float)str_replace(",", ".", $this->prices["country_group"][$countryGroupId][$providerIdent]);
      }
    }
    return null;
  }

  public function isCustom() {
    return $this->custom;
  }

  public function isNew() {
    return $this->new;
  }

  public function isCountrySelected($id) {
    if (array_key_exists("country", $this->regions)) {
      return in_array($id, $this->regions["country"]);
    }
    return false;
  }

  public function isCountryGroupSelected($id) {
    if (array_key_exists("country_group", $this->regions)) {
      return in_array($id, $this->regions["country_group"]);
    }
    return false;
  }

  public function isProviderSelected($providerIdent) {
    if ($this->getProviderIndex($providerIdent) !== null) {
      return true;
    }
    return false;
  }

  public function setCustom($isCustom) {
    $this->custom = $isCustom;
    return true;
  }

  public function setNew($isNew) {
    $this->new = ($isNew ? true : false);
    return true;
  }

  public function setName($name) {
    $this->name = $name;
    return true;
  }

  public function setPrices($arPrices, $arEnabled) {
    $arCountryGroupPrice = (array_key_exists("COUNTRY_GROUP", $arPrices) ? $arPrices["COUNTRY_GROUP"] : array());
    $arCountryGroupEnabled = (array_key_exists("COUNTRY_GROUP", $arEnabled) ? $arEnabled["COUNTRY_GROUP"] : array());
    $arCountryPrice = (array_key_exists("COUNTRY", $arPrices) ? $arPrices["COUNTRY"] : array());
    $arCountryEnabled = (array_key_exists("COUNTRY", $arEnabled) ? $arEnabled["COUNTRY"] : array());
    // Country groups
    $this->prices["country_group"] = array();
    foreach ($this->regions["country_group"] as $groupIndex => $groupId) {
      if (!array_key_exists($groupId, $this->prices["country_group"])) {
        $this->prices["country_group"][$groupId] = array();
      }
      foreach ($this->providers as $providerIndex => $providerDetail) {
        $providerIdent = $providerDetail["IDENT"];
        if (!array_key_exists($groupId, $arCountryGroupEnabled) || !array_key_exists($providerIdent, $arCountryGroupEnabled[$groupId])) {
          $this->prices["country_group"][$groupId][$providerIdent] = "-";
        } else if (array_key_exists($groupId, $arCountryGroupPrice) && array_key_exists($providerIdent, $arCountryGroupPrice[$groupId])) {
          $this->prices["country_group"][$groupId][$providerIdent] = $arCountryGroupPrice[$groupId][$providerIdent];
        }
      }
    }
    // Country
    $this->prices["country"] = array();
    foreach ($this->regions["country"] as $countryIndex => $countryId) {
      if (!array_key_exists($countryId, $this->prices["country"])) {
        $this->prices["country"][$countryId] = array();
      }
      foreach ($this->providers as $providerIndex => $providerDetail) {
        $providerIdent = $providerDetail["IDENT"];
        if (!array_key_exists($countryId, $arCountryEnabled) || !array_key_exists($providerIdent, $arCountryEnabled[$countryId])) {
          $this->prices["country"][$countryId][$providerIdent] = "-";
        } else if (array_key_exists($countryId, $arCountryPrice) && array_key_exists($providerIdent, $arCountryPrice[$countryId])) {
          $this->prices["country"][$countryId][$providerIdent] = $arCountryPrice[$countryId][$providerIdent];
        }
      }
    }
    return true;
  }

  public function setRegions($arCountryGroups, $arCountries) {
    $this->regions["country"] = $arCountries;
    $this->regions["country_group"] = $arCountryGroups;
    return true;
  }

  public function setProviders($arProviderIdents) {
    // Remove deselected providers
    for ($providerIndex = count($this->providers) - 1; $providerIndex >= 0; $providerIndex--) {
      if (!in_array($this->providers[$providerIndex]["IDENT"], $arProviderIdents)) {
        array_splice($this->providers, $providerIndex, 1);
      }
    }
    // Add missing providers
    foreach ($arProviderIdents as $providerIndex => $providerIdent) {
      $this->addProvider($providerIdent);
    }
    return true;
  }
  
  /**
   * (PHP 5 &gt;= 5.4.0)<br/>
   * Specify data which should be serialized to JSON
   * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
   * @return mixed data which can be serialized by <b>json_encode</b>,
   * which is a value of any type other than a resource.
   */
  function jsonSerialize() {
    return array(
      "ident"       => $this->ident,
      "custom"      => $this->custom,
      "new"         => $this->new,
      "name"        => $this->name,
      "providers"   => $this->providers,
      "regions"     => $this->regions,
      "prices"      => $this->prices
    );
  }

  /**
   * (PHP 5 &gt;= 5.1.0)<br/>
   * String representation of object
   * @link http://php.net/manual/en/serializable.serialize.php
   * @return string the string representation of the object or null
   */
  public function serialize() {
    return serialize($this->jsonSerialize());
  }

  /**
   * (PHP 5 &gt;= 5.1.0)<br/>
   * Constructs the object
   * @link http://php.net/manual/en/serializable.unserialize.php
   * @param string $serialized <p>
   * The string representation of the object.
   * </p>
   * @return void
   */
  public function unserialize($serialized)
  {
    $arData = unserialize($serialized);
    $this->ident = $arData["ident"];
    $this->custom = $arData["custom"];
    $this->new = $arData["new"];
    $this->name = $arData["name"];
    $this->providers = $arData["providers"];
    $this->regions = $arData["regions"];
    $this->prices = $arData["prices"];
  }
}
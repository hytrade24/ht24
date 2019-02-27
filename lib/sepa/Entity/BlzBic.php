<?php

namespace Scs\Common\BillingBundle\Entity;


class BlzBic
{
	/** @var integer */
	private $id;

	/** @var integer */
	private $blz;
	const LENGTH_BLZ = 8;

	/** @var string */
	private $name;
	const LENGTH_NAME = 58;

	/** @var string */
	private $plz;
	const LENGTH_PLZ = 5;

	/** @var string */
	private $city;
	const LENGTH_CITY = 35;

	/** @var string */
	private $shortname;
	const LENGTH_SHORTNAME = 27;

	/** @var string */
	private $bic;
	const LENGTH_BIC = 11;


	/**
	 * Get id
	 *
	 * @return integer
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * Set blz
	 *
	 * @param integer $blz
	 * @return $this
	 */
	public function setBlz($blz)
	{
		$this->blz = $blz;
		return $this;
	}

	/**
	 * Get blz
	 *
	 * @return integer
	 */
	public function getBlz()
	{
		return $this->blz;
	}

	/**
	 * Set name
	 *
	 * @param string $name
	 * @return $this
	 */
	public function setName($name)
	{
		$this->name = $name;
		return $this;
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Set plz
	 *
	 * @param string $plz
	 * @return $this
	 */
	public function setPlz($plz)
	{
		$this->plz = $plz;
		return $this;
	}

	/**
	 * Get plz
	 *
	 * @return string
	 */
	public function getPlz()
	{
		return $this->plz;
	}

	/**
	 * Set city
	 *
	 * @param string $city
	 * @return $this
	 */
	public function setCity($city)
	{
		$this->city = $city;
		return $this;
	}

	/**
	 * Get city
	 *
	 * @return string
	 */
	public function getCity()
	{
		return $this->city;
	}

	/**
	 * Set shortname
	 *
	 * @param string $shortname
	 * @return $this
	 */
	public function setShortname($shortname)
	{
		$this->shortname = $shortname;
		return $this;
	}

	/**
	 * Get shortname
	 *
	 * @return string
	 */
	public function getShortname()
	{
		return $this->shortname;
	}

	/**
	 * Set bic
	 *
	 * @param string $bic
	 * @return $this
	 */
	public function setBic($bic)
	{
		$this->bic = $bic;
		return $this;
	}

	/**
	 * Get bic
	 *
	 * @return string
	 */
	public function getBic()
	{
		return $this->bic;
	}
}

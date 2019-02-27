<?php

namespace Scs\Common\BillingBundle\Entity;


class DTAUSPayment
{

	/** @var string */
	private $amount;

	/** @var string */
	private $banknumber;

	/** @var string */
	private $debitnumber;

	/** @var string */
	private $name;

	/** @var string */
	private $purpose;


	/**
	 * @return string
	 */
	public function getAmount()
	{
		return $this->amount;
	}

	/**
	 * Setzt den Betrag und konvertiert ihn in das Stringformat fuer die DTAUS.
	 *
	 * @since 2014.09.25 Statt d, s an sprintf uebergeben (Beispiel float(27846) wurde zu 00000027845)
	 * @param float $amount
	 * @return $this
	 */
	public function setAmount($amount)
	{
		$amount *= 100;
		$this->amount = sprintf('%011s', $amount);
		return $this;
	}

	/**
	 * @return string
	 */
	public function getBanknumber()
	{
		return $this->banknumber;
	}

	/**
	 * @param string $banknumber
	 * @return $this
	 */
	public function setBanknumber($banknumber)
	{
		$this->banknumber = sprintf('%08s', substr($banknumber, 0, 8));
		return $this;
	}

	/**
	 * @return string
	 */
	public function getDebitnumber()
	{
		return $this->debitnumber;
	}

	/**
	 * @param string $debitnumber
	 * @return $this
	 */
	public function setDebitnumber($debitnumber)
	{
		$this->debitnumber = sprintf('%010s', substr($debitnumber, 0, 10));
		return $this;
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 * @return $this
	 */
	public function setName($name)
	{
		$name = str_replace(
			array('ä',  'ö',  'ü',  'Ä',  'Ö',  'Ü',  'ß'),
			array('ae', 'oe', 'ue', 'AE', 'OE', 'UE', 'ss'),
			$name
		);
		$this->name = sprintf('% -27s', substr($name, 0, 27));
		return $this;
	}

	/**
	 * @return string
	 */
	public function getPurpose()
	{
		return $this->purpose;
	}

	/**
	 * @param string $purpose
	 * @return $this
	 */
	public function setPurpose($purpose)
	{
		$purpose = str_replace(
			array('ä',  'ö',  'ü',  'Ä',  'Ö',  'Ü',  'ß'),
			array('ae', 'oe', 'ue', 'AE', 'OE', 'UE', 'ss'),
			$purpose
		);
		$this->purpose = sprintf('% -27s', substr($purpose, 0, 27));
		return $this;
	}

}

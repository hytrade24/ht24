<?php

namespace Scs\Common\BillingBundle\Util;

use Scs\Common\BillingBundle\Entity\DTAUS;
use Scs\Common\BillingBundle\Entity\DTAUSPayment;
use Scs\Common\BillingBundle\Exception\DTAUSParseException;
use Symfony\Component\HttpFoundation\File\File;


class DTAUSParser
{

	/** @var integer */
	private $extensionsCnt;

	/** @var integer */
	private $extensionsRead;

	/** @var integer */
	private $cursor;

	/** @var string */
	private $parseData;

	/** @var DTAUS */
	private $data;


	public function __construct()
	{
		$this->_initParse();
	}

	/**
	 * @throws DTAUSParseException
	 */
	private function _checkParseData()
	{
		if (!$this->parseData)
		{
			throw new DTAUSParseException('Keine Daten zum parsen vorhanden');
		}
	}

	private function _initParse()
	{
		$this->extensionsCnt = 0;
		$this->extensionsRead = 0;
		$this->cursor = 0;
		$this->data = new DTAUS();
	}

	/**
	 * @param $length
	 * @return string
	 * @throws DTAUSParseException
	 */
	private function _readDataLength($length)
	{
		$data = substr($this->parseData, $this->cursor, $length);
		if (strlen($data) != $length)
		{
			throw new DTAUSParseException('Fehlerhafte Datei, konnte nicht genug Zeichen einlesen');
		}
		$this->cursor += $length;
		return $data;
	}

	/**
	 * @throws DTAUSParseException
	 */
	private function _parseBlockA()
	{
		$this->_checkParseData();
		// Datensatz A - Header
		// --------------------
		// (4) Laenge des Datensatzes (Header ist immer 128 Bytes)
		$data = $this->_readDataLength(4);
		if ('0128' != $data)
		{
			throw new DTAUSParseException('Header Datensatzlaenge ungueltig');
		}
		// (1) Datensatz-Typ (immer A)
		$data = $this->_readDataLength(1);
		if ('A' != $data)
		{
			throw new DTAUSParseException('Datensatztyp im Header ungueltig');
		}
		// (2) Art der Transaktion (LB = Lastschriften Bankseitig, LK = Lastschriften Kundenseitig,
		//                          GB = Gutschriften Bankseitig, GK = Gutschriften Kundenseitig)
		$data = $this->_readDataLength(2);
		if (!in_array($data, array('LB', 'LK', 'GB', 'GK')))
		{
			throw new DTAUSParseException('Art der Transaktion ungueltig im Header');
		}
		// (8) Bankleitzahl des Auftraggebers
		$data = $this->_readDataLength(8);
		$this->data->setOwnerBankNumber((int)$data);
		// (8) CST, nur belegt, wenn Absender Kreditinstitut, ansonsten "00000000"
		$this->_readDataLength(8);
		// (27) Name des Auftraggebers
		$data = trim($this->_readDataLength(27));
		if (!$data)
		{
			throw new DTAUSParseException('Es wurde kein Auftraggebername angegeben');
		}
		$this->data->setOwnerName($data);
		// (6) Aktuelles Datum im Format DDMMJJ
		$data = $this->_readDataLength(6);
		try
		{
			$year = substr($data, 4);
			$month = substr($data, 2, 2);
			$day = substr($data, 0, 2);
			$this->data->setDate(new \DateTime(sprintf('%s/%s/%s', $month, $day, $year)));
		}
		catch (\Exception $e)
		{
			throw new DTAUSParseException('Aktuelles Datum im Header ungueltig', 0, $e);
		}
		// (4) CST, 4 Leerzeichen
		$data = $this->_readDataLength(4);
		if (str_repeat(' ', 4) != $data)
		{
			throw new DTAUSParseException('4 Blanks im Header ungueltig');
		}
		// (10) Kontonummer des Auftraggebers
		$data = $this->_readDataLength(10);
		$this->data->setOwnerDebitNumber((int)$data);
		// (10) Optionale Referenznummer
		$data = $this->_readDataLength(10);
		$this->data->setReferenceNumber((int)$data);
		// (15) Reserviert, 15 Leerzeichen
		$data = $this->_readDataLength(15);
		if (str_repeat(' ', 15) != $data)
		{
			throw new DTAUSParseException('15 Blanks im Header ungueltig');
		}
		// (8) Optionales Ausfuehrungsdatum im Format DDMMJJJJ
		//     Nicht juenger als Erstellungsdatum, jedoch hoechstens 15 Kalendertage spaeter, sonst Leerzeichen
		$data = $this->_readDataLength(8);
		if (trim($data))
		{
			try
			{
				$year = substr($data, 4);
				$month = substr($data, 2, 2);
				$day = substr($data, 0, 2);
				$this->data->setRequestDate(new \DateTime(sprintf('%s%s%s', $year, $month, $day)));
				if ($this->data->getDate() > $this->data->getRequestDate())
				{
					throw new DTAUSParseException('Optionales Ausfuehrungsdatum liegt vor dem Datum der DTAUS');
				}
			}
			catch (\Exception $e)
			{
				throw new DTAUSParseException('Optionales Ausfuehrungsdatum ist ungueltig', 0, $e);
			}
		}
		// (24) Reserviert, 24 Leerzeichen
		$data = $this->_readDataLength(24);
		if (str_repeat(' ', 24) != $data)
		{
			throw new DTAUSParseException('24 Blanks im Header ungueltig');
		}
		// (1) Waehrungskennzeichen (' ' = DM, 1 = Euro)
		$data = $this->_readDataLength(1);
		if ('1' != $data)
		{
			throw new DTAUSParseException('Waehrung ist nicht in Euro, Datei wird nicht angenommen');
		}
	}

	/**
	 * @throws DTAUSParseException
	 */
	private function _parseBlockC()
	{
		$payment = new DTAUSPayment();
		// Datensatz C - Body (Zahlung)
		// ----------------------------
		// (4) Laenge des Datensatze (187 + x * 29, x = Anzahl Erweiterungsteil)
		$data = (int)$this->_readDataLength(4);
		if (245 != $data)
		{
			throw new DTAUSParseException('C Block hat ungueltige Laenge');
		}
		/*if (0 != $data % 29)
		{
			throw new DTAUSParseException('C Block hat ungueltige Laenge');
		}*/
		// (1) Datensatz-Typ (immer C)
		$data = $this->_readDataLength(1);
		if ('C' != $data)
		{
			throw new DTAUSParseException('Fehler beim Einlesen eines C Blocks, es ist keiner');
		}
		// (8) Bankleitzahl des Auftraggebers (optional)
		$data = (float)$this->_readDataLength(8);
		if ($data and $data != $this->data->getOwnerBankNumber())
		{
			throw new DTAUSParseException(
				'Bankleitzahl vom Auftraggeber in C Block unterscheidet sich mit Bankleitzahl im A Block'
			);
		}
		// (8) Bankleitzahl des Kunden
		$data = (float)$this->_readDataLength(8);
		if (!$data)
		{
			throw new DTAUSParseException('Keine gueltige Bankleitzahl des Kunden');
		}
		$payment->setBankNumber($data);
		// (10) Kontonummer des Kunden
		$data = (float)$this->_readDataLength(10);
		if (!$data)
		{
			throw new DTAUSParseException('Keine gueltige Kontonummer des Kunden');
		}
		$payment->setDebitNumber($data);
		// (13) Verschiedenes
		$data = $this->_readDataLength(13);
		if ('0' != substr($data, 0, 1) or '0' != substr($data, -1))
		{
			throw new DTAUSParseException('Ungueltige Daten bei Verschiedenes');
		}
		// (5) Art der Transaktion (05000 = Einzugsermaechtigungsverfahren)
		$data = $this->_readDataLength(5);
		if ('05000' != $data)
		{
			throw new DTAUSParseException('Zahlungsvorgang ist keine Lastschrift');
		}
		// (1) Reserviert (1 Leerzeichen)
		$data = $this->_readDataLength(1);
		if (' ' != $data)
		{
			throw new DTAUSParseException('1 Blank im Zahlung ist ungueltig');
		}
		// (11) Betrag (DM)
		$this->_readDataLength(11);
		// (8) Bankleitzahl des Auftraggebers
		$data = (float)$this->_readDataLength(8);
		if ($data != $this->data->getOwnerBankNumber())
		{
			throw new DTAUSParseException(
				'Bankleitzahl vom Auftraggeber im C Block unterscheidet sich mit Bankleitzahl im A Block'
			);
		}
		// (10) Kontonummer des Auftraggebers
		$data = (float)$this->_readDataLength(10);
		if ($data != $this->data->getOwnerDebitNumber())
		{
			throw new DTAUSParseException(
				'Kontonummer vom Auftraggeber im C Block unterscheidet sich von Kontonummer im A Block'
			);
		}
		// (11) Betrag (Euro)
		$data = (int)$this->_readDataLength(11) / 100;
		if (0 > $data)
		{
			throw new DTAUSParseException('Zahlung ist ohne Betrag');
		}
		$payment->setAmount($data);
		// (3) Reserviert (3 Leerzeichen)
		$data = $this->_readDataLength(3);
		if (str_repeat(' ', 3) != $data)
		{
			throw new DTAUSParseException('3 Blanks in Zahlung ist ungueltig');
		}
		// (27) Name des Kunden
		$data = trim($this->_readDataLength(27));
		if (!$data)
		{
			throw new DTAUSParseException('Es wurde kein Kundenname angegeben');
		}
		$payment->setName($data);
		// (8) Reserviert (8 Leerzeichen)
		$data = $this->_readDataLength(8);
		if (str_repeat(' ', 8) != $data)
		{
			throw new DTAUSParseException('8 Blanks in Zahlung ist unegueltig');
		}
		// (27) Name des Auftraggebers
		$data = trim($this->_readDataLength(27));
		if ($data != $this->data->getOwnerName())
		{
			throw new DTAUSParseException(
				'Auftraggebername im Block C unterscheidet sich vom Auftraggebernamen im Block A'
			);
		}
		// (27) Verwendungszweck
		$data = trim($this->_readDataLength(27));
		if (!$data)
		{
			throw new DTAUSParseException('Es wurde in der Zahlung kein Verwendungszweck angegeben');
		}
		$payment->setPurpose($data);
		// (1) Waehrungskennzeichen (' ' = DM, 1 = Euro)
		$data = $this->_readDataLength(1);
		if ('1' != $data)
		{
			throw new DTAUSParseException('Waehrung ist nicht in Euro, Datei wird nicht angenommen');
		}
		// (2) Reserviert (2 Leerzeichen)
		$data = $this->_readDataLength(2);
		if (str_repeat(' ', 2) != $data)
		{
			throw new DTAUSParseException('2 Blanks in Zahlung ist ungueltig');
		}
		// (2) Anzahl der Erweiterungsdatensaetze ('00' - '15')
		$this->extensionsCnt = (int)$this->_readDataLength(2);
		$this->extensionsRead = 0;
		for ($i = 1; $i <= 2; $i++)
		{
			$this->_parseBlockCExtensionData($payment);
		}
		// (11) 11 Leerzeichen
		$data = $this->_readDataLength(11);
		if (str_repeat(' ', 11) != $data)
		{
			throw new DTAUSParseException('11 Blanks in Zahlung ist ungueltig');
		}
		// @todo eventuell weitere Erweiterungen lesen, immer in 4er Bloecken + 12 Blanks (128er Bloecke)
		$this->data->addPayment($payment);
	}

	/**
	 * @throws DTAUSParseException
	 */
	private function _parseBlockE()
	{
		// Datensatz E - Footer
		// --------------------
		// (4) Laenge des Datensatzes (immer 128 Bytes)
		$data = $this->_readDataLength(4);
		if ('0128' != $data)
		{
			throw new DTAUSParseException('E Block hat ungueltige Laenge');
		}
		// (1) Datensatz-Typ (immer E)
		$data = $this->_readDataLength(1);
		if ('E' != $data)
		{
			throw new DTAUSParseException('Fehler beim Lesen eines E Blocks, es ist keiner');
		}
		// (5) 5 Leerzeichen
		$data = $this->_readDataLength(5);
		if (str_repeat(' ', 5) != $data)
		{
			throw new DTAUSParseException('5 Blanks im Footer ungueltig');
		}
		// (7) Anzahl der Datensaetze vom Typ C
		$data = (int)$this->_readDataLength(7);
		if ($data != $this->data->getPayments()->count())
		{
			throw new DTAUSParseException(
				'Die Anzahl der Transaktionen stimmt nicht mit der eingelesenen Anzahl ueberein'
			);
		}
		// (13) Kontrollsumme Betraege in DM
		$this->_readDataLength(13);

		// Kontrollsummen aus Transaktionen ausrechnen
		$ctrlAmount = 0;
		$ctrlBanknumbers = 0;
		$ctrlDebitnumbers = 0;
		/** @var DTAUSPayment $payment */
		foreach ($this->data->getPayments() as $payment)
		{
			$ctrlAmount += (int)$payment->getAmount();
			$ctrlBanknumbers += (float)$payment->getBankNumber();
			$ctrlDebitnumbers += (float)$payment->getDebitNumber();
		}

		// (17) Kontrollsumme Kontonummern
		$data = $this->_readDataLength(17);
		if ((float)$data != $ctrlDebitnumbers)
		{
			throw new DTAUSParseException('Kontrollsumme der Kontonummern stimmt nicht ueberein');
		}
		// (17) Kontrollsumme Bankleitzahlen
		$data = (float)$this->_readDataLength(17);
		if ($data != $ctrlBanknumbers)
		{
			throw new DTAUSParseException('Kontrollsumme der Bankleitzahlen stimmt nicht ueberein');
		}
		// (13) Kontrollsumme Betraege in Euro
		$data = (int)$this->_readDataLength(13);
		/*var_dump($data, $ctrlAmount);
		die();*/
		if ($data != $ctrlAmount)
		{
			throw new DTAUSParseException('Kontrollsumme der Eurozahlungen stimmt nicht ueberein');
		}
		// (51) 51 Leerzeichen
		$data = $this->_readDataLength(51);
		if (str_repeat(' ', 51) != $data)
		{
			throw new DTAUSParseException('51 Blanks im Footer sind ungueltig');
		}
	}

	/**
	 * @param DTAUSPayment $payment
	 * @throws DTAUSParseException
	 */
	private function _parseBlockCExtensionData(DTAUSPayment $payment)
	{
		// (2) Art der Erweiterung (01 = Name des Kunden, 02 = Verwendungszeck, 03 = Name des Auftraggebers)
		$type = $this->_readDataLength(2);
		if (!in_array($type, array('01', '02', '03')))
		{
			throw new DTAUSParseException('Ungueltiger Typ im Erweiterungsdatensatz');
		}
		// (27) Inhalt der Erweiterung
		$data = trim($this->_readDataLength(27));
		if ($this->extensionsRead < $this->extensionsCnt)
		{
			switch ((int)$type)
			{
				case 2:
					$payment->setPurpose($payment->getPurpose().' '.$data);
			}
			$this->extensionsRead++;
		}
	}

	/**
	 * @return DTAUS
	 */
	public function getData()
	{
		return $this->data;
	}

	/**
	 * @return string
	 */
	public function getParseData()
	{
		return $this->parseData;
	}

	/**
	 * @return DTAUS
	 * @throws DTAUSParseException
	 */
	public function parse()
	{
		$this->_initParse();
		$this->_parseBlockA();

		// Rausfinden wann C Bloecke vorbei sind und dann E Block lesen
		do {
			$this->_readDataLength(4);
			$data = $this->_readDataLength(1);
			$this->cursor -= 5;
			if ('C' != $data)
			{
				break;
			}
			$this->_parseBlockC();
		} while (true);

		$this->_parseBlockE();

		return $this->data;
	}

	public function parseFile(File $file)
	{
		$this->parseData = file_get_contents($file->getPathname());
		return $this->parse();
	}

	/**
	 * @param $data
	 * @return DTAUS
	 */
	public function parseString($data)
	{
		$this->parseData = $data;
		return $this->parse();
	}

	/**
	 * @param string $parseData
	 * @return $this
	 */
	public function setParseData($parseData)
	{
		$this->parseData = $parseData;
		return $this;
	}

}
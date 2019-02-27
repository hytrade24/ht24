<?php

namespace Scs\Common\BillingBundle\Util;

use Scs\Common\BillingBundle\Entity\DTAUS;
use Scs\Common\BillingBundle\Entity\DTAUSPayment;


class DTAUSCreator
{

	/** @var DTAUS */
	private $dtaus;

	/** @var string */
	private $dtausText;

	/** @var integer */
	private $ctrlBanknumbers;

	/** @var integer */
	private $ctrlDebitnumbers;

	/** @var integer */
	private $ctrlAmount;

	/** @var DTAUSPayment[] */
	private $payments;


	private function _generatePayment(DTAUSPayment $payment)
	{
		$body = '';
		// Datensatz C - Body (Zahlung)
		// ----------------------------
		// (4) Laenge des Datensatze (187 + x * 29, x = Anzahl Erweiterungsteil)
		$body .= '0216';
		// (1) Datensatz-Typ (immer C)
		$body .= 'C';
		// (8) Bankleitzahl des Auftraggebers (optional)
		$body .= $this->dtaus->getOwnerBankNumber();
		// (8) Bankleitzahl des Kunden
		$body .= $payment->getBanknumber();
		$this->ctrlBanknumbers += intval($payment->getBanknumber());
		// (10) Kontonummer des Kunden
		$body .= $payment->getDebitnumber();
		$this->ctrlDebitnumbers += intval($payment->getDebitnumber());
		// (13) Verschiedenes
		$body .= str_repeat('0', 13);
		// (5) Art der Transaktion (05000 = Einzugsermaechtigungsverfahren)
		$body .= '05000';
		// (1) Reserviert (1 Leerzeichen)
		$body .= ' ';
		// (11) Betrag (DM)
		$body .= str_repeat('0', 11);
		// (8) Bankleitzahl des Auftraggebers
		$body .= $this->dtaus->getOwnerBankNumber();
		// (10) Kontonummer des Auftraggebers
		$body .= $this->dtaus->getOwnerDebitNumber();
		// (11) Betrag (Euro)
		$body .= $payment->getAmount();
		$this->ctrlAmount += intval($payment->getAmount());
		// (3) Reserviert (3 Leerzeichen)
		$body .= str_repeat(' ', 3);
		// (27) Name des Kunden
		$body .= $payment->getName();
		// (8) Reserviert (8 Leerzeichen)
		$body .= str_repeat(' ', 8);
		// (27) Name des Auftraggebers
		$body .= $this->dtaus->getOwnerName();
		// (27) Verwendungszweck
		$body .= $payment->getPurpose();
		// (1) Waehrungskennzeichen (' ' = DM, 1 = Euro)
		$body .= '1';
		// (2) Reserviert (2 Leerzeichen)
		$body .= '  ';
		// (2) Anzahl der Erweiterungsdatensaetze ('00' - '15')
		$body .= '01';
		// (2) Art der Erweiterung (01 = Name des Kunden, 02 = Verwendungszeck, 03 = Name des Auftraggebers)
		$body .= '02';
		// (27) Inhalt der Erweiterung
		$body .= sprintf('% -27s', 'Firecash Onlinezahlung');
		// Auf 256 Zeichen erweitern
		$body .= '00'.str_repeat(' ', 27).str_repeat(' ', 11);
		// Ueberpruefen, ob 256 Zeichen vorhanden sind
		$bodyLength = strlen($body);
		if (256 != $bodyLength)
		{
			throw new \Exception(sprintf('DTAUS body entry length mismatch (%d != 256)', $bodyLength));
		}

		$this->dtausText .= $body;
	}

	public function generateDtaus()
	{
		if (empty($this->payments))
		{
			throw new \Exception('no payments specified');
		}

		$this->dtausText = '';
		$this->ctrlBanknumbers = 0;
		$this->ctrlDebitnumbers = 0;
		$this->ctrlAmount = 0;
		$this->dtaus->setDate(new \DateTime());

		// Datensatz A - Header
		// --------------------
		// (4) Laenge des Datensatzes (Header ist immer 128 Bytes)
		$this->dtausText .= '0128';
		// (1) Datensatz-Typ (immer A)
		$this->dtausText .= 'A';
		// (2) Art der Transaktion (LB = Lastschriften Bankseitig, LK = Lastschriften Kundenseitig,
		//                          GB = Gutschriften Bankseitig, GK = Gutschriften Kundenseitig)
		$this->dtausText .= 'LK';
		// (8) Bankleitzahl des Auftraggebers
		$this->dtausText .= $this->dtaus->getOwnerBankNumber();
		// (8) CST, nur belegt, wenn Absender Kreditinstitut, ansonsten "00000000"
		$this->dtausText .= str_repeat('0', 8);
		// (27) Name des Auftraggebers
		$this->dtausText .= $this->dtaus->getOwnerName();
		// (6) Aktuelles Datum im Format DDMMJJ
		$this->dtausText .= $this->dtaus->getDate()->format('dmy');
		// (4) CST, 4 Leerzeichen
		$this->dtausText .= str_repeat(' ', 4);
		// (10) Kontonummer des Auftraggebers
		$this->dtausText .= $this->dtaus->getOwnerDebitNumber();
		// (10) Optionale Referenznummer
		$this->dtausText .= str_repeat('0', 10);
		// (15) Reserviert, 15 Leerzeichen
		$this->dtausText .= str_repeat(' ', 15);
		// (8) Optionales Ausfuehrungsdatum im Format DDMMJJJJ
		//     Nicht juenger als Erstellungsdatum, jedoch hoechstens 15 Kalendertage spaeter, sonst Leerzeichen
		$this->dtausText .= $this->dtaus->getDate()->format('dmY');
		// (24) Reserviert, 24 Leerzeichen
		$this->dtausText .= str_repeat(' ', 24);
		// (1) Waehrungskennzeichen (' ' = DM, 1 = Euro)
		$this->dtausText .= '1';
		// Ueberpruefen, ob 128 Zeichen vorhanden sind
		if (128 != strlen($this->dtausText))
		{
			throw new \Exception('DTAUS header length mismatch');
		}

		// Datensatz C - Body (Zahlungen)
		foreach ($this->dtaus->getPayments() as $payment)
		{
			$this->_generatePayment($payment);
		}


		$footer = '';
		// Datensatz E - Footer
		// --------------------
		// (4) Laenge des Datensatzes (immer 128 Bytes)
		$footer .= '0128';
		// (1) Datensatz-Typ (immer E)
		$footer .= 'E';
		// (5) 5 Leerzeichen
		$footer .= '     ';
		// (7) Anzahl der Datensaetze vom Typ C
		$footer .= sprintf('%07d', count($this->payments));
		// (13) Kontrollsumme Betraege in DM
		$footer .= str_repeat('0', 13);
		// (17) Kontrollsumme Kontonummern
		$footer .= sprintf('%017d', $this->ctrlDebitnumbers);
		// (17) Kontrollsumme Bankleitzahlen
		$footer .= sprintf('%017d', $this->ctrlBanknumbers);
		// (13) Kontrollsumme Betraege in Euro
		$footer .= sprintf('%013d', $this->ctrlAmount);
		// (51) 51 Leerzeichen
		$footer .= str_repeat(' ', 51);
		// Ueberpruefen, ob 128 Zeichen vorhanden sind
		if (128 != strlen($footer))
		{
			throw new \Exception('DTAUS footer length mismatch');
		}
		$this->dtausText .= $footer;

		return $this->dtausText;
	}

	/**
	 * @return \DateTime
	 */
	public function getNow()
	{
		return $this->dtaus->getDate();
	}

	/**
	 * @param DTAUS $dtaus
	 */
	public function setDtaus(DTAUS $dtaus)
	{
		$this->dtaus = $dtaus;
	}

}
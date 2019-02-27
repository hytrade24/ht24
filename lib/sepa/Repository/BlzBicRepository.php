<?php

namespace Scs\Common\BillingBundle\Repository;

use Doctrine\ORM\EntityRepository;


class BlzBicRepository extends EntityRepository
{

	/**
	 * Ueberprueft ob fuer die uebergebene BIC und BLZ ein Bankinstitut gefunden wird.
	 *
	 * @param string $blz
	 * @param string $bic
	 * @return bool
	 */
	public function checkBlzAndBic($blz, $bic)
	{
		$entry = $this->findOneBy(array('bic' => $bic, 'blz' => $blz));
		return is_null($entry) ? false : true;
	}

	/**
	 * @source http://www.michael-schummel.de/2007/10/05/iban-prufung-mit-php/
	 * @param $debitNumber
	 * @param $bankNumber
	 * @return string
	 */
	public function generateIban($debitNumber, $bankNumber)
	{
		$iban = 'DE00'.$bankNumber.sprintf('%010s', $debitNumber);
		$iban1 = substr( $iban,4 )
			. strval( ord( $iban{0} )-55 )
			. strval( ord( $iban{1} )-55 )
			. substr( $iban, 2, 2 );

		$rest=0;
		for ( $pos=0; $pos<strlen($iban1); $pos+=7 ) {
			$part = strval($rest) . substr($iban1,$pos,7);
			$rest = intval($part) % 97;
		}
		$pz = sprintf("%02d", 98-$rest);

		return substr_replace($iban, $pz, 2, 2);
	}

	/**
	 * @param $blz
	 * @return null|string
	 */
	public function getBicFromBlz($blz)
	{
		$qb = $this->createQueryBuilder('b');
		$qb->select('b.bic')
			->where($qb->expr()->eq('b.blz', ':blz'))
			->setParameter(':blz', $blz);
		$result = $qb->getQuery()->getResult();
		if (!$result)
		{
			return null;
		}
		return $result[0]['bic'];
	}

	/**
	 * @param $bic
	 * @return null|string
	 */
	public function getBlzFromBic($bic)
	{
		$qb = $this->createQueryBuilder('b');
		$qb->select('b.blz')
			->where($qb->expr()->eq('b.bic', ':bic'))
			->setParameter(':bic', $bic);
		$result = $qb->getQuery()->getResult();
		if (!$result)
		{
			return null;
		}
		return $result[0]['blz'];
	}

}
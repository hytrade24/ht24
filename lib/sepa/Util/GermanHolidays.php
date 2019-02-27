<?php

namespace Scs\Common\BillingBundle\Util;


class GermanHolidays
{

	public static $holidays = array(
		'ascensionDay',
		'dayOfWork',
		'christmasEve',
		'easter',
		'easterMonday',
		'firstChristmasDay',
		'germanUnificationDay',
		'goodFriday',
		'newYearsEve',
		'secondChristmasDay',
		'whitMonday'
	);

	/**
	 * Errechnet Christi Himmelfahrt
	 *
	 * @param string|null $year
	 * @return \DateTime
	 */
	public static function getAscensionDay($year = null)
	{
		$ascensionDate = self::getEaster($year);
		$ascensionDate->add(new \DateInterval('P39D'));
		return $ascensionDate;
	}

	public static function getChristmasEve($year)
	{
		$year = is_null($year) ? date('Y') : $year;
		$christmasEve = new \DateTime($year.'-12-24');
		return $christmasEve;
	}

	/**
	 * Errechnet den Tag der Arbeit
	 *
	 * @param string|null $year
	 * @return \DateTime
	 */
	public static function getDayOfWork($year = null)
	{
		$year = is_null($year) ? date('Y') : $year;
		$dayOfWork = new \DateTime($year.'-05-01');
		return $dayOfWork;
	}

	/**
	 * Errechnet den Ostersonntag
	 *
	 * @param string|null $year
	 * @return \DateTime
	 */
	public static function getEaster($year = null)
	{
		$year = is_null($year) ? date('Y') : $year;
		$easter = new \DateTime($year.'-03-21');
		$easter->add(new \DateInterval('P'.easter_days($year).'D'));
		return $easter;
	}

	/**
	 * Errechnet den Ostermontag
	 *
	 * @param string|null $year
	 * @return \DateTime
	 */
	public static function getEasterMonday($year = null)
	{
		$easterMonday = self::getEaster($year);
		$easterMonday->add(new \DateInterval('P1D'));
		return $easterMonday;
	}

	/**
	 * Errechnet den 1. Weihnachtstag
	 *
	 * @param string|null $year
	 * @return \DateTime
	 */
	public static function getFirstChristmasDay($year = null)
	{
		$year = is_null($year) ? date('Y') : $year;
		$firstChristmasDay = new \DateTime($year.'-12-25');
		return $firstChristmasDay;
	}

	/**
	 * Errechnet den Tag der deutschen Einheit
	 *
	 * @param string|null $year
	 * @return \DateTime
	 */
	public static function getGermanUnificationDay($year = null)
	{
		$year = is_null($year) ? date('Y') : $year;
		$germanUnificationDay = new \DateTime($year.'-10-03');
		return $germanUnificationDay;
	}

	/**
	 * Errechnet den Karfreitag
	 *
	 * @param string|null $year
	 * @return \DateTime
	 */
	public static function getGoodFriday($year = null)
	{
		$goodFriday = self::getEaster($year);
		$goodFriday->sub(new \DateInterval('P2D'));
		return $goodFriday;
	}

	public static function getNewYearsEve($year = null)
	{
		$year = is_null($year) ? date('Y') : $year;
		$newYearsEve = new \DateTime($year.'-12-31');
		return $newYearsEve;
	}

	/**
	 * Errechnet den 2. Weihnachtstag
	 *
	 * @param string|null $year
	 * @return \DateTime
	 */
	public static function getSecondChristmasDay($year = null)
	{
		$year = is_null($year) ? date('Y') : $year;
		$secondChristmasDay = new \DateTime($year.'-12-26');
		return $secondChristmasDay;
	}

	/**
	 * Errechnet den Pfingstmontag
	 *
	 * @param string|null $year
	 * @return \DateTime
	 */
	public static function getWhitMonday($year = null)
	{
		$whitMonday = self::getEaster($year);
		$whitMonday->add(new \DateInterval('P50D'));
		return $whitMonday;
	}

	/**
	 * Checkt ob das aktuelle Datum ein Feiertag ist, unter Beruecksichtigung der Filterliste.
	 * Wird keine Liste angegeben, werden alle gecheckt.
	 *
	 * @param \DateTime $date
	 * @param array $filter
	 * @return bool
	 */
	public static function isHoliday(\DateTime $date, Array $filter = array())
	{
		if (empty($filter))
		{
			$filter = self::$holidays;
		}
		$date = clone $date;
		$date->setTime(0, 0, 0);

		foreach ($filter as $day)
		{
			if (!in_array($day, self::$holidays))
			{
				continue;
			}
			$method = 'get'.ucfirst($day);
			/** @var \DateTime $holiday */
			$holiday = self::$method($date->format('Y'));
			if ($holiday == $date)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Gibt an ob es sich um einen Tag am Wochenende handelt.
	 *
	 * @param \DateTime $date
	 * @return bool
	 */
	public static function isWeekend(\DateTime $date)
	{
		$weekday = $date->format('w');
		return (0 == $weekday or 6 == $weekday) ? true : false;
	}

}
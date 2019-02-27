<?php

class AdAvailabilityManagement {
	/**
	 * List of AdAvailabilityManagement classes by ad id.
	 * @var array
	 */
	private static $instances = array();

	/**
	 * Database instance to be used for this ad
	 * @var ebiz_db
	 */
	private $db;

	/**
	 * Id of the ad this availability is calculated/manipulated for
	 * @var int
	 */
	private $id_ad;

	private static function calculateBlockedAreas(&$ar_block_list, $timeBegin, $timeEnd, $amount, $amountMax, $fk_event = null) {
        if ($timeBegin == $timeEnd) {
            return $ar_block_list;
        }
        $type = "na";
	    if (!is_array($fk_event) && ($fk_event > 0)) {
	        $fk_event = array($fk_event);
	    } else if ($fk_event == null) {
            $fk_event = array();
	    }
        if (count($fk_event) > 0) { 
            $type = "event";
        }
        $indexBlockLast = 0;
		foreach ($ar_block_list as $indexBlock => $ar_block) {
			if (($timeBegin >= $ar_block['BEGIN']) && ($timeBegin < $ar_block['END'])) {
			    if ($timeBegin == $ar_block['BEGIN']) {
			        if ($timeEnd > $ar_block['END']) {
                        // Add amount to existing block
                        $ar_block_list[$indexBlock]['AMOUNT_BLOCKED'] += $amount;
                        $ar_block_list[$indexBlock]['FK_EVENTS'] = array_merge($ar_block["FK_EVENTS"], $fk_event);
                        // Calculate the remaining range
                        return self::calculateBlockedAreas($ar_block_list, $ar_block['END'], $timeEnd, $amount, $amountMax, $fk_event);
			        }
			    } else {
                    // Add overlapping area as new block
                    array_splice($ar_block_list, $indexBlock+1, 0, array(array(
                        "BEGIN"            => $timeBegin,
                        "END"              => $ar_block["END"],
                        "TYPE"             => ($ar_block["TYPE"] == "na" ? "na" : $type),
                        "AMOUNT"           => $amountMax,
                        "AMOUNT_BLOCKED"   => $amount + $ar_block["AMOUNT_BLOCKED"],
                        "FK_EVENTS"        => array_merge($ar_block["FK_EVENTS"], $fk_event)
                    )));
                    // New block starts within the current block, shorten current one
                    $ar_block_list[$indexBlock]['END'] = $timeBegin;
                    // Calculate the remaining range
                    return self::calculateBlockedAreas($ar_block_list, $ar_block['END'], $timeEnd, $amount, $amountMax, $fk_event);
			    }
			}
			if (($timeEnd > $ar_block['BEGIN']) && ($timeEnd <= $ar_block['END'])) {
                if ($timeEnd == $ar_block['END']) {
                    if ($timeBegin < $ar_block['BEGIN']) {
                        // Add amount to existing block
                        $ar_block_list[$indexBlock]['AMOUNT_BLOCKED'] += $amount;
                        $ar_block_list[$indexBlock]['FK_EVENTS'] = array_merge($ar_block["FK_EVENTS"], $fk_event);
                        // Calculate the remaining range
                        return self::calculateBlockedAreas($ar_block_list, $timeBegin, $ar_block['BEGIN'], $amount, $amountMax, $fk_event);
                    }
                } else {
                    // Add overlapping area as new block
                    array_splice($ar_block_list, $indexBlock, 0, array(array(
                        "BEGIN"            => $ar_block["BEGIN"],
                        "END"              => $timeEnd,
                        "TYPE"             => ($ar_block["TYPE"] == "na" ? "na" : $type),
                        "AMOUNT"           => $amountMax,
                        "AMOUNT_BLOCKED"   => $amount + $ar_block["AMOUNT_BLOCKED"],
                        "FK_EVENTS"        => array_merge($ar_block["FK_EVENTS"], $fk_event)
                    )));
                    // New block starts within the current block, shorten current one
                    $ar_block_list[$indexBlock+1]['BEGIN'] = $timeEnd;
                    $ar_block_list[$indexBlock+1]['FK_EVENTS'] = array_merge($ar_block["FK_EVENTS"], $fk_event);
                    // Calculate the remaining range
                    return self::calculateBlockedAreas($ar_block_list, $timeBegin, $ar_block['BEGIN'], $amount, $amountMax, $fk_event);
                }
			}
			if (($timeBegin == $ar_block['BEGIN']) && ($timeEnd == $ar_block['END'])) {
				// Add amount to existing block
				$ar_block_list[$indexBlock]['AMOUNT_BLOCKED'] += $amount;
                $ar_block_list[$indexBlock]['FK_EVENTS'] = array_merge($ar_block["FK_EVENTS"], $fk_event);
				return $ar_block_list;
			}
            if ($ar_block['END'] > $timeEnd) {
                $indexBlockLast = $indexBlock;
                break;
            }
		}
        array_splice($ar_block_list, $indexBlockLast, 0, array(array(
            "BEGIN"            => $timeBegin,
            "END"              => $timeEnd,
            "TYPE"             => $type,
            "AMOUNT"           => $amountMax,
            "AMOUNT_BLOCKED"   => $amount,
            "FK_EVENTS"        => $fk_event
        )));
		return $ar_block_list;
	}

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return AdAvailabilityManagement
	 */
	public static function getInstance($id_ad, ebiz_db $db) {
		if (self::$instances[$id_ad] === NULL) {
			self::$instances[$id_ad] = new self($id_ad, $db);
		}
		return self::$instances[$id_ad];
	}

	public static function optimizeWorkTimes(&$ar_work_times) {
		$ar_result = array();
		foreach ($ar_work_times as $dateStart => $ar_work_times_to) {
			foreach ($ar_work_times_to as $dateEnd => $ar_work_times_weekdays) {
				if (preg_match("/^([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})$/", $dateStart, $arStart)
					&& preg_match("/^([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})$/", $dateEnd, $arEnd)) {
					$datetimeStart = strtotime($arStart[3]."-".$arStart[2]."-".$arStart[1]);
					$datetimeEnd = strtotime($arEnd[3]."-".$arEnd[2]."-".$arEnd[1]);
					// Initialize date range if not already done
					if (!isset($ar_result[$datetimeStart])) {
						$ar_result[$datetimeStart] = array();
					}
					if (!isset($ar_result[$datetimeStart][$datetimeEnd])) {
						$ar_result[$datetimeStart][$datetimeEnd] = array(
							"BEGIN" => $dateStart,
							"END"	=> $dateEnd,
							"TIMES" => array()
						);
					} 
					foreach ($ar_work_times_weekdays as $dateWeekday => $ar_times) {
						$ar_result[$datetimeStart][$datetimeEnd]["TIMES"][$dateWeekday] = array();
						while (count($ar_times) >= 2) {
							$timeStart = array_shift($ar_times);
							$timeEnd = array_shift($ar_times);
							$stampStart = strtotime($timeStart.":00");
							$ar_result[$datetimeStart][$datetimeEnd]["TIMES"][$dateWeekday][$stampStart] = array(
								"BEGIN"	=> $timeStart,
								"END"	=> $timeEnd
							);
						}
					}		
				}	
			}
		}
		// Sort by start date
		ksort($ar_result);
		$ar_result_formatted = array();
		foreach ($ar_result as $timeStart => $ar_result_to) {
			// Sort by end date
			ksort($ar_result_to);
			foreach ($ar_result_to as $timeEnd => $ar_result_range) {
				$dateBegin = $ar_result_range["BEGIN"];
				$dateEnd = $ar_result_range["END"];
				// Sort by weekday
				ksort($ar_result_range["TIMES"]);
				if (!is_array($ar_result_formatted[$dateBegin])) {
					$ar_result_formatted[$dateBegin] = array();
				}
				if (!is_array($ar_result_formatted[$dateBegin][$dateEnd])) {
					$ar_result_formatted[$dateBegin][$dateEnd] = array();
				}
				foreach ($ar_result_range["TIMES"] as $weekday => $ar_result_week) {
					// Sort by time
					ksort($ar_result_week);
					foreach ($ar_result_week as $stampStart => $ar_times) {
						if (!is_array($ar_result_formatted[$dateBegin][$dateEnd][$weekday])) {
							$ar_result_formatted[$dateBegin][$dateEnd][$weekday] = array();
						}
						$ar_result_formatted[$dateBegin][$dateEnd][$weekday][] = $ar_times["BEGIN"];
						$ar_result_formatted[$dateBegin][$dateEnd][$weekday][] = $ar_times["END"];
					}
				}
			}
		}
		return $ar_result_formatted;
	}

	/**
	 * Find ads by availability
	 * @param ebiz_db $db
	 * @param string $dateBegin
	 * @param string $dateEnd
	 * @param string $timeBegin
	 * @param string $timeEnd
	 */
	public static function fetchAllByAvailability(ebiz_db $db, $dateBegin, $dateEnd, $timeBegin, $timeEnd) {
		$queryTimeBegin = "TIME('".mysql_real_escape_string($timeBegin)."')";
		$queryTimeEnd = "TIME('".mysql_real_escape_string($timeEnd)."')";
		$query = "SELECT
				a.*,
				b.ID_AD_AVAILABILITY_BLOCK,
				b.TIME_BEGIN as TIME_BEGIN_BLOCKED,
				b.TIME_END as TIME_END_BLOCKED
			FROM `ad_availability` a
			LEFT JOIN `ad_availability_block` b ON a.ID_AD_AVAILABILITY=b.FK_AD_AVAILABILITY
			WHERE a.DATE_DAY BETWEEN DATE('".mysql_real_escape_string($dateBegin)."') AND DATE('".mysql_real_escape_string($dateEnd)."')
				AND (a.TIME_BEGIN BETWEEN ".$queryTimeBegin." AND ".$queryTimeEnd."
					OR a.TIME_END BETWEEN ".$queryTimeBegin." AND ".$queryTimeEnd."
					OR ".$queryTimeBegin." BETWEEN a.TIME_BEGIN AND a.TIME_END
					OR ".$queryTimeEnd." BETWEEN a.TIME_BEGIN AND a.TIME_END)
			ORDER BY a.DATE_DAY";
		$ar_result = $db->fetch_table($query);
		$ar_ads = array();
		foreach ($ar_result as $index => $ar_current) {
			$id_avail_cur = $ar_current["ID_AD_AVAILABILITY"];
			if (!is_array($ar_ads[$id_avail_cur])) {
				$ar_ads[ $id_avail_cur ] = array(
					"ID_AVAIL"		=> $id_avail_cur,
					"FK_AD"			=> $ar_current["FK_AD"],
					"DATE_DAY"		=> $ar_current["DATE_DAY"],
					"TIME_BEGIN"	=> $ar_current["TIME_BEGIN"],
					"TIME_END"		=> $ar_current["TIME_END"],
					"STAMP_BEGIN"	=> strtotime($ar_current["TIME_BEGIN"]),
					"STAMP_END"		=> strtotime($ar_current["TIME_END"]),
					"AMOUNT"		=> $ar_current["AMOUNT"],
					'AMOUNT_MAX'	=> $db->fetch_atom("SELECT MENGE FROM `ad_master` WHERE ID_AD_MASTER=".(int)$ar_current["FK_AD"]),
					"BLOCKED"		=> array()
				);
			}
			if ($ar_current['ID_AD_AVAILABILITY_BLOCK'] != null) {
				$timeBegin = strtotime($ar_current['TIME_BEGIN_BLOCKED']);
				$timeEnd = strtotime($ar_current['TIME_END_BLOCKED']);
				$amount = (int)$ar_current['AMOUNT_BLOCKED'];
                $amountMax = (int)$ar_current['AMOUNT'];
				// Check bounds
				if ($timeBegin < $ar_ads[$id_avail_cur]['STAMP_BEGIN']) {
					$timeBegin = $ar_ads[$id_avail_cur]['STAMP_BEGIN'];
				}
				if ($timeEnd > $ar_ads[$id_avail_cur]['STAMP_END']) {
					$timeEnd = $ar_ads[$id_avail_cur]['STAMP_END'];
				}
				// Combine blocks
				$ar_ads[$id_avail_cur]['BLOCKED'] = self::calculateBlockedAreas($ar_ads[$id_avail_cur]['BLOCKED'], $timeBegin, $timeEnd, $amount, $amountMax);
			}
		}
		return $ar_ads;
	}

	private function __construct($id_ad, ebiz_db $db) {
		$this->db = $db;
		$this->id_ad = $id_ad;
	}

	private function __clone() {
	}

	/**
	 * @return ebiz_db $db
	 */
	public function getDb() {
		return $db;
	}
    
    /**
     * Fills up missing days and unserializes all known days cached block data
     * @see		fetchByDay
     * @param	array	$ar_db_blocked
     * @return	array
     */
    private function combineBlockedAreasCached(&$ar_db_blocked, $dateBegin, $dateEnd) {
    	$stampRangeBegin = strtotime($dateBegin);
    	$stampRangeEnd = strtotime($dateEnd);
    	$stampCurrent = $stampRangeBegin;
    	$ar_blocked = array();
    	$ar_days_known = array();
    	foreach ($ar_db_blocked as $index => $ar_current) {
    		$timeDayStart = strtotime($ar_current['DATE_DAY']." 00:00:00");
    		$timeDayEnd = $timeDayStart + (24 * 60 * 60);
    		$stampBegin = strtotime($ar_current["DATE_DAY"]." ".$ar_current["TIME_BEGIN"]);
    		$stampEnd = strtotime($ar_current["DATE_DAY"]." ".$ar_current["TIME_END"]);
			// Block missing days
    		while ($stampCurrent < $timeDayStart) {
    			$dateCurrent = date('Y-m-d', $stampCurrent);
    			$stampNext = $stampCurrent + (24 * 60 * 60);
    			if (!in_array($dateCurrent, $ar_days_known)) {
    				$ar_days_known[] = $dateCurrent;
    				$ar_blocked[] = array(
    					"BEGIN"          => $stampCurrent,
    					"END"            => $stampNext,
    					"TYPE"           => 'na',
    					"AMOUNT"         => $ar_current["AMOUNT"],
    					"AMOUNT_BLOCKED" => $ar_current["AMOUNT"],
    					"FK_EVENTS"      => array()
    				);
    			}
    			$stampCurrent = $stampNext;    // + 1day
    		};
    		// Unserialize cache
    		$ar_days_known[] = $ar_current['DATE_DAY'];
    		$ar_blocked_cache = unserialize($ar_current['SER_BLOCKED']);
    		foreach ($ar_blocked_cache as $indexCache => $ar_current_cache) {
    			$ar_blocked[] = $ar_current_cache;
    		}
    	}
    	// Block missing days
    	while ($stampCurrent <= $stampRangeEnd) {
    		$stampNext = $stampCurrent + (24 * 60 * 60);
    		$dateCurrent = date('Y-m-d', $stampCurrent);
    		if (!in_array($dateCurrent, $ar_days_known)) {
    			$ar_days_known[] = $dateCurrent;
    			$ar_blocked[] = array(
    					"BEGIN"          => $stampCurrent,
    					"END"            => $stampNext,
    					"TYPE"           => 'na',
    					"AMOUNT"         => $ar_current["AMOUNT"],
    					"AMOUNT_BLOCKED" => $ar_current["AMOUNT"],
    					"FK_EVENTS"      => array()
    			);
    		}
    		$stampCurrent = $stampNext;    // + 1day
    	};
    	return $ar_blocked;
    }
    
    /**
     * Processes the result coming directly from the database and processes it to non-overlapping availability blocks.
     * @see		fetchByDay
     * @param	array	$ar_db_blocked
     * @return	array
     */
    private function combineBlockedAreas(&$ar_db_blocked, $dateBegin, $dateEnd) {
    	$stampCurrent = strtotime($dateBegin);
    	$ar_blocked = array();
    	$ar_days_known = array();
    	foreach ($ar_db_blocked as $index => $ar_current) {
    		$timeDayStart = strtotime($ar_current['DATE_DAY']." 00:00:00");
    		$timeDayEnd = $timeDayStart + (24 * 60 * 60);
    		$stampBegin = strtotime($ar_current["DATE_DAY"]." ".$ar_current["TIME_BEGIN"]);
    		$stampEnd = strtotime($ar_current["DATE_DAY"]." ".$ar_current["TIME_END"]);
    		// Fehlende Tage blockieren
    		while ($stampCurrent < $timeDayStart) {
    			$dateCurrent = date('Y-m-d', $stampCurrent);
    			$stampNext = $stampCurrent + (24 * 60 * 60);
    			if (!in_array($dateCurrent, $ar_days_known)) {
    				$ar_days_known[] = date('Y-m-d', $stampCurrent);
    				$ar_blocked[] = array(
    						"BEGIN"          => $stampCurrent,
    						"END"            => $stampNext,
    						"TYPE"           => 'na',
    						"AMOUNT"         => $ar_current["AMOUNT"],
    						"AMOUNT_BLOCKED" => $ar_current["AMOUNT"],
    						"FK_EVENTS"      => array()
    				);
    			}
    			$stampCurrent = $stampNext;    // + 1day
    		};
    		// Zeitraum vor der Verfügbarkeit
    		if (!in_array($ar_current['DATE_DAY'], $ar_days_known)) {
    			$ar_blocked[] = array(
    					"BEGIN"             => $timeDayStart,
    					"END"               => $stampBegin,
    					"TYPE"              => 'na',
    					"AMOUNT"            => $ar_current["AMOUNT"],
    					"AMOUNT_BLOCKED"    => $ar_current["AMOUNT"],
    					"FK_EVENTS"         => array()
    			);
    		}
    		if ($ar_current['ID_AD_AVAILABILITY_BLOCK'] != null) {
    			$timeBegin = strtotime($ar_current['DATE_DAY']." ".$ar_current['TIME_BEGIN_BLOCKED']);
    			$timeEnd = strtotime($ar_current['DATE_DAY']." ".$ar_current['TIME_END_BLOCKED']);
    			$amount = (int)$ar_current['AMOUNT_BLOCKED'];
    			$amountMax = (int)$ar_current['AMOUNT'];
    			$fk_event = (int)$ar_current['FK_EVENT'];
    			// Check bounds
    			if ($timeBegin < $stampBegin) {
    				$timeBegin = $stampBegin;
    			}
    			if ($timeEnd > $stampEnd) {
    				$timeEnd = $stampEnd;
    			}
    			// Combine blocks
    			$ar_blocked = self::calculateBlockedAreas($ar_blocked, $timeBegin, $timeEnd, $amount, $amountMax, $fk_event);
    		}
    		if (!in_array($ar_current['DATE_DAY'], $ar_days_known)) {
    			$ar_days_known[] = $ar_current['DATE_DAY'];
    			$ar_blocked[] = array(
    					"BEGIN"			 => $stampEnd,
    					"END"			 => $timeDayEnd,
    					"TYPE"           => 'na',
    					"AMOUNT"		 => $ar_current["AMOUNT"],
    					"AMOUNT_BLOCKED" => $ar_current["AMOUNT"],
    					"FK_EVENTS"      => array()
    			);
    		}
    	}
    	while ($stampCurrent <= $stampRangeEnd) {
    		$stampNext = $stampCurrent + (24 * 60 * 60);
    		$dateCurrent = date('Y-m-d', $stampCurrent);
    		if (!in_array($dateCurrent, $ar_days_known)) {
    			$ar_days_known[] = $dateCurrent;
    			$ar_blocked[] = array(
    					"BEGIN"          => $stampCurrent,
    					"END"            => $stampNext,
    					"TYPE"           => 'na',
    					"AMOUNT"         => $ar_current["AMOUNT"],
    					"AMOUNT_BLOCKED" => $ar_current["AMOUNT"],
    					"FK_EVENTS"      => array()
    			);
    		}
    		$stampCurrent = $stampNext;    // + 1day
    	};
    	return $ar_blocked;
    }

	/**
	 * Generate the availability database entries from now for the given number of days into the future.
	 * @param int $days
	 */
	public function createAdAvailability($days, $ar_worktimes) {
		$timeFrom = time();
		$timeTo = $timeFrom + ($days * 24 * 60 * 60);
		return $this->createAdAvailabilityRange(date('Y-m-d', $timeFrom), date('Y-m-d', $timeTo), $ar_worktimes);
	}

	/**
	 * Generate the availability database entries for the given date range
	 * @param string	$dateFrom
	 * @param string	$dateTo
	 * @param array		$ar_worktimes
	 */
	public function createAdAvailabilityRange($dateFrom, $dateTo, $ar_worktimes, $amount = 1) {
		// Delete old entries
		$ar_avail_raw = $this->db->fetch_table("SELECT * FROM `ad_availability` WHERE
			FK_AD=".(int)$this->id_ad." AND DATE_DAY BETWEEN '".mysql_real_escape_string($dateFrom)."' AND '".mysql_real_escape_string($dateTo)."'");
		$ar_avail = array();
		$ar_avail_ids = array();
		foreach ($ar_avail_raw as $index => $arCur) {
			$ar_avail[$arCur['DATE_DAY']] = $arCur;
			$ar_avail_ids[] = $arCur['ID_AD_AVAILABILITY'];
		}
		// Get unix timestamps of date range
		$timeFrom = strtotime($dateFrom);
		$timeTo = strtotime($dateTo);
		$timeCur = $timeFrom;
		// Prepare availability object cache
		$ar_availability_list = array();
		while ($timeCur <= $timeTo) {
			$timeCurEnd = $timeCur + (24 * 60 * 60);
			$dateCur = date('Y-m-d', $timeCur);
			$dateCurWeekday = date('N', $timeCur);
			$ar_availability = $ar_avail[$dateCur];
			$arPauseBlocks = array();
			foreach ($ar_worktimes as $index => $ar_worktime) {
				if ($ar_worktime["WEEKDAY"] == $dateCurWeekday) {
					if (!is_array($ar_availability)) {
						$ar_availability = array(
							'FK_AD'			=> $this->id_ad,
							'DATE_DAY'		=> $dateCur,
							'TIME_BEGIN'	=> $ar_worktime['BEGIN'],
							'TIME_END'		=> $ar_worktime['END'],
							'HASH_PAUSE'	=> "",
							'AMOUNT'		=> $amount
						);
						$ar_availability["ID_AD_AVAILABILITY"] = $this->db->update('ad_availability', $ar_availability);
						$ar_avail[$dateCur] = $ar_availability;
					} else {
                        if (!$ar_availability['REFRESH']) {
                            $ar_availability['TIME_BEGIN'] = $ar_worktime['BEGIN'];
                            $ar_availability['TIME_END'] = $ar_worktime['END'];
                            $ar_availability['AMOUNT'] = $amount;
                            $ar_availability['REFRESH'] = 1;
                        }
                        // Calculate pauses
                        $timeWorkFrom = strtotime($ar_worktime['BEGIN']);
                        $timeWorkTo = strtotime($ar_worktime['END']);
                        $timeDayFrom = strtotime($ar_availability['TIME_BEGIN']);
                        $timeDayTo = strtotime($ar_availability['TIME_END']);
                        if ($timeWorkFrom < $timeDayFrom) {
                            if ($timeWorkTo < $timeDayFrom) {
                                $pauseBegin = $ar_worktime['END'];
                                $pauseEnd = $ar_availability['TIME_BEGIN'];
                                $arPauseBlocks[] = array(
                                    'ID_AD_AVAILABILITY'    => $ar_availability["ID_AD_AVAILABILITY"], 
                                    'TIME_BEGIN'            => $pauseBegin,
                                    'TIME_END'              => $pauseEnd,
                                    'AMOUNT'                => $amount
                                );
                            }
                            $ar_availability['TIME_BEGIN'] = $ar_worktime['BEGIN'];
                        }
                        if ($timeWorkTo > $timeDayTo) {
                            if ($timeWorkFrom > $timeDayTo) {
                                $pauseBegin = $ar_availability['TIME_END'];
                                $pauseEnd = $ar_worktime['BEGIN'];
                                $arPauseBlocks[] = array(
                                    'ID_AD_AVAILABILITY'    => $ar_availability["ID_AD_AVAILABILITY"], 
                                    'TIME_BEGIN'            => $pauseBegin,
                                    'TIME_END'              => $pauseEnd,
                                    'AMOUNT'                => $amount
                                );
                            }
                            $ar_availability['TIME_END'] = $ar_worktime['END'];
                        }
					}
				}
			}
			// Check if a workday at all
			if (is_array($ar_availability)) {
				// Update pause blocks if changed
				$hash = md5(serialize($arPauseBlocks));
				if ($hash != $ar_availability['HASH_PAUSE']) {
					// TODO: Optimieren durch das zusammenfassen in einen delete (mit in auf eine liste aller modifizierten tage als ID_AD_AVAILABILITY)
					$this->db->querynow("DELETE FROM `ad_availability_block` 
							WHERE FK_AD_AVAILABILITY=".(int)$ar_availability["ID_AD_AVAILABILITY"]." 
								AND FK_AD_AVAILABILITY_EVENT IS NULL");
					// TODO: Optimieren durch das zusammenfassen in einen insert
					foreach ($arPauseBlocks as $indexPause => $arPause) {
						$this->blockTime($arPause["ID_AD_AVAILABILITY"], null, $arPause["TIME_BEGIN"], $arPause["TIME_END"], $arPause["AMOUNT"]);
					}
				}
				$ar_availability['HASH_PAUSE'] = $hash;
				// Update availability block if changed
				if (($ar_availability['TIME_BEGIN'] != $ar_avail[$dateCur]['TIME_BEGIN']) || ($ar_availability['TIME_END'] != $ar_avail[$dateCur]['TIME_END'])
					|| ($ar_availability['AMOUNT'] != $ar_avail[$dateCur]['AMOUNT']) || ($ar_availability['HASH_PAUSE'] != $ar_avail[$dateCur]['HASH_PAUSE'])) {
					$time_blocked = 0;
					$ar_blocked = $this->fetchByDay($dateCur, false);
					foreach ($ar_blocked as $indexBlocked => $ar_block) {
						$minutes = ($ar_block['END'] - $ar_block['BEGIN']) / 60;
						if ($ar_block['AMOUNT'] <= $ar_block['AMOUNT_BLOCKED']) {
							$time_blocked += $minutes;
						}
					}
					$time_available = (24 * 60) - $time_blocked;
					$ar_availability['SER_BLOCKED'] = serialize($ar_blocked);
					$ar_availability['TIME_AVAILABLE'] = $time_available;
					$this->db->update('ad_availability', $ar_availability);
				} /*else if (!$ar_availability['REFRESH']) {
                    // Arbeitstag entfernen falls nicht mehr vorhanden
                    // TODO: Optimieren durch das zusammenfassen in einen delete (mit in auf eine liste aller modifizierten tage als ID_AD_AVAILABILITY)
                    $this->db->querynow("DELETE FROM `ad_availability` 
                            WHERE ID_AD_AVAILABILITY=".(int)$ar_availability["ID_AD_AVAILABILITY"]);
                    $this->db->querynow("DELETE FROM `ad_availability_block` 
                            WHERE FK_AD_AVAILABILITY=".(int)$ar_availability["ID_AD_AVAILABILITY"]." 
                                AND FK_AD_AVAILABILITY_EVENT IS NULL");
				}*/
			}
			// Next day
			$timeCur += (24 * 60 * 60);
		}
	}

    /**
     * Changes the title and description of an event.
     * @param string    $begin          Begin time of the event as 'Y-m-d H:i:s' timestamp
     * @param string    $end            End time of the event as 'Y-m-d H:i:s' timestamp
     * @param string    $title          Title of the event
     * @param string    $description    Description of the event
     */
    public function createEvent($begin, $end, $title, $amount = 1, $description = "") {
        $ar_event = array(
            'FK_AD' 			=> $this->id_ad,
            'TITLE' 			=> $title,
        	'AMOUNT_BLOCKED'	=> $amount,
            'BEGIN' 			=> $begin,
            'END'   			=> $end,
        );
        $id_event = $this->db->update("ad_availability_event", $ar_event);
        $this->updateEvent($ar_event);
        return $id_event;
    }

    /**
     * Changes the title and description of an event.
     * @param int       $id_event       ID of the event to be edited
     * @param string    $title          Title of the event
     * @param string    $description    Description of the event
     */
    public function editEvent($id_event, $title = null, $amount = null, $description = null) {
        $ar_event = $this->db->fetch1("SELECT * FROM `ad_availability_event` WHERE
            FK_AD=".$this->id_ad." AND ID_AD_AVAILABILITY_EVENT=".(int)$id_event);
        if (is_array($ar_event)) {
            if ($title !== null) {
                $ar_event['TITLE'] = $title;   
            }
            if ($amount !== null) {
                $ar_event['AMOUNT_BLOCKED'] = $amount;   
            }
            if ($description !== null) {
                $ar_event['DESCRIPTION'] = $description;   
            }
            $this->db->update("ad_availability_event", $ar_event);
            $this->updateEvent($ar_event);
            return true;
        }
        return false;
    }

    /**
     * Moves the start date of an event
     * @param int       $id_event       ID of the event to be edited
     * @param int       $deltaDays
     * @param int       $deltaMinutes
     */
    public function deleteEvent($id_event) {
    	if (!$this->unblockTime($ar_event["ID_AD_AVAILABILITY_EVENT"])) {
    		return false;
    	}
        $ar_event = $this->adv->fetch1("SELECT * FROM `ad_availability_event` WHERE
            FK_AD=".$this->id_ad." AND ID_AD_AVAILABILITY_EVENT=".(int)$id_event);
        if (is_array($ar_event)) {
            $result = $this->db->querynow("DELETE FROM `ad_availability_event` WHERE
                FK_AD=".$this->id_ad." AND ID_AD_AVAILABILITY_EVENT=".(int)$id_event);
            if ($result['rsrc']) {
                $dateBegin = date("Y-m-d", strtotime($ar_event['BEGIN']));
                $dateEnd = date("Y-m-d", strtotime($ar_event['END']));
                $ser_worktimes = $this->db->fetch_atom("SELECT AVAILABILITY FROM `ad_master` WHERE ID_AD_MASTER=".(int)$this->id_ad);
                $ar_worktimes = unserialize($ser_worktimes);
                if (is_array($ar_worktimes)) {
                    $this->createAdAvailabilityRange($dateBegin, $dateEnd, $ar_worktimes);
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Moves the start date of an event
     * @param int       $id_event       ID of the event to be edited
     * @param int       $deltaDays
     * @param int       $deltaMinutes
     */
    public function moveEvent($id_event, $deltaDays = 0, $deltaMinutes = 0) {
        $ar_event = $this->db->fetch1("SELECT * FROM `ad_availability_event` WHERE
            FK_AD=".$this->id_ad." AND ID_AD_AVAILABILITY_EVENT=".(int)$id_event);
        if (is_array($ar_event)) {
            $dateBegin = date("Y-m-d", strtotime($ar_event['BEGIN']));
            $dateEnd = date("Y-m-d", strtotime($ar_event['END']));
            // Move event
            $deltaTime = (($deltaDays * 24 * 60 + $deltaMinutes) * 60); 
            $stampBegin = strtotime($ar_event['BEGIN']) + $deltaTime; 
            $stampEnd = strtotime($ar_event['END']) + $deltaTime;
            $ar_event['BEGIN'] = date('Y-m-d H:i:s', $stampBegin);
            $ar_event['END'] = date('Y-m-d H:i:s', $stampEnd);
            $this->db->update("ad_availability_event", $ar_event);
            $this->updateEvent($ar_event);
            if ($deltaTime > 0) {
                $dateEnd = date('Y-m-d', $stampEnd);
            } else {
                $dateBegin = date('Y-m-d', $stampBegin);
            }
            // Update cache
            $ser_worktimes = $this->db->fetch_atom("SELECT AVAILABILITY FROM `ad_master` WHERE ID_AD_MASTER=".(int)$this->id_ad);
            $ar_worktimes = unserialize($ser_worktimes);
            if (is_array($ar_worktimes)) {
                $this->createAdAvailabilityRange($dateBegin, $dateEnd, $ar_worktimes);
            }
            return true;
        }
        return false;
    }

    /**
     * Moves the end date of an event
     * @param int       $id_event       ID of the event to be edited
     * @param int       $deltaDays
     * @param int       $deltaMinutes
     */
    public function resizeEvent($id_event, $deltaDays = 0, $deltaMinutes = 0) {
        $ar_event = $this->db->fetch1("SELECT * FROM `ad_availability_event` WHERE
            FK_AD=".$this->id_ad." AND ID_AD_AVAILABILITY_EVENT=".(int)$id_event);
        if (is_array($ar_event)) {
            $deltaTime = (($deltaDays * 24 * 60 + $deltaMinutes) * 60); 
            $stampEnd = strtotime($ar_event['END']) + $deltaTime;
            $ar_event['END'] = date('Y-m-d H:i:s', $stampEnd);
            $this->db->update("ad_availability_event", $ar_event);
            $this->updateEvent($ar_event);
            // Update cache
            $dateBegin = date("Y-m-d", strtotime($ar_event['BEGIN']));
            $dateEnd = date("Y-m-d", $stampEnd);
            $ser_worktimes = $this->db->fetch_atom("SELECT AVAILABILITY FROM `ad_master` WHERE ID_AD_MASTER=".(int)$this->id_ad);
            $ar_worktimes = unserialize($ser_worktimes);
            if (is_array($ar_worktimes)) {
                $this->createAdAvailabilityRange($dateBegin, $dateEnd, $ar_worktimes);
            }
            return true;
        }
        return false;
    }

	/**
	 * Find ads by availability
	 * @param string   $dateBegin
	 * @param string   $dateEnd
	 * @param bool     $timeBegin
	 */
	public function fetchByDay($dateDay, $useCache = true, $addEvents = true) {
		if ($useCache && $addEvents) {
			$query = "SELECT
					a.*
				FROM `ad_availability` a
				WHERE a.DATE_DAY='".mysql_real_escape_string($dateDay)."'
					AND a.FK_AD=".(int)$this->id_ad."
				ORDER BY a.DATE_DAY ASC";
			$ar_result = $this->db->fetch_table($query);
			$ar_blocked =  $this->combineBlockedAreasCached($ar_result, $dateDay, $dateDay);
			return $ar_blocked;
		} else {
			$query = "SELECT
					a.*,
					b.ID_AD_AVAILABILITY_BLOCK,
					b.TIME_BEGIN as TIME_BEGIN_BLOCKED,
					b.TIME_END as TIME_END_BLOCKED,
	                b.FK_AD_AVAILABILITY_EVENT as FK_EVENT,
	                b.AMOUNT_BLOCKED
				FROM `ad_availability` a
				LEFT JOIN `ad_availability_block` b ON a.ID_AD_AVAILABILITY=b.FK_AD_AVAILABILITY".
				($addEvents ? "" : " AND b.FK_AD_AVAILABILITY_EVENT IS NULL")."
				WHERE a.DATE_DAY='".mysql_real_escape_string($dateDay)."'
					AND a.FK_AD=".(int)$this->id_ad."
				ORDER BY a.DATE_DAY ASC, b.TIME_END ASC";
			$ar_result = $this->db->fetch_table($query);
			$ar_blocked = $this->combineBlockedAreas($ar_result, $dateDay, $dateDay);
			if ($addEvents) {
			    // Write to cache
			    $this->db->querynow("UPDATE `ad_availability` SET SER_BLOCKED='".mysql_real_escape_string(serialize($ar_blocked))."'
			         WHERE FK_AD=".(int)$this->id_ad." AND DATE_DAY='".mysql_real_escape_string($dateDay)."'");
			}
			return $ar_blocked;
		}
	}

	/**
	 * Find ads by availability
	 * @param string   $dateBegin
	 * @param string   $dateEnd
	 * @param bool     $timeBegin
	 */
	public function fetchByRange($dateBegin, $dateEnd, $useCache = true, $addEvents = true) {
		if ($useCache && $addEvents) {
			$query = "SELECT
					a.*
				FROM `ad_availability` a
				WHERE a.DATE_DAY BETWEEN DATE('".mysql_real_escape_string($dateBegin)."') AND DATE('".mysql_real_escape_string($dateEnd)."')
					AND a.FK_AD=".(int)$this->id_ad."
				ORDER BY a.DATE_DAY ASC";
			$ar_result = $this->db->fetch_table($query);
			$ar_blocked = $this->combineBlockedAreasCached($ar_result, $dateBegin, $dateEnd);
            return $ar_blocked;
		} else {
			$query = "SELECT
					a.*,
					b.ID_AD_AVAILABILITY_BLOCK,
					b.TIME_BEGIN as TIME_BEGIN_BLOCKED,
					b.TIME_END as TIME_END_BLOCKED,
	                b.FK_AD_AVAILABILITY_EVENT as FK_EVENT,
	                b.AMOUNT_BLOCKED
				FROM `ad_availability` a
				LEFT JOIN `ad_availability_block` b ON a.ID_AD_AVAILABILITY=b.FK_AD_AVAILABILITY".
				($addEvents ? "" : " AND b.FK_AD_AVAILABILITY_EVENT IS NULL")."
				WHERE a.DATE_DAY BETWEEN DATE('".mysql_real_escape_string($dateBegin)."') AND DATE('".mysql_real_escape_string($dateEnd)."')
					AND a.FK_AD=".(int)$this->id_ad."
				ORDER BY a.DATE_DAY ASC, b.TIME_END ASC";
			$ar_result = $this->db->fetch_table($query);
			$ar_blocked = $this->combineBlockedAreas($ar_result, $dateBegin, $dateEnd);
            if ($addEvents) {
                $dateBlocked = array();
                $dateBegin = strtotime($dateBegin);
                $dateCur = strtotime($dateEnd);
                $dateIndex = count($ar_blocked);
                while ($dateIndex-- > 0) {
                    $arBlockCur = $ar_blocked[$dateIndex];
                    while ($arBlockCur["BEGIN"] < $dateCur) {
                        // Write day to cache
                        $dateDay = date("Y-m-d", $dateCur);
                        $this->db->querynow("UPDATE `ad_availability` SET SER_BLOCKED='".mysql_real_escape_string(serialize($dateBlocked))."'
                             WHERE FK_AD=".(int)$this->id_ad." AND DATE_DAY='".mysql_real_escape_string($dateDay)."'");
                        $dateCur -= (24 * 60 * 60);
                        $dateBlocked = array();
                    }
                    $dateBlocked[] = $arBlockCur;
                }
            }
            return $ar_blocked;
		}
	}
	
    /**
     * Get all events within the given date range
     * @param string $dateBegin
     * @param string $dateEnd
     */
    public function fetchEventsByRange($dateBegin, $dateEnd) {
        $query = "SELECT
                e.*
            FROM `ad_availability_event` e
            WHERE 
        		e.FK_AD=".(int)$this->id_ad." AND
                (e.BEGIN BETWEEN DATE('".mysql_real_escape_string($dateBegin)."') AND DATE('".mysql_real_escape_string($dateEnd)."')
                OR e.END BETWEEN DATE('".mysql_real_escape_string($dateBegin)."') AND DATE('".mysql_real_escape_string($dateEnd)."'))";
        return $this->db->fetch_table($query);
    }

	/**
	 * Set the given time blocked
	 * @param int|null	$id_ad_event
	 * @return boolean
	 */
	public function unblockTime($id_ad_event) {
		$query = "DELETE FROM `ad_availability_block` WHERE FK_AD_AVAILABILITY_EVENT=".$id_ad_event;
		$this->db->querynow($query);
		return true;
	}

	/**
	 * Set the given time blocked
	 * @param int		$id_ad_availability
	 * @param int|null	$id_ad_event
	 * @param string	$begin
	 * @param string	$end
	 * @return boolean
	 */
	public function blockTime($id_ad_availability, $id_ad_event, $begin, $end, $amount = 0) {
		$query = "INSERT INTO `ad_availability_block`
			(FK_AD_AVAILABILITY_EVENT, FK_AD_AVAILABILITY, TIME_BEGIN, TIME_END, AMOUNT_BLOCKED)
			VALUES (".($id_ad_event>0 ? (int)$id_ad_event : "NULL").", ".(int)$id_ad_availability.",
				TIME('".mysql_real_escape_string($begin)."'),
				TIME('".mysql_real_escape_string($end)."'),
				".(int)$amount."
			)";
		$this->db->querynow($query);
		return true;
	}

	/**
	 * Update the given event in the availability
	 * @param array $ar_availability
	 * @param array $ar_event
	 */
	public function updateEvent($ar_event) {
		if (!is_array($ar_event)) {
			// Fehlerhafte Parameter!
			return false;
		}
		$stampBegin = strtotime($ar_event["BEGIN"]);
		$stampEnd = strtotime($ar_event["END"]);
		$ar_days = $this->db->fetch_table("SELECT * FROM `ad_availability`
				WHERE FK_AD=".(int)$this->id_ad."
					AND DATE_DAY BETWEEN DATE('".date("Y-m-d", $stampBegin)."') AND DATE('".date("Y-m-d", $stampEnd)."')");
		foreach ($ar_days as $index => $ar_availability) {
			$stampDayBegin = strtotime($ar_availability["DATE_DAY"]);
			$stampDayEnd = $stampDayBegin + (24 * 60 * 60);
			if ($stampBegin < $stampDayBegin) {
				$ar_event["BEGIN"] = date("Y-m-d H:i:s", $stampDayBegin);
			}
			if ($stampEnd > $stampDayEnd) {
				$ar_event["END"] = date("Y-m-d H:i:s", $stampDayEnd);
			}
			$this->unblockTime($ar_event["ID_AD_AVAILABILITY_EVENT"]);
		}
        $this->blockTime($ar_availability['ID_AD_AVAILABILITY'], $ar_event["ID_AD_AVAILABILITY_EVENT"], 
                    $ar_event["BEGIN"], $ar_event["END"], $ar_event["AMOUNT_BLOCKED"]);
	}

}

?>
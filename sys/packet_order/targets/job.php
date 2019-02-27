<?php

require_once $ab_path."sys/lib.job.php";

class PacketTargetJob {

	/**
	 * Anzeigen aktivieren
	 *
	 * @param int     $id_packet_order
	 */
	public static function activate($db, $id_packet_order) {
		$jobs = JobManagement::getInstance($db);
		$ar_jobs = $db->fetch_nar("
			SELECT ID_JOB, OK
			FROM `packet_order_usage` u
				LEFT JOIN `job` a ON a.ID_JOB=u.FK
			WHERE u.ID_PACKET_ORDER=".(int)$id_packet_order);
		if (!empty($ar_jobs)) {
			foreach ($ar_jobs as $id_job => $freigabe) {
				$jobs->enable($id_job);
			}
		}
		return true;
	}

	/**
	 * Anzeigen deaktivieren
	 *
	 * @param int     $id_packet_order
	 */
	public function deactivate($db, $id_packet_order) {
		$jobs = JobManagement::getInstance($db);
		$ar_jobs = $db->fetch_nar("
			SELECT ID_JOB, OK
			FROM `packet_order_usage` u
				LEFT JOIN `job` a ON a.ID_JOB=u.FK
			WHERE u.ID_PACKET_ORDER=".(int)$id_packet_order);
		if (!empty($ar_jobs)) {
			foreach ($ar_jobs as $id_job => $freigabe) {
				$jobs->disable($id_job);
			}
		}
		return true;
	}
}

?>
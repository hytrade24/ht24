<?php

interface PacketOrderInterface {
	/**
	 * Paket aktivieren
	 *
	 * @return Das Paket (Anzeige/Werbung/...) aktivieren
	 */
	public function activate();

	/**
	 * Paket deaktivieren
	 *
	 * @return Das Paket (Anzeige/Werbung/...) aktivieren
	 */
	public function deactivate();

	/**
	 * Ist das Paket aktiv?
	 *
	 * @return Ist das Paket (Anzeige/Werbung/...) aktiv/online?
	 */
	public function isActive();

	/**
	 * Ist das Paket wiederkehrend?
	 *
	 * @return Wahr wenn das Paket in regelmäßigen Abständen abgerechnet wird ("Abo")
	 */
	public function isRecurring();

	/**
	 * Verknüpfung zu einem "Objekt" (z.B. Anzeige/Werbung/Bild/...) hinzufügen
	 *
	 * @param int $fk_item		ID des Objekts
	 *
	 * @return Wahr wenn die Verknüpfung erfolgreich hinzugefügt wurde
	 */
	public function itemAdd($fk_item);

	/**
	 * Verknüpfung zu einem "Objekt" (z.B. Anzeige/Werbung/Bild/...) entfernen
	 *
	 * @param int $fk_item		ID des Objekts
	 *
	 * @return Wahr wenn die Verknüpfung erfolgreich entfernt wurde
	 */
	public function itemRemove($fk_item);

	/**
	 * Anzahl der maximal erlaubten "Objekte" für dieses Paket
	 *
	 * @return Die max. erlaubte Anzahl an "Objekten" (z.B. Anzahl erlaubter Anzeigen/Bilder/...)
	 */
	public function getCountMax();

	/**
	 * Anzahl der aktuell mit diesem Paket verknüpften "Objekte"
	 *
	 * @return Die aktuelle Anzahl an Verknüpfungen zu "Objekten" (z.B. Anzahl verwendeter Anzeigen/Bilder/...)
	 */
	public function getCountUsed();

	/**
	 * Gibt die ID der Bestellung zurück.
	 *
	 * @return Die Id der Bestellung. (ID_PACKET_ORDER)
	 */
	public function getOrderId();

	/**
	 * Gibt die dazugehörige Rechnung aus in der dieses Paket abgerechnet wurde
	 *
	 * @return array	Die Id der dazugehörigen Rechnungen als array oder null falls keine Rechnungen vorhanden.
	 */
	public function getInvoiceCount($count_paid = false);

	/**
	 * Gibt die dazugehörige Rechnung aus in der dieses Paket abgerechnet wurde
	 *
	 * @return array	Die Id der dazugehörigen Rechnungen als array oder null falls keine Rechnungen vorhanden.
	 */
	public function getInvoiceIds();

	/**
	 * Gibt den Interval zurück in dem dieses Paket abgerechnet wird.
	 * Nur für wiederkehrende Pakete verwenden.
	 *
	 * @see isRecurring
	 * @return Ein als MySQL-Interval verwendbarer String. (z.B. '2 MONTH')
	 */
	public function getPaymentCycle();

	/**
	* Gibt das Datum zurück an dem das Paket das erste mal abgerechnet wurde/wird.
	* Nur für wiederkehrende Pakete verwenden.
	*
	* @see isRecurring
	* @return Datum der ersten Abrechung im Datenbankformat (idr. "Y-m-d H:i:s")
	*/
	public function getPaymentDateFirst();

	/**
	 * Gibt das Datum zurück an dem das Paket das erste mal abgerechnet wurde/wird.
	 * Nur für wiederkehrende Pakete verwenden.
	 *
	 * @see isRecurring
	 * @return Datum der ersten Abrechung im Datenbankformat (idr. "Y-m-d H:i:s")
	 */
	public function getPaymentDateNext();

	/**
	 * Gibt das Datum zurück an dem das Paket das erste mal abgerechnet wurde/wird.
	 * Nur für wiederkehrende Pakete verwenden.
	 *
	 * @see isRecurring
	 * @return Datum der ersten Abrechung im Datenbankformat (idr. "Y-m-d H:i:s")
	 */
	public function getPaymentDateLast();

	/**
	 * Gibt das letzte mögliche Kündigungsdatum zurück.
	 * Nur für wiederkehrende Pakete verwenden.
	 *
	 * @see isRecurring
	 * @return Datum der Kündigungsfrist
	 */
	public function getPaymentDateCancel();

	/**
	 * Gibt die vom Kunden gewählte Zahlungsart für dieses Paket zurück
	 *
	 * @return Ein Enum-Wert entsprechend der Zahlungsart.
	 */
	public function getPaymentType();

	/**
	* Gibt die Id der Paketart zurück
	*
	* @return Id des Pakets (FK_PACKET / ID_PACKET)
	*/
	public function getPacketId();

	/**
	 * Gibt den Namen der Paketart zurück
	 *
	 * @return Name des Pakets (z.B. "Anzeige", "Bild")
	 */
	public function getPacketName();

	/**
	 * Gibt den Typ des Pakets zurück
	 *
	 * @return Typ des Pakets (z.B. "COLLECTION", "BASE_ABO")
	 */
	public function getType();

	/**
	 * Gibt User-ID des Besitzers zurück
	 *
	 * @return ID des Besitzers
	 */
	public function getUserId();
}

?>
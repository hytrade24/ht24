<?php

/**
 *
 * @Version 1.0
 * @author Jürgen Esipov
 * @package Marketplace
 *
 * @uses ebiz_db
 */
require_once dirname(__FILE__).'/IXR_Library.php';

/**
 * Class EvatrLibrary
 */
class EvatrLibrary {

    /**
     * @var string
     */
    protected $server = 'https://evatr.bff-online.de/';
    /**
     * @var null
     */
    protected $client = null;
    /**
     * @var array
     */
    protected $clientData = array(
        'ustid_1' => 'DE202177720',
        'ustid_2' => null,
        'firmenname' => null,
        'ort' => null,
        'plz' => null,
        'strasse' => null,
        'druck' => false
    );

    /**
     * @var null
     */
    private $response = null;

    /**
     * @var array
     */
    private $errorCodes = array(
        200 => 'Die angefragte USt-IdNr. ist gültig.',
        201 => 'Die angefragte USt-IdNr. ist ungültig.',
        202 => 'Die angefragte USt-IdNr. ist ungültig. Sie ist nicht in der Unternehmerdatei des betreffenden EU-Mitgliedstaates registriert. Hinweis: Ihr Geschäftspartner kann seine gültige USt-IdNr. bei der für ihn zuständigen Finanzbehörde in Erfahrung bringen. Möglicherweise muss er einen Antrag stellen, damit seine USt-IdNr. in die Datenbank aufgenommen wird.',
        203 => 'Die angefragte USt-IdNr. ist ungültig. Sie ist erst ab dem ... gültig (siehe Feld \'Gueltig_ab\').',
        204 => 'Die angefragte USt-IdNr. ist ungültig. Sie war im Zeitraum von ... bis ... gültig (siehe Feld \'Gueltig_ab\' und \'Gueltig_bis\').',
        205 => 'Ihre Anfrage kann derzeit durch den angefragten EU-Mitgliedstaat oder aus anderen Gründen nicht beantwortet werden. Bitte versuchen Sie es später noch einmal. Bei wiederholten Problemen wenden Sie sich bitte an das Bundeszentralamt für Steuern - Dienstsitz Saarlouis.',
        206 => 'Ihre deutsche USt-IdNr. ist ungültig. Eine Bestätigungsanfrage ist daher nicht möglich. Den Grund hierfür können Sie beim Bundeszentralamt für Steuern - Dienstsitz Saarlouis - erfragen.',
        207 => 'Ihnen wurde die deutsche USt-IdNr. ausschliesslich zu Zwecken der Besteuerung des innergemeinschaftlichen Erwerbs erteilt. Sie sind somit nicht berechtigt, Bestätigungsanfragen zu stellen.',
        208 => 'Für die von Ihnen angefragte USt-IdNr. läuft gerade eine Anfrage von einem anderen Nutzer. Eine Bearbeitung ist daher nicht möglich. Bitte versuchen Sie es später noch einmal.',
        209 => 'Die angefragte USt-IdNr. ist ungültig. Sie entspricht nicht dem Aufbau der für diesen EU-Mitgliedstaat gilt. ( Aufbau der USt-IdNr. aller EU-Länder)',
        210 => 'Die angefragte USt-IdNr. ist ungültig. Sie entspricht nicht den Prüfziffernregeln die für diesen EU-Mitgliedstaat gelten.',
        211 => 'Die angefragte USt-IdNr. ist ungültig. Sie enthält unzulässige Zeichen (wie z.B. Leerzeichen oder Punkt oder Bindestrich usw.).',
        212 => 'Die angefragte USt-IdNr. ist ungültig. Sie enthält ein unzulässiges Länderkennzeichen.',
        213 => 'Die Abfrage einer deutschen USt-IdNr. ist nicht möglich.',
        214 => 'Ihre deutsche USt-IdNr. ist fehlerhaft. Sie beginnt mit \'DE\' gefolgt von 9 Ziffern.',
        215 => 'Ihre Anfrage enthält nicht alle notwendigen Angaben für eine einfache Bestätigungsanfrage (Ihre deutsche USt-IdNr. und die ausl. USt-IdNr.). Ihre Anfrage kann deshalb nicht bearbeitet werden.',
        216 => 'Ihre Anfrage enthält nicht alle notwendigen Angaben für eine qualifizierte Bestätigungsanfrage (Ihre deutsche USt-IdNr., die ausl. USt-IdNr., Firmenname einschl. Rechtsform und Ort). Ihre Anfrage kann deshalb nicht bearbeitet werden.',
        217 => 'Bei der Verarbeitung der Daten aus dem angefragten EU-Mitgliedstaat ist ein Fehler aufgetreten. Ihre Anfrage kann deshalb nicht bearbeitet werden.',
        218 => 'Eine qualifizierte Bestätigung ist zur Zeit nicht möglich. Es wurde eine einfache Bestätigungsanfrage mit folgendem Ergebnis durchgeführt: Die angefragte USt-IdNr. ist gültig.',
        219 => 'Bei der Durchführung der qualifizierten Bestätigungsanfrage ist ein Fehler aufgetreten. Es wurde eine einfache Bestätigungsanfrage mit folgendem Ergebnis durchgeführt: Die angefragte USt-IdNr. ist gültig.',
        220 => 'Bei der Anforderung der amtlichen Bestätigungsmitteilung ist ein Fehler aufgetreten. Sie werden kein Schreiben erhalten.',
        221 => 'Die Anfragedaten enthalten nicht alle notwendigen Parameter oder einen ungültigen Datentyp. Weitere Informationen erhalten Sie bei den Hinweisen zum Schnittstelle - Aufruf.',
        999 => 'Eine Bearbeitung Ihrer Anfrage ist zurzeit nicht möglich. Bitte versuchen Sie es später noch einmal.',
    );

    /**
     * @var array
     */
    private $errorCodesExtended = array(
        'A' => 'stimmt überein',
        'B' => 'stimmt nicht überein',
        'C' => 'nicht angefragt',
        'D' => 'vom EU-Mitgliedsstaat nicht mitgeteilt'
    );

    /**
     * @param $server
     */
    public function __construct($server = null) {
        if ($server !== null) {
            $this->server = $server;
        }

        $this->client = new IXR_Client($this->getServer());
    }

    /**
     * @param array $clientData
     */
    public function setClientData($clientData)
    {
        $clientData = array_change_key_case($clientData, CASE_LOWER);
        $this->clientData = array_merge($this->clientData, $clientData);
    }

    /**
     * @return array
     */
    public function getClientData()
    {
        return $this->clientData;
    }

    /**
     * @param string $server
     */
    public function setServer($server)
    {
        $this->server = $server;
        $this->client = new IXR_Client($this->getServer());
    }

    /**
     * @return string
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     *
     */
    public function getResponse() {
        return $this->response;
    }

    /**
     * @param $clientData
     * @param int $extended
     * @return bool
     */
    public function verify($clientData, $extended = 0) {
        $this->setClientData($clientData);

        if (!$extended) {
            return $this->verifyNormal();
        }
        else {
            return $this->verifyExtended();
        }
    }

    /**
     * @internal param $clientData
     * @return bool
     * @internal param $string
     */
    protected  function verifyNormal() {
        $clientData = $this->getClientData();
        $hasRequiredData = $clientData['ustid_1'] !== null && $clientData['ustid_2'] !== null;

        if ($hasRequiredData) {
            if (!$this->client->query('evatrRPC', $clientData['ustid_1'], $clientData['ustid_2'])) {
                return false;
            }

            $this->response = $this->translateResponse($this->client->getResponse());

            if ($this->response['ErrorCode'] != 200) {
                return true;
            }

            return false;
        }

        $this->response = array(
            'ErrorCode' => 215,
            'Message' => $this->errorCodes[215]
        );

        $this->response = array_merge($this->response, $clientData);

        return false;
    }

    /**
     * @return bool
     */
    protected function verifyExtended() {
        $clientData = $this->getClientData();
        $hasRequiredData = $clientData['ustid_1'] !== null && $clientData['ustid_2'] !== null &&
                           $clientData['firmenname'] !== null && $clientData['ort'] !== null;

        if ($hasRequiredData) {
            $this->client->query(
                'evatrRPC', $clientData['ustid_1'], $clientData['ustid_2'],
                $clientData['firmenname'], $clientData['ort'], $clientData['plz'],
                $clientData['strasse'], ($clientData['druck'] ? 'ja' : 'nein')
            );

            $this->response = $this->translateResponse($this->client->getResponse());

            if ($this->response['ErrorCode'] == 200) {
                return true;
            }

            return false;
        }

        $this->response = array(
            'ErrorCode' => 216,
            'Message' => $this->errorCodes[216]
        );

        $this->response = array_merge($this->response, $clientData);

        return false;
    }

    /**
     * @param $response
     * @return array
     */
    protected function translateResponse($response) {
        $responseAsArray = array();
        
        $response = new SimpleXMLElement($response);

        foreach ($response as $data) {
            $data = $data->value->array->data;
            $key = (string)$data->value[0]->string;
            $value = (string)$data->value[1]->string;

            if ($key === 'ErrorCode') {
                $responseAsArray['Message'] = $this->errorCodes[(int)$value];
            }

            if (substr($key, 0, 4) === 'Erg_') {
                $responseAsArray[$key . '_Message'] = $this->errorCodesExtended[$value];
            }

            $responseAsArray[$key] = $value;
        }

        return $responseAsArray;
    }
}

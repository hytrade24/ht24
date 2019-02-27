<?php

class PaymentPinCodeEmail {

    private $db;

    function __construct()
    {
        global $db;
        $this->db = $db;
    }

    public function getDb() {
        return $this->db;
    }

    public function checkAndSendPossibleEmails() {
        $sql = 'SELECT b.*
        FROM user_account_pincode_export a
        INNER JOIN user b
        ON a.IS_EMAIL_SENT = 0
        AND TRUNCATE((UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(a.STAMP))/60/60/24,0) >= a.PINCODE_EMAIL
        AND b.FK_USER_ACCOUNT_PINCODE_EXPORT = a.ID_USER_ACCOUNT_PINCODE_EXPORT';

        $arResult = $this->db->fetch_table( $sql );

        foreach ( $arResult as $row ) {
            sendMailTemplateToUser(
                0,
                $row['ID_USER'],
                'PAYMENT_PINCODE_EMAIL',
                $row
            );
        }

        $sql = 'UPDATE user_account_pincode_export a
        INNER JOIN user b
        ON a.IS_EMAIL_SENT = 0
        AND TRUNCATE((UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(a.STAMP))/60/60/24,0) >= a.PINCODE_EMAIL
        AND b.FK_USER_ACCOUNT_PINCODE_EXPORT = a.ID_USER_ACCOUNT_PINCODE_EXPORT
        SET a.IS_EMAIL_SENT = 1, a.EMAIL_SENT_STAMP = "'.date("Y-m-d H:i:s") .'"' ;

        $result = $this->db->fetch_table( $sql );

        return $result;
    }

    public function sendForceEmails( $export_no ) {
        $sql = 'SELECT b.*
        FROM user_account_pincode_export a
        INNER JOIN user b
        ON a.EXPORT_NO = '.$export_no.'
        AND b.FK_USER_ACCOUNT_PINCODE_EXPORT = a.ID_USER_ACCOUNT_PINCODE_EXPORT';

        $arResult = $this->db->fetch_table( $sql );

        foreach ( $arResult as $row ) {
            sendMailTemplateToUser(
                0,
                $row['ID_USER'],
                'PAYMENT_PINCODE_EMAIL',
                $row
            );
        }

        $sql = 'UPDATE user_account_pincode_export a
        SET a.IS_EMAIL_SENT = 1, a.EMAIL_SENT_STAMP = "'.date("Y-m-d H:i:s").'"
        WHERE a.EXPORT_NO = ' . $export_no;

        $result = $this->db->querynow( $sql );

        return $result;
    }

}
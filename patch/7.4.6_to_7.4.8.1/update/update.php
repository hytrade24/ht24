<?php

global $db;

$query = 'SELECT u.ID_USER, u.PAYMENT_ADAPTER_CONFIG, u.PAYMENT_ADAPTER_SELLER_CONFIG
					FROM user u';

$result = $db->fetch_table( $query );

$key = '1a9b545312cd4aa2cbd6e9f8cd5e7a9cfge34vrtf';
$iv = 'q12331adf2sds1z22Adsa2das23125c2dfew2f12d';
$encrypt_method = "AES-256-CBC";

function generate_iv( $id, $key, $iv, $encrypt_method ) {
	$secret_key = $key;
	$secret_iv = $iv;

	// hash
	$key = hash('sha256', $id.$secret_key);

	// iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
	$iv = substr(hash('sha256', $secret_iv), 0, 16);

	$data = new stdClass();
	$data->iv = $iv;
	$data->key = $key;

	return $data;
}

function encrypt( $string, $id, $key, $iv, $encrypt_method ) {

	$data = generate_iv( $id, $key, $iv, $encrypt_method );

	$output = openssl_encrypt($string, $encrypt_method, $data->key, 0, $data->iv);
	$output = base64_encode($output);

	return $output;

}

foreach ( $result as $row ) {
	if ( $row["PAYMENT_ADAPTER_CONFIG"] != "" ) {

		$encrypted_serialize_data = '';
		$serialize_data = unserialize(
			$row["PAYMENT_ADAPTER_CONFIG"]
		);
		if ( $serialize_data != false ) {
			$encrypted_serialize_data = encrypt(
				$row["PAYMENT_ADAPTER_CONFIG"],
				$row["ID_USER"],
				$key,
				$iv,
				$encrypt_method
			);
			$db->update(
				"user",
				array(
					"ID_USER"   =>  $row["ID_USER"],
					"PAYMENT_ADAPTER_CONFIG"    => $encrypted_serialize_data
				)
			);
		}
	}
	if ( $row["PAYMENT_ADAPTER_SELLER_CONFIG"] != "" ) {

		$encrypted_serialize_data = '';
		$serialize_data = unserialize(
			$row["PAYMENT_ADAPTER_SELLER_CONFIG"]
		);
		if ( $serialize_data != false ) {
			$encrypted_serialize_data = encrypt(
				$row["PAYMENT_ADAPTER_SELLER_CONFIG"],
				$row["ID_USER"],
				$key,
				$iv,
				$encrypt_method
			);
		}
		$db->update(
			"user",
			array(
				"ID_USER"   =>  $row["ID_USER"],
				"PAYMENT_ADAPTER_SELLER_CONFIG"    => $encrypted_serialize_data
			)
		);
	}
}
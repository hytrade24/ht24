<?php
function translateText( $text) 
{
    $apiKey = 'AIzaSyDfcS6atJqDbmPqoAf90Eg5ce0EIOSu27g';
    $text = $text ;
    $url = 'https://www.googleapis.com/language/translate/v2?key=' . $apiKey . '&q=' . rawurlencode($text) . '&source=de&target=en';

    $handle = curl_init($url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($handle);
    $responseDecoded = json_decode($response, true);
    $responseCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);      //Here we fetch the HTTP response code
    curl_close($handle);

    if($responseCode == 200) 
    {
        return $responseDecoded['data']['translations'][0]['translatedText'] ; 
    }
    else {
        return '';
        //echo 'Fetching translation failed! Server response code:' . $responseCode . '<br>';
        //echo 'Error description: ' . $responseDecoded['error']['errors'][0]['message'];
    }
}
?>

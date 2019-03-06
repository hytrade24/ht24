<?php
include_once "sys/GoogleTranslator.php";

//echo translateText( "Sun Hydraulics 2/2 - Wegesitzventil - DTBFXHV740-224" );

// include_once "src/MicrosoftTranslator.php";

// $text = "Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF
// Hydraulikmotor OMRW 375 151-6438, Schluckvolumen in cm³/U: 372,6, Welle: Konisch 1:10 Ø1 1/4'', Flansch: Radflansch, Anschlusspos.: Seitlich, Anschlussgröße: 7/8-14 UNF

// ";

// $text = "Hydraulikmotor OMRW 375 151-6438";
// //for( $i = 0 ; $i < 30 ; $i++ )
// {
//     echo $result = Translate( $text );
// }

?>
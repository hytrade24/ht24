<?php

$ar_nav_urls = array (
  0 => 
  array (
    'IDENT' => 'marktplatz',
    'ID_NAV_URL' => '17',
    'FK_NAV' => '951',
    'FK_LANG' => '2',
    'PRIORITY' => '0',
    'URL_PATTERN' => '/products/',
    'URL_MANUAL' => '0',
    'URL_REGEXP' => '/^\\/products\\/$/',
    'URL_MAPPING' => 'a:0:{}',
  ),
  1 => 
  array (
    'IDENT' => 'marktplatz',
    'ID_NAV_URL' => '19',
    'FK_NAV' => '951',
    'FK_LANG' => '2',
    'PRIORITY' => '0',
    'URL_PATTERN' => '/products/{2}_{#1}',
    'URL_MANUAL' => '0',
    'URL_REGEXP' => '/^\\/products\\/(.*)_([0-9-]*)$/',
    'URL_MAPPING' => 'a:2:{i:2;s:2:"$1";i:1;s:2:"$2";}',
  ),
);
$ar_nav_urls_by_id = array (
  951 => 
  array (
    0 => 
    array (
      'IDENT' => 'marktplatz',
      'ID_NAV_URL' => '17',
      'FK_NAV' => '951',
      'FK_LANG' => '2',
      'PRIORITY' => '0',
      'URL_PATTERN' => '/products/',
      'URL_MANUAL' => '0',
      'URL_REGEXP' => '/^\\/products\\/$/',
      'URL_MAPPING' => 'a:0:{}',
    ),
    1 => 
    array (
      'IDENT' => 'marktplatz',
      'ID_NAV_URL' => '19',
      'FK_NAV' => '951',
      'FK_LANG' => '2',
      'PRIORITY' => '0',
      'URL_PATTERN' => '/products/{2}_{#1}',
      'URL_MANUAL' => '0',
      'URL_REGEXP' => '/^\\/products\\/(.*)_([0-9-]*)$/',
      'URL_MAPPING' => 'a:2:{i:2;s:2:"$1";i:1;s:2:"$2";}',
    ),
  ),
);
<?php

$ar_nav_urls = array (
  0 => 
  array (
    'IDENT' => 'marktplatz',
    'ID_NAV_URL' => '21',
    'FK_NAV' => '951',
    'FK_LANG' => '1',
    'PRIORITY' => '12',
    'URL_PATTERN' => '/produkte/{REGION_NAME}/{2}_{#1}_{$3}_{$4}_{$5}_{#6}_{$7}',
    'URL_MANUAL' => '0',
    'URL_REGEXP' => '/^\\/produkte\\/(.*)\\/(.*)_([0-9-]*)_([^\\/]*)_([^\\/]*)_([^\\/]*)_([0-9-]*)_([^\\/]*)$/',
    'URL_MAPPING' => 'a:8:{s:11:"REGION_NAME";s:2:"$1";i:2;s:2:"$2";i:1;s:2:"$3";i:3;s:2:"$4";i:4;s:2:"$5";i:5;s:2:"$6";i:6;s:2:"$7";i:7;s:2:"$8";}',
  ),
  1 => 
  array (
    'IDENT' => 'marktplatz',
    'ID_NAV_URL' => '20',
    'FK_NAV' => '951',
    'FK_LANG' => '1',
    'PRIORITY' => '11',
    'URL_PATTERN' => '/produkte/{REGION_NAME}/{2}_{#1}',
    'URL_MANUAL' => '0',
    'URL_REGEXP' => '/^\\/produkte\\/(.*)\\/(.*)_([0-9-]*)$/',
    'URL_MAPPING' => 'a:3:{s:11:"REGION_NAME";s:2:"$1";i:2;s:2:"$2";i:1;s:2:"$3";}',
  ),
  2 => 
  array (
    'IDENT' => 'marktplatz',
    'ID_NAV_URL' => '12',
    'FK_NAV' => '951',
    'FK_LANG' => '1',
    'PRIORITY' => '2',
    'URL_PATTERN' => '/produkte/{2}_{#1}_{$3}_{$4}_{$5}_{#6}_{$7}',
    'URL_MANUAL' => '0',
    'URL_REGEXP' => '/^\\/produkte\\/(.*)_([0-9-]*)_([^\\/]*)_([^\\/]*)_([^\\/]*)_([0-9-]*)_([^\\/]*)$/',
    'URL_MAPPING' => 'a:7:{i:2;s:2:"$1";i:1;s:2:"$2";i:3;s:2:"$3";i:4;s:2:"$4";i:5;s:2:"$5";i:6;s:2:"$6";i:7;s:2:"$7";}',
  ),
  3 => 
  array (
    'IDENT' => 'marktplatz',
    'ID_NAV_URL' => '1',
    'FK_NAV' => '951',
    'FK_LANG' => '1',
    'PRIORITY' => '1',
    'URL_PATTERN' => '/produkte/{2}_{#1}',
    'URL_MANUAL' => '0',
    'URL_REGEXP' => '/^\\/produkte\\/(.*)_([0-9-]*)$/',
    'URL_MAPPING' => 'a:2:{i:2;s:2:"$1";i:1;s:2:"$2";}',
  ),
  4 => 
  array (
    'IDENT' => 'marktplatz_anzeige',
    'ID_NAV_URL' => '11',
    'FK_NAV' => '985',
    'FK_LANG' => '1',
    'PRIORITY' => '1',
    'URL_PATTERN' => '/anzeige/{KAT_PATH}/{$2}_{#1}',
    'URL_MANUAL' => '0',
    'URL_REGEXP' => '/^\\/anzeige\\/(.*)\\/([^\\/]*)_([0-9-]*)$/',
    'URL_MAPPING' => 'a:3:{s:8:"KAT_PATH";s:2:"$1";i:2;s:2:"$2";i:1;s:2:"$3";}',
  ),
  5 => 
  array (
    'IDENT' => 'marktplatz',
    'ID_NAV_URL' => '2',
    'FK_NAV' => '951',
    'FK_LANG' => '1',
    'PRIORITY' => '0',
    'URL_PATTERN' => '/produkte/',
    'URL_MANUAL' => '0',
    'URL_REGEXP' => '/^\\/produkte\\/$/',
    'URL_MAPPING' => 'a:0:{}',
  ),
  6 => 
  array (
    'IDENT' => 'news',
    'ID_NAV_URL' => '15',
    'FK_NAV' => '1061',
    'FK_LANG' => '1',
    'PRIORITY' => '0',
    'URL_PATTERN' => '/blog/{$2}_{#1}',
    'URL_MANUAL' => '0',
    'URL_REGEXP' => '/^\\/blog\\/([^\\/]*)_([0-9]*)$/',
    'URL_MAPPING' => 'a:2:{i:2;s:2:"$1";i:1;s:2:"$2";}',
  ),
  7 => 
  array (
    'IDENT' => 'marktplatz_anzeige',
    'ID_NAV_URL' => '4',
    'FK_NAV' => '985',
    'FK_LANG' => '1',
    'PRIORITY' => '0',
    'URL_PATTERN' => '/anzeige/{$2}_{#1}',
    'URL_MANUAL' => '0',
    'URL_REGEXP' => '/^\\/anzeige\\/([^\\/]*)_([0-9-]*)$/',
    'URL_MAPPING' => 'a:2:{i:2;s:2:"$1";i:1;s:2:"$2";}',
  ),
  8 => 
  array (
    'IDENT' => 'news',
    'ID_NAV_URL' => '5',
    'FK_NAV' => '1061',
    'FK_LANG' => '1',
    'PRIORITY' => '0',
    'URL_PATTERN' => '/blog/',
    'URL_MANUAL' => '0',
    'URL_REGEXP' => '/^\\/blog\\/$/',
    'URL_MAPPING' => 'a:0:{}',
  ),
  9 => 
  array (
    'IDENT' => 'jobs',
    'ID_NAV_URL' => '7',
    'FK_NAV' => '1194',
    'FK_LANG' => '1',
    'PRIORITY' => '0',
    'URL_PATTERN' => '/stellenangebote/',
    'URL_MANUAL' => '0',
    'URL_REGEXP' => '/^\\/stellenangebote\\/$/',
    'URL_MAPPING' => 'a:0:{}',
  ),
  10 => 
  array (
    'IDENT' => 'fuer-haendler',
    'ID_NAV_URL' => '8',
    'FK_NAV' => '1300',
    'FK_LANG' => '1',
    'PRIORITY' => '0',
    'URL_PATTERN' => '/blog/fuer-haendler.htm',
    'URL_MANUAL' => '0',
    'URL_REGEXP' => '/^\\/blog\\/fuer\\-haendler\\.htm$/',
    'URL_MAPPING' => 'a:0:{}',
  ),
  11 => 
  array (
    'IDENT' => 'fuer-haendler',
    'ID_NAV_URL' => '9',
    'FK_NAV' => '1300',
    'FK_LANG' => '1',
    'PRIORITY' => '0',
    'URL_PATTERN' => '/blog/fuer-haendler/{2}_{#1}.htm',
    'URL_MANUAL' => '0',
    'URL_REGEXP' => '/^\\/blog\\/fuer\\-haendler\\/(.*)_([0-9]*)\\.htm$/',
    'URL_MAPPING' => 'a:2:{i:2;s:2:"$1";i:1;s:2:"$2";}',
  ),
  12 => 
  array (
    'IDENT' => 'view_user_jobs',
    'ID_NAV_URL' => '14',
    'FK_NAV' => '1193',
    'FK_LANG' => '1',
    'PRIORITY' => '0',
    'URL_PATTERN' => '/stelleangebot/{$6}_{#2}_{#5}_{#3}_{$1}_{4}',
    'URL_MANUAL' => '0',
    'URL_REGEXP' => '/^\\/stelleangebot\\/([^\\/]*)_([0-9]*)_([0-9]*)_([0-9]*)_([^\\/]*)_(.*)$/',
    'URL_MAPPING' => 'a:6:{i:6;s:2:"$1";i:2;s:2:"$2";i:5;s:2:"$3";i:3;s:2:"$4";i:1;s:2:"$5";i:4;s:2:"$6";}',
  ),
  13 => 
  array (
    'IDENT' => 'archiv',
    'ID_NAV_URL' => '16',
    'FK_NAV' => '660',
    'FK_LANG' => '1',
    'PRIORITY' => '0',
    'URL_PATTERN' => '/blog/archiv.htm',
    'URL_MANUAL' => '0',
    'URL_REGEXP' => '/^\\/blog\\/archiv\\.htm$/',
    'URL_MAPPING' => 'a:0:{}',
  ),
);
$ar_nav_urls_by_id = array (
  951 => 
  array (
    0 => 
    array (
      'IDENT' => 'marktplatz',
      'ID_NAV_URL' => '21',
      'FK_NAV' => '951',
      'FK_LANG' => '1',
      'PRIORITY' => '12',
      'URL_PATTERN' => '/produkte/{REGION_NAME}/{2}_{#1}_{$3}_{$4}_{$5}_{#6}_{$7}',
      'URL_MANUAL' => '0',
      'URL_REGEXP' => '/^\\/produkte\\/(.*)\\/(.*)_([0-9-]*)_([^\\/]*)_([^\\/]*)_([^\\/]*)_([0-9-]*)_([^\\/]*)$/',
      'URL_MAPPING' => 'a:8:{s:11:"REGION_NAME";s:2:"$1";i:2;s:2:"$2";i:1;s:2:"$3";i:3;s:2:"$4";i:4;s:2:"$5";i:5;s:2:"$6";i:6;s:2:"$7";i:7;s:2:"$8";}',
    ),
    1 => 
    array (
      'IDENT' => 'marktplatz',
      'ID_NAV_URL' => '20',
      'FK_NAV' => '951',
      'FK_LANG' => '1',
      'PRIORITY' => '11',
      'URL_PATTERN' => '/produkte/{REGION_NAME}/{2}_{#1}',
      'URL_MANUAL' => '0',
      'URL_REGEXP' => '/^\\/produkte\\/(.*)\\/(.*)_([0-9-]*)$/',
      'URL_MAPPING' => 'a:3:{s:11:"REGION_NAME";s:2:"$1";i:2;s:2:"$2";i:1;s:2:"$3";}',
    ),
    2 => 
    array (
      'IDENT' => 'marktplatz',
      'ID_NAV_URL' => '12',
      'FK_NAV' => '951',
      'FK_LANG' => '1',
      'PRIORITY' => '2',
      'URL_PATTERN' => '/produkte/{2}_{#1}_{$3}_{$4}_{$5}_{#6}_{$7}',
      'URL_MANUAL' => '0',
      'URL_REGEXP' => '/^\\/produkte\\/(.*)_([0-9-]*)_([^\\/]*)_([^\\/]*)_([^\\/]*)_([0-9-]*)_([^\\/]*)$/',
      'URL_MAPPING' => 'a:7:{i:2;s:2:"$1";i:1;s:2:"$2";i:3;s:2:"$3";i:4;s:2:"$4";i:5;s:2:"$5";i:6;s:2:"$6";i:7;s:2:"$7";}',
    ),
    3 => 
    array (
      'IDENT' => 'marktplatz',
      'ID_NAV_URL' => '1',
      'FK_NAV' => '951',
      'FK_LANG' => '1',
      'PRIORITY' => '1',
      'URL_PATTERN' => '/produkte/{2}_{#1}',
      'URL_MANUAL' => '0',
      'URL_REGEXP' => '/^\\/produkte\\/(.*)_([0-9-]*)$/',
      'URL_MAPPING' => 'a:2:{i:2;s:2:"$1";i:1;s:2:"$2";}',
    ),
    4 => 
    array (
      'IDENT' => 'marktplatz',
      'ID_NAV_URL' => '2',
      'FK_NAV' => '951',
      'FK_LANG' => '1',
      'PRIORITY' => '0',
      'URL_PATTERN' => '/produkte/',
      'URL_MANUAL' => '0',
      'URL_REGEXP' => '/^\\/produkte\\/$/',
      'URL_MAPPING' => 'a:0:{}',
    ),
  ),
  985 => 
  array (
    0 => 
    array (
      'IDENT' => 'marktplatz_anzeige',
      'ID_NAV_URL' => '11',
      'FK_NAV' => '985',
      'FK_LANG' => '1',
      'PRIORITY' => '1',
      'URL_PATTERN' => '/anzeige/{KAT_PATH}/{$2}_{#1}',
      'URL_MANUAL' => '0',
      'URL_REGEXP' => '/^\\/anzeige\\/(.*)\\/([^\\/]*)_([0-9-]*)$/',
      'URL_MAPPING' => 'a:3:{s:8:"KAT_PATH";s:2:"$1";i:2;s:2:"$2";i:1;s:2:"$3";}',
    ),
    1 => 
    array (
      'IDENT' => 'marktplatz_anzeige',
      'ID_NAV_URL' => '4',
      'FK_NAV' => '985',
      'FK_LANG' => '1',
      'PRIORITY' => '0',
      'URL_PATTERN' => '/anzeige/{$2}_{#1}',
      'URL_MANUAL' => '0',
      'URL_REGEXP' => '/^\\/anzeige\\/([^\\/]*)_([0-9-]*)$/',
      'URL_MAPPING' => 'a:2:{i:2;s:2:"$1";i:1;s:2:"$2";}',
    ),
  ),
  1061 => 
  array (
    0 => 
    array (
      'IDENT' => 'news',
      'ID_NAV_URL' => '15',
      'FK_NAV' => '1061',
      'FK_LANG' => '1',
      'PRIORITY' => '0',
      'URL_PATTERN' => '/blog/{$2}_{#1}',
      'URL_MANUAL' => '0',
      'URL_REGEXP' => '/^\\/blog\\/([^\\/]*)_([0-9]*)$/',
      'URL_MAPPING' => 'a:2:{i:2;s:2:"$1";i:1;s:2:"$2";}',
    ),
    1 => 
    array (
      'IDENT' => 'news',
      'ID_NAV_URL' => '5',
      'FK_NAV' => '1061',
      'FK_LANG' => '1',
      'PRIORITY' => '0',
      'URL_PATTERN' => '/blog/',
      'URL_MANUAL' => '0',
      'URL_REGEXP' => '/^\\/blog\\/$/',
      'URL_MAPPING' => 'a:0:{}',
    ),
  ),
  1194 => 
  array (
    0 => 
    array (
      'IDENT' => 'jobs',
      'ID_NAV_URL' => '7',
      'FK_NAV' => '1194',
      'FK_LANG' => '1',
      'PRIORITY' => '0',
      'URL_PATTERN' => '/stellenangebote/',
      'URL_MANUAL' => '0',
      'URL_REGEXP' => '/^\\/stellenangebote\\/$/',
      'URL_MAPPING' => 'a:0:{}',
    ),
  ),
  1300 => 
  array (
    0 => 
    array (
      'IDENT' => 'fuer-haendler',
      'ID_NAV_URL' => '8',
      'FK_NAV' => '1300',
      'FK_LANG' => '1',
      'PRIORITY' => '0',
      'URL_PATTERN' => '/blog/fuer-haendler.htm',
      'URL_MANUAL' => '0',
      'URL_REGEXP' => '/^\\/blog\\/fuer\\-haendler\\.htm$/',
      'URL_MAPPING' => 'a:0:{}',
    ),
    1 => 
    array (
      'IDENT' => 'fuer-haendler',
      'ID_NAV_URL' => '9',
      'FK_NAV' => '1300',
      'FK_LANG' => '1',
      'PRIORITY' => '0',
      'URL_PATTERN' => '/blog/fuer-haendler/{2}_{#1}.htm',
      'URL_MANUAL' => '0',
      'URL_REGEXP' => '/^\\/blog\\/fuer\\-haendler\\/(.*)_([0-9]*)\\.htm$/',
      'URL_MAPPING' => 'a:2:{i:2;s:2:"$1";i:1;s:2:"$2";}',
    ),
  ),
  1193 => 
  array (
    0 => 
    array (
      'IDENT' => 'view_user_jobs',
      'ID_NAV_URL' => '14',
      'FK_NAV' => '1193',
      'FK_LANG' => '1',
      'PRIORITY' => '0',
      'URL_PATTERN' => '/stelleangebot/{$6}_{#2}_{#5}_{#3}_{$1}_{4}',
      'URL_MANUAL' => '0',
      'URL_REGEXP' => '/^\\/stelleangebot\\/([^\\/]*)_([0-9]*)_([0-9]*)_([0-9]*)_([^\\/]*)_(.*)$/',
      'URL_MAPPING' => 'a:6:{i:6;s:2:"$1";i:2;s:2:"$2";i:5;s:2:"$3";i:3;s:2:"$4";i:1;s:2:"$5";i:4;s:2:"$6";}',
    ),
  ),
  660 => 
  array (
    0 => 
    array (
      'IDENT' => 'archiv',
      'ID_NAV_URL' => '16',
      'FK_NAV' => '660',
      'FK_LANG' => '1',
      'PRIORITY' => '0',
      'URL_PATTERN' => '/blog/archiv.htm',
      'URL_MANUAL' => '0',
      'URL_REGEXP' => '/^\\/blog\\/archiv\\.htm$/',
      'URL_MAPPING' => 'a:0:{}',
    ),
  ),
);
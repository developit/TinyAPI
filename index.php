<?php

$tinyapi_install_dir = dirname(__FILE__);

// TinyAPI Framework
//  -- BOOTSTRAP --

$tinyapi_config = array(
	'method_dir'	=> $tinyapi_install_dir . '/public/',
	'base_uri' 		=> substr(dirname(__FILE__), strlen($_SERVER['DOCUMENT_ROOT'])) . '/',
	'formatters'	=> array(
		'xml'	=> 'XMLFormatter'
	)
);

include_once $tinyapi_install_dir . '/tinyapi/lib/formatters/xml.inc.php';

include $tinyapi_install_dir . '/tinyapi/main.php';

?>
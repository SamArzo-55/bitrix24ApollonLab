<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

return [
	'js' => './dist/eventfiles.bundle.js',
	'css' => './dist/eventfiles.bundle.css',
	'rel' => [
		'main.core',
		'main.core.events',
	],
	'skip_core' => true,
];

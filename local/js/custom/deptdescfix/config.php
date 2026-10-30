<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

return [
	'js' => './dist/deptdescfix.bundle.js',
	'css' => './dist/deptdescfix.bundle.css',
	'rel' => [
		'main.core',
	],
	'skip_core' => true,
];

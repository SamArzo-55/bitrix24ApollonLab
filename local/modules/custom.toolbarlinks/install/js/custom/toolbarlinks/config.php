<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

return [
	'js' => './dist/toolbarlinks.bundle.js',
	'rel' => [
		'main.core',
		'ui.dialogs.messagebox',
	],
	'skip_core' => true,
	'settings' => [
		'messages' => [
			'title' => Loc::getMessage('CUSTOM_TOOLBARLINKS_JS_TITLE'),
			'label' => Loc::getMessage('CUSTOM_TOOLBARLINKS_JS_LABEL'),
			'save' => Loc::getMessage('CUSTOM_TOOLBARLINKS_JS_SAVE'),
			'cancel' => Loc::getMessage('CUSTOM_TOOLBARLINKS_JS_CANCEL'),
			'error' => Loc::getMessage('CUSTOM_TOOLBARLINKS_JS_ERROR'),
		],
	],
];

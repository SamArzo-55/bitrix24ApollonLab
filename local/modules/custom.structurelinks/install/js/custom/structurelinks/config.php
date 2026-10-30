<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$links = [];
if (Loader::includeModule('custom.structurelinks'))
{
	$links = \Custom\StructureLinks\Links::getAll();
}

return [
	'js' => './dist/structurelinks.bundle.js',
	'css' => './dist/structurelinks.bundle.css',
	'rel' => [
		'main.core',
		'ui.dialogs.messagebox',
	],
	'skip_core' => true,
	'settings' => [
		'links' => $links,
		'messages' => [
			'editTitle' => Loc::getMessage('CUSTOM_STRUCTURELINKS_JS_EDIT_TITLE'),
			'edit' => Loc::getMessage('CUSTOM_STRUCTURELINKS_JS_EDIT'),
			'addFirst' => Loc::getMessage('CUSTOM_STRUCTURELINKS_JS_ADD_FIRST'),
			'addRow' => Loc::getMessage('CUSTOM_STRUCTURELINKS_JS_ADD_ROW'),
			'remove' => Loc::getMessage('CUSTOM_STRUCTURELINKS_JS_REMOVE'),
			'titlePlaceholder' => Loc::getMessage('CUSTOM_STRUCTURELINKS_JS_TITLE_PH'),
			'urlPlaceholder' => Loc::getMessage('CUSTOM_STRUCTURELINKS_JS_URL_PH'),
			'save' => Loc::getMessage('CUSTOM_STRUCTURELINKS_JS_SAVE'),
			'cancel' => Loc::getMessage('CUSTOM_STRUCTURELINKS_JS_CANCEL'),
			'error' => Loc::getMessage('CUSTOM_STRUCTURELINKS_JS_ERROR'),
		],
	],
];

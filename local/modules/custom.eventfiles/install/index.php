<?php

use Bitrix\Main\EventManager;
use Bitrix\Main\ModuleManager;

class custom_eventfiles extends CModule
{
	public $MODULE_ID = 'custom.eventfiles';
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME = 'Кликабельные файлы в истории CRM';
	public $MODULE_DESCRIPTION = 'Имя файла в истории CRM становится ссылкой на папку файла на Общем диске.';

	public function __construct()
	{
		$arModuleVersion = [];
		include __DIR__ . '/version.php';
		$this->MODULE_VERSION = $arModuleVersion['VERSION'];
		$this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
	}

	public function DoInstall()
	{
		ModuleManager::registerModule($this->MODULE_ID);
		CopyDirFiles(__DIR__ . '/js', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js', true, true);
		EventManager::getInstance()
			->registerEventHandler('main', 'OnProlog', $this->MODULE_ID, '\Custom\EventFiles\Handler', 'onProlog')
		;
	}

	public function DoUninstall()
	{
		EventManager::getInstance()
			->unRegisterEventHandler('main', 'OnProlog', $this->MODULE_ID, '\Custom\EventFiles\Handler', 'onProlog')
		;
		DeleteDirFilesEx('/bitrix/js/custom/eventfiles');
		ModuleManager::unRegisterModule($this->MODULE_ID);
	}
}

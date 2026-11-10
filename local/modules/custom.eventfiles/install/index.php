<?php

use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\ModuleManager;

/**
 * Installer of the custom.eventfiles module.
 */
class custom_eventfiles extends CModule
{
	private const EXTENSION_CONFIG = '/local/js/custom/eventfiles/config.php';

	private const EVENT_MODULE = 'main';

	private const EVENT_NAME = 'OnProlog';

	private const HANDLER_METHOD = 'onProlog';

	private const HANDLER_CLASS = '\Custom\EventFiles\PrologHandler';

	private const LEGACY_HANDLER_CLASS = '\Custom\EventFiles\Handler';

	public $MODULE_ID = 'custom.eventfiles';
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME = 'Кликабельные файлы в истории CRM';
	public $MODULE_DESCRIPTION = 'Имя файла в истории CRM становится ссылкой на файл на Общем диске.';

	public function __construct()
	{
		$arModuleVersion = [];
		include __DIR__ . '/version.php';
		$this->MODULE_VERSION = $arModuleVersion['VERSION'];
		$this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
	}

	/**
	 * Registers the module and the OnProlog handler.
	 *
	 * @return bool
	 */
	public function DoInstall()
	{
		global $APPLICATION;

		if (!is_file(Application::getDocumentRoot() . self::EXTENSION_CONFIG))
		{
			$APPLICATION->ThrowException('Не найдено JS-расширение: ' . self::EXTENSION_CONFIG);

			return false;
		}

		ModuleManager::registerModule($this->MODULE_ID);
		$this->unregisterHandlers();
		EventManager::getInstance()
			->registerEventHandler(
				self::EVENT_MODULE,
				self::EVENT_NAME,
				$this->MODULE_ID,
				self::HANDLER_CLASS,
				self::HANDLER_METHOD
			)
		;

		return true;
	}

	/**
	 * Unregisters the handler and the module.
	 *
	 * @return bool
	 */
	public function DoUninstall()
	{
		$this->unregisterHandlers();
		ModuleManager::unRegisterModule($this->MODULE_ID);

		return true;
	}

	private function unregisterHandlers(): void
	{
		$eventManager = EventManager::getInstance();

		foreach ([self::HANDLER_CLASS, self::LEGACY_HANDLER_CLASS] as $handlerClass)
		{
			$eventManager->unRegisterEventHandler(
				self::EVENT_MODULE,
				self::EVENT_NAME,
				$this->MODULE_ID,
				$handlerClass,
				self::HANDLER_METHOD
			);
		}
	}
}

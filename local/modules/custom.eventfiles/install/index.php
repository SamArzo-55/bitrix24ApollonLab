<?php

use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\ModuleManager;

/**
 * Installer of the custom.eventfiles module.
 */
class custom_eventfiles extends CModule
{
	/** JS extensions shipped with the module; copied to /local/js on install. */
	private const EXTENSIONS_SOURCE_DIR = __DIR__ . '/js';

	private const EXTENSIONS_TARGET_DIR = '/local/js';

	private const EXTENSION_VENDOR_DIR = '/local/js/custom';

	private const EXTENSION_DIR = '/local/js/custom/eventfiles';

	private const EVENT_MODULE = 'main';

	private const EVENT_NAME = 'OnProlog';

	private const HANDLER_METHOD = 'onProlog';

	private const HANDLER_CLASS = '\Custom\EventFiles\PrologHandler';

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
	 * Copies the JS extension to /local/js, registers the module and the OnProlog handler.
	 *
	 * @return bool
	 */
	public function DoInstall()
	{
		global $APPLICATION;

		if (!$this->installExtensions())
		{
			$APPLICATION->ThrowException('Не удалось скопировать JS-расширение в ' . self::EXTENSION_DIR);

			return false;
		}

		ModuleManager::registerModule($this->MODULE_ID);
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
	 * Unregisters the handler and the module, removes the JS extension from /local/js.
	 *
	 * @return bool
	 */
	public function DoUninstall()
	{
		EventManager::getInstance()->unRegisterEventHandler(
			self::EVENT_MODULE,
			self::EVENT_NAME,
			$this->MODULE_ID,
			self::HANDLER_CLASS,
			self::HANDLER_METHOD
		);
		ModuleManager::unRegisterModule($this->MODULE_ID);
		$this->uninstallExtensions();

		return true;
	}


	private function installExtensions(): bool
	{
		$sourceDirectory = self::EXTENSIONS_SOURCE_DIR;
		$targetDirectory = Application::getDocumentRoot() . self::EXTENSIONS_TARGET_DIR;

		if (!is_dir($sourceDirectory))
		{
			return false;
		}

		CheckDirPath($targetDirectory . '/');

		return CopyDirFiles(
			$sourceDirectory,
			$targetDirectory,
			true,
			true
		);
	}


	private function uninstallExtensions(): void
	{
		$documentRoot = Application::getDocumentRoot();

		Directory::deleteDirectory($documentRoot . self::EXTENSION_DIR);

		// Do not leave an empty vendor directory behind.
		$vendorDirectory = new Directory($documentRoot . self::EXTENSION_VENDOR_DIR);
		if ($vendorDirectory->isExists() && $vendorDirectory->getChildren() === [])
		{
			$vendorDirectory->delete();
		}
	}

}

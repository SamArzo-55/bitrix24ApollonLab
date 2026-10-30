<?php
use Bitrix\Main\EventManager;
use Bitrix\Main\ModuleManager;

class custom_structurelinks extends CModule
{
    public $MODULE_ID = 'custom.structurelinks';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME = 'Ссылки на регламенты в структуре компании';
    public $MODULE_DESCRIPTION = 'Блок со ссылками на регламенты на странице /hr/structure/. Администратор редактирует ссылки прямо в блоке.';
    public $PARTNER_NAME = 'Custom';
    public $PARTNER_URI = '';

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
        EventManager::getInstance()->registerEventHandler('main', 'OnProlog', $this->MODULE_ID, '\Custom\StructureLinks\Handler', 'onProlog');
    }

    public function DoUninstall()
    {
        EventManager::getInstance()->unRegisterEventHandler('main', 'OnProlog', $this->MODULE_ID, '\Custom\StructureLinks\Handler', 'onProlog');
        DeleteDirFilesEx('/bitrix/js/custom/structurelinks');
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
}

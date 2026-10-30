<?php
use Bitrix\Main\EventManager;
use Bitrix\Main\ModuleManager;

class custom_toolbarlinks extends CModule
{
    public $MODULE_ID = 'custom.toolbarlinks';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME = 'Кнопки в Лидах и Сделках';
    public $MODULE_DESCRIPTION = 'Кнопки со ссылкой в тулбаре Лидов и Сделок. Ссылку меняет администратор прямо на странице.';
    public $PARTNER_NAME = 'custom';
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
        EventManager::getInstance()->registerEventHandler('main', 'OnProlog', $this->MODULE_ID, '\Custom\Toolbarlinks\Handler', 'onProlog');
    }

    public function DoUninstall()
    {
        EventManager::getInstance()->unRegisterEventHandler('main', 'OnProlog', $this->MODULE_ID, '\Custom\Toolbarlinks\Handler', 'onProlog');
        DeleteDirFilesEx('/bitrix/js/custom/toolbarlinks');
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
}

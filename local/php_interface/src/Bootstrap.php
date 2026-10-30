<?php
declare(strict_types=1);

namespace Custom\App;

use Bitrix\Main\EventManager;
use Custom\App\Handler\DeptDescFixHandler;

final class Bootstrap
{
	public static function init(): void
	{
		$eventManager = EventManager::getInstance();
		$eventManager->addEventHandler('main', 'OnEpilog', [DeptDescFixHandler::class, 'onEpilog']);
	}
}

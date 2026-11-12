<?php
\Bitrix\Main\EventManager::getInstance()->addEventHandler(
	'main',
	'OnProlog',
	static function (): void {
		$path = (new \Bitrix\Main\Web\Uri(
			\Bitrix\Main\Context::getCurrent()->getRequest()->getRequestUri()
		))->getPath();

		$path = rtrim($path, '/');

		$variables = [];

		$page = \CComponentEngine::parseComponentPath(
			'/crm/',
			[
				'deal_details' => 'deal/details/#deal_id#',
			],
			$variables,
			$path
		);

		if ($page === 'deal_details')
		{
			\Bitrix\Main\UI\Extension::load('custom.eventfiles');
		}
	}
);
<?php

namespace Custom\EventFiles;

use Bitrix\Main\Context;
use Bitrix\Main\UI\Extension;
use Bitrix\Main\Web\Uri;

/**
 * OnProlog handler: loads the JS extension only on the CRM deal details page.
 */
class PrologHandler
{
	private const EXTENSION_ID = 'custom.eventfiles';

	private const CRM_FOLDER = '/crm/';

	private const DEAL_DETAILS_PAGE = 'deal_details';

	private const URL_TEMPLATES = [
		self::DEAL_DETAILS_PAGE => 'deal/details/#deal_id#',
	];

	public static function onProlog(): void
	{
		if (self::isDealDetailsPage())
		{
			Extension::load(self::EXTENSION_ID);
		}
	}

	private static function isDealDetailsPage(): bool
	{
		$requestUri = Context::getCurrent()
			->getRequest()
			->getRequestUri();

		$path = (new Uri($requestUri))->getPath();

		$path = rtrim($path, '/');

		$variables = [];

		$page = \CComponentEngine::parseComponentPath(
			self::CRM_FOLDER,
			self::URL_TEMPLATES,
			$variables,
			$path
		);

		return $page === self::DEAL_DETAILS_PAGE;
	}


}

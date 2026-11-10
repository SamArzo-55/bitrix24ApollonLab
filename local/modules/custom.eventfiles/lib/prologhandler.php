<?php

namespace Custom\EventFiles;

use Bitrix\Main\Context;
use Bitrix\Main\UI\Extension;
use Bitrix\Main\Web\Uri;

/**
 * OnProlog handler: loads the JS extension on CRM entity detail pages.
 */
class PrologHandler
{
	private const EXTENSION_ID = 'custom.eventfiles';

	private const ENTITY_DETAILS_PATH_PATTERN = '#^/crm/(deal|contact|company|lead)/details/\d+/#';

	public static function onProlog(): void
	{
		if (self::isEntityDetailsPage())
		{
			Extension::load(self::EXTENSION_ID);
		}
	}

	private static function isEntityDetailsPage(): bool
	{
		$requestUri = Context::getCurrent()->getRequest()->getRequestUri();
		$path = (new Uri($requestUri))->getPath();

		return preg_match(self::ENTITY_DETAILS_PATH_PATTERN, $path) === 1;
	}
}

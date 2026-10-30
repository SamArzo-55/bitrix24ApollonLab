<?php
declare(strict_types=1);

namespace Custom\App\Handler;

use Bitrix\Main\UI\Extension;

final class DeptDescFixHandler
{
	public static function onEpilog(): void
	{
		if (defined('ADMIN_SECTION') && ADMIN_SECTION === true)
		{
			return;
		}

		Extension::load('custom.deptdescfix');
	}
}

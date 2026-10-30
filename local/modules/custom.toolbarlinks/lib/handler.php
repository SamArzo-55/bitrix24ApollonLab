<?php
namespace Custom\Toolbarlinks;

use Bitrix\Main\Context;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Extension;
use Bitrix\UI\Buttons\Button;
use Bitrix\UI\Buttons\Color;
use Bitrix\UI\Buttons\Icon;
use Bitrix\UI\Toolbar\ButtonLocation;
use Bitrix\UI\Toolbar\Facade\Toolbar;

Loc::loadMessages(__FILE__);

class Handler
{
    public static function onProlog(): void
    {
        $request = Context::getCurrent()->getRequest();
        if (defined('ADMIN_SECTION') || $request->isAjaxRequest()) {
            return;
        }

        $code = LinkStorage::resolveCode($request->getRequestedPageDirectory() . '/');
        if ($code === null) {
            return;
        }

        $isAdmin = CurrentUser::get()->isAdmin();
        $url = LinkStorage::getUrl($code);
        if ($url === '' && !$isAdmin) {
            return;
        }

		$button = new Button([
			'text'       => LinkStorage::getTitle($code),
			'link'       => $url !== '' ? $url : '#',
			'color'      => Color::SUCCESS,
			'dataset' => [
				'custom-toolbarlinks-link' => 'Y',
				'custom-toolbarlinks-code' => $code,
			],
		]);
		$button->addAttribute('target', '_blank');

		Toolbar::addButton($button, ButtonLocation::RIGHT);

        if ($isAdmin) {
			$button = new Button([
				'icon'  => Icon::TASK,
				'color' => Color::LIGHT_BORDER,
				'dataset' => [
					'custom-toolbarlinks-edit' => $code,
				],
			]);

			$button->addAttribute(
				'title',
				(string)Loc::getMessage('CUSTOM_TOOLBARLINKS_EDIT_HINT')
			);

			Toolbar::addButton($button, ButtonLocation::RIGHT);

            Extension::load('custom.toolbarlinks');
        }
    }
}

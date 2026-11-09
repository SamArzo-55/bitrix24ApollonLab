<?php
namespace Custom\Toolbarlinks\Service;

use Bitrix\Main\Context;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Extension;
use Bitrix\UI\Buttons\Button;
use Bitrix\UI\Buttons\Color;
use Bitrix\UI\Buttons\Icon;
use Bitrix\UI\Toolbar\ButtonLocation;
use Bitrix\UI\Toolbar\Facade\Toolbar;

class ToolbarBuilder
{
    public static function build(): void
    {
        $request = Context::getCurrent()->getRequest();
        if (defined('ADMIN_SECTION') || $request->isAjaxRequest()) {
            return;
        }

        $dir = $request->getRequestedPageDirectory() . '/';
        $code = RouteService::resolveCode($dir);
        if ($code === null) {
            $code = RouteService::resolveCode($request->getRequestUri());
        }

        if ($code === null) {
            return;
        }

        $isAdmin = CurrentUser::get()->isAdmin();
        $url = LinkService::getUrl($code);
        if ($url === '' && !$isAdmin) {
            return;
        }

        $button = new Button([
            'text' => LinkService::getTitle($code),
            'link' => $url !== '' ? $url : '#',
            'color' => Color::SUCCESS,
            'dataset' => [
                'custom-toolbarlinks-link' => 'Y',
                'custom-toolbarlinks-code' => $code,
            ],
        ]);
        $button->addAttribute('target', '_blank');

        Toolbar::addButton($button, ButtonLocation::RIGHT);

        if ($isAdmin) {
            $editButton = new Button([
                'icon' => Icon::TASK,
                'color' => Color::LIGHT_BORDER,
                'dataset' => [
                    'custom-toolbarlinks-edit' => $code,
                ],
            ]);

            $editButton->addAttribute(
                'title',
                'Изменить ссылку'
            );

            Toolbar::addButton($editButton, ButtonLocation::RIGHT);

            Extension::load('custom.toolbarlinks');
        }
    }
}

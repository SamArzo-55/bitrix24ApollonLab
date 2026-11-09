<?php
namespace Custom\Toolbarlinks\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Custom\Toolbarlinks\Service\LinkService;

Loc::loadMessages(__FILE__);

class Link extends Controller
{
    public function saveAction(string $code, string $url = ''): ?array
    {
        if (!CurrentUser::get()->isAdmin()) {
            $this->addError(new Error((string)Loc::getMessage('CUSTOM_TOOLBARLINKS_ERR_ACCESS'), 'ACCESS_DENIED'));
            return null;
        }

        $url = trim($url);
        if (!LinkService::isValidCode($code) || !LinkService::isSafeUrl($url)) {
            $this->addError(new Error((string)Loc::getMessage('CUSTOM_TOOLBARLINKS_ERR_URL'), 'INVALID_URL'));
            return null;
        }

        LinkService::setUrl($code, $url);

        return ['url' => $url];
    }
}

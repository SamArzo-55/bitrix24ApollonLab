<?php
namespace Custom\Toolbarlinks\Service;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Custom\Toolbarlinks\Config\Constants;

class LinkService
{
    public static function isValidCode(string $code): bool
    {
        return $code === Constants::CODE_LEAD || (bool)preg_match('/^deal_\d+$/', $code);
    }

    public static function isSafeUrl(string $url): bool
    {
        return $url === '' || (bool)preg_match('#^(https?://|/(?!/))#i', $url);
    }

    public static function getUrl(string $code): string
    {
        return trim((string)Option::get(Constants::MODULE_ID, $code . Constants::OPTION_URL_SUFFIX, ''));
    }

    public static function setUrl(string $code, string $url): void
    {
        Option::set(Constants::MODULE_ID, $code . Constants::OPTION_URL_SUFFIX, $url);
    }

    public static function getTitle(string $code): string
    {
		return $code === Constants::CODE_LEAD ? 'Инструкция по работе с лидами' : 'Инструкция по работе со сделками';
    }
}

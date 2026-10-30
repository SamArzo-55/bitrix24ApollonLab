<?php
namespace Custom\Toolbarlinks;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class LinkStorage
{
    public const MODULE_ID = 'custom.toolbarlinks';

    public static function resolveCode(string $dir): ?string
    {
        if (strpos($dir, '/crm/lead/') === 0) {
            return 'lead';
        }

        if (preg_match('#^/crm/deal/(?:[a-z]+/)?category/(\d+)/#', $dir, $m)) {
            return 'deal_' . (int)$m[1];
        }

        return null;
    }

    public static function isValidCode(string $code): bool
    {
        return $code === 'lead' || (bool)preg_match('/^deal_\d+$/', $code);
    }

    public static function isSafeUrl(string $url): bool
    {
        return $url === '' || (bool)preg_match('#^(https?://|/(?!/))#i', $url);
    }

    public static function getUrl(string $code): string
    {
        return trim((string)Option::get(self::MODULE_ID, $code . '_url', ''));
    }

    public static function setUrl(string $code, string $url): void
    {
        Option::set(self::MODULE_ID, $code . '_url', $url);
    }

    public static function getTitle(string $code): string
    {
        $key = $code === 'lead' ? 'CUSTOM_TOOLBARLINKS_TITLE_LEAD' : 'CUSTOM_TOOLBARLINKS_TITLE_DEAL';

        return (string)Loc::getMessage($key);
    }
}

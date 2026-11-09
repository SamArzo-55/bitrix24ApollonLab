<?php
namespace Custom\Toolbarlinks;

use Custom\Toolbarlinks\Service\LinkService;
use Custom\Toolbarlinks\Service\RouteService;

class LinkStorage
{
    public static function resolveCode(string $dir): ?string
    {
        return RouteService::resolveCode($dir);
    }

    public static function isValidCode(string $code): bool
    {
        return LinkService::isValidCode($code);
    }

    public static function isSafeUrl(string $url): bool
    {
        return LinkService::isSafeUrl($url);
    }

    public static function getUrl(string $code): string
    {
        return LinkService::getUrl($code);
    }

    public static function setUrl(string $code, string $url): void
    {
        LinkService::setUrl($code, $url);
    }

    public static function getTitle(string $code): string
    {
        return LinkService::getTitle($code);
    }
}

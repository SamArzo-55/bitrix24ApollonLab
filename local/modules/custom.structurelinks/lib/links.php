<?php
namespace Custom\StructureLinks;

use Bitrix\Main\Config\Option;

class Links
{
    public const MODULE_ID = 'custom.structurelinks';

    public static function getAll(): array
    {
        $raw = Option::get(self::MODULE_ID, 'links', '[]');
        $list = json_decode($raw, true);

        return is_array($list) ? $list : [];
    }

    public static function save(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $title = trim((string)($item['title'] ?? ''));
            $url = trim((string)($item['url'] ?? ''));
            if ($title === '' || $url === '' || !self::isSafeUrl($url)) {
                continue;
            }
            $result[] = ['title' => $title, 'url' => $url];
        }

        Option::set(self::MODULE_ID, 'links', json_encode($result, JSON_UNESCAPED_UNICODE));

        return $result;
    }

    public static function isSafeUrl(string $url): bool
    {
        return (bool)preg_match('~^(https?://|/)(?!/)~i', $url);
    }
}

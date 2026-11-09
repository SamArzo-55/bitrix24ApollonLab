<?php
namespace Custom\Toolbarlinks\Service;

use CComponentEngine;

class RouteService
{
    public static function resolveCode(string $requestUri): ?string
    {
        $templates = [
            'lead' => 'crm/lead/',
            'deal_cat' => 'crm/deal/category/#CATEGORY_ID#/',
            'deal_sub_cat' => 'crm/deal/#SUB_DIR#/category/#CATEGORY_ID#/',
            'deal_default' => 'crm/deal/',
        ];

        $variables = [];
        $engine = new CComponentEngine();
        $code = $engine->parseComponentPath('/', $templates, $variables, $requestUri);

        if ($code === 'lead') {
            return 'lead';
        }

        if ($code === 'deal_cat' || $code === 'deal_sub_cat') {
            $categoryId = (int)($variables['CATEGORY_ID'] ?? 0);
            return 'deal_' . $categoryId;
        }

        if ($code === 'deal_default') {
            return 'deal_0';
        }

        if (strpos($requestUri, '/crm/lead/') === 0) {
            return 'lead';
        }

        if (preg_match('#^/crm/deal/(?:[a-z]+/)?category/(\d+)/#', $requestUri, $m)) {
            return 'deal_' . (int)$m[1];
        }

        return null;
    }
}

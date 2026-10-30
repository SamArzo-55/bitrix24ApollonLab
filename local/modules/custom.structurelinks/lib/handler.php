<?php
namespace Custom\StructureLinks;

use Bitrix\Main\Context;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\UI\Extension;

class Handler
{
    public static function onProlog(): void
    {
        if (!CurrentUser::get()->isAdmin()) {
            return;
        }

        $dir = Context::getCurrent()->getRequest()->getRequestedPageDirectory();
        if (strpos($dir . '/', '/hr/structure/') !== 0) {
            return;
        }

        Extension::load('custom.structurelinks');
    }
}

<?php
namespace Custom\EventFiles;

use Bitrix\Main\Context;
use Bitrix\Main\UI\Extension;

class Handler
{
    public static function onProlog(): void
    {
        $dir = Context::getCurrent()->getRequest()->getRequestedPageDirectory();
        if (strpos($dir . '/', '/crm/') === 0) {
            Extension::load('custom.eventfiles');
        }
    }
}

<?php
namespace Custom\Toolbarlinks;

use Custom\Toolbarlinks\Service\ToolbarBuilder;

class Handler
{
    public static function onProlog(): void
    {
        ToolbarBuilder::build();
    }
}

<?php
namespace Custom\StructureLinks\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Custom\StructureLinks\Links as LinksStorage;

Loc::loadMessages(__FILE__);

class Links extends Controller
{
    public function saveAction(array $links = []): ?array
    {
        if (!CurrentUser::get()->isAdmin()) {
            $this->addError(new Error('Недостаточно прав: ссылки может менять только администратор.', 'ACCESS_DENIED'));
            return null;
        }

        return ['links' => LinksStorage::save($links)];
    }
}

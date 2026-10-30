<?php
namespace Custom\EventFiles\Controller;

use Bitrix\Disk\File as DiskFile;
use Bitrix\Disk\Folder;
use Bitrix\Disk\Internals\FileTable;
use Bitrix\Main\Engine\Controller;

class File extends Controller
{
    public function getLinksAction(array $names = []): array
    {
        $result = [];

		$names = array_slice(array_unique($names), 0, 100);

		$rows = FileTable::getList([
			'select' => ['ID', 'NAME'],
			'filter' => ['=NAME' => $names, '=DELETED_TYPE' => 0],
			'limit' => 1,
		])->fetchAll();
	
        foreach ($rows as $row) {
            $file = $row ? DiskFile::loadById($row['ID']) : null;
            if (!$file || !$file->canRead($file->getStorage()->getCurrentUserSecurityContext())) {
                continue;
            }

            $parts = [];
            for ($f = Folder::loadById($file->getParentId()); $f && $f->getParentId(); $f = Folder::loadById($f->getParentId())) {
                array_unshift($parts, rawurlencode($f->getName()));
            }
            $result[$row['NAME']] = '/docs/shared/path/' . implode('/', $parts) . '/';
        }

        return $result;
    }
}

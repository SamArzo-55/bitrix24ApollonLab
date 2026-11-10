<?php

namespace Custom\EventFiles\Service;

use Bitrix\Disk\Internals\FileTable;
use Bitrix\Disk\Internals\FolderTable;
use Bitrix\Disk\Internals\StorageTable;
use Bitrix\Main\Context;
use Bitrix\Main\Data\Cache;

/**
 * Finds Disk files by name and builds Shared Drive file URLs. Access rights are checked by Disk itself.
 */
class FileUrlProvider
{
	private const SHARED_STORAGE_ENTITY_PREFIX = 'shared_files_';

	private const SHARED_FILE_URL = '/docs/shared/file/';

	public const MAX_FILE_NAME_LENGTH = 255;

	private const MAX_FOLDER_DEPTH = 50;

	private const DELETED_TYPE_NONE = 0;

	private const CACHE_DIR = '/custom/eventfiles/file_urls';
	private const CACHE_TTL = 300;

	private bool $sharedStorageResolved = false;
	private ?int $sharedStorageId = null;

	/** @var array<int, array{name: string, parentId: int}> */
	private array $folders = [];

	/**
	 * Returns file URLs; for duplicate names the newest file wins.
	 *
	 * @param string[] $fileNames Unique validated file names.
	 *
	 * @return array<string, string> File name => file URL; names missing on Disk are omitted.
	 *
	 * @throws \InvalidArgumentException If a name is not a non-empty string of allowed length.
	 */
	public function getFileUrls(array $fileNames): array
	{
		if ($fileNames === [])
		{
			return [];
		}

		$fileNames = $this->validateFileNames($fileNames);

		$found = $this->findFiles($fileNames);
		if ($found === [])
		{
			return [];
		}

		$cacheId = md5(serialize($found));
		$cache = Cache::createInstance();
		if ($cache->initCache(self::CACHE_TTL, $cacheId, self::CACHE_DIR))
		{
			$cached = $cache->getVars();
			if (is_array($cached) && !empty($cached['urls']) && is_array($cached['urls']))
			{
				return $cached['urls'];
			}
		}

		$urls = $this->buildUrls($found);

		$cache->forceRewriting(true);
		if ($cache->startDataCache(self::CACHE_TTL, $cacheId, self::CACHE_DIR))
		{
			$cache->endDataCache(['urls' => $urls]);
		}

		return $urls;
	}

	/**
	 * @param array $fileNames
	 *
	 * @return string[] Unique names.
	 */
	private function validateFileNames(array $fileNames): array
	{
		foreach ($fileNames as $fileName)
		{
			if (!is_string($fileName) || trim($fileName) === '' || mb_strlen($fileName) > self::MAX_FILE_NAME_LENGTH)
			{
				throw new \InvalidArgumentException('File names must be non-empty strings of valid length.');
			}
		}

		return array_values(array_unique($fileNames));
	}

	/**
	 * Looks up non-deleted files: Shared Drive first, then all storages for the missing names.
	 *
	 * @param string[] $fileNames
	 *
	 * @return array<string, array{diskName: string, parentId: int}>
	 */
	private function findFiles(array $fileNames): array
	{
		$found = [];

		$storageId = $this->getSharedStorageId();
		if ($storageId !== null)
		{
			$found = $this->queryFiles($fileNames, $storageId);
		}

		$missingNames = array_values(array_filter(
			$fileNames,
			static fn (string $fileName): bool => !isset($found[$fileName])
		));
		if ($missingNames !== [])
		{
			$found += $this->queryFiles($missingNames, null);
		}

		return $found;
	}

	/**
	 * @param string[] $fileNames
	 * @param int|null $storageId Null for any storage.
	 *
	 * @return array<string, array{diskName: string, parentId: int}> Requested name => Disk name and parent folder.
	 */
	private function queryFiles(array $fileNames, ?int $storageId): array
	{
		$filter = [
			'=NAME' => $fileNames,
			'=DELETED_TYPE' => self::DELETED_TYPE_NONE,
		];
		if ($storageId !== null)
		{
			$filter['=STORAGE_ID'] = $storageId;
		}

		$rows = FileTable::getList([
			'select' => ['ID', 'NAME', 'PARENT_ID'],
			'filter' => $filter,
			'order' => ['ID' => 'ASC'],
		])->fetchAll();

		$requestedNames = [];
		foreach ($fileNames as $fileName)
		{
			$requestedNames[mb_strtolower($fileName)] = $fileName;
		}

		$candidates = [];
		foreach ($rows as $row)
		{
			$diskName = (string)$row['NAME'];
			$requestedName = $requestedNames[mb_strtolower($diskName)] ?? $diskName;
			$candidates[$requestedName][(int)$row['ID']] = [
				'diskName' => $diskName,
				'parentId' => (int)$row['PARENT_ID'],
			];
		}

		$found = [];
		foreach ($candidates as $requestedName => $files)
		{
			$found[$requestedName] = $files[max(array_keys($files))];
		}

		return $found;
	}

	private function getSharedStorageId(): ?int
	{
		if ($this->sharedStorageResolved)
		{
			return $this->sharedStorageId;
		}

		$this->sharedStorageResolved = true;

		$siteId = (string)Context::getCurrent()->getSite();
		if ($siteId === '')
		{
			return $this->sharedStorageId = null;
		}

		$row = StorageTable::getList([
			'select' => ['ID'],
			'filter' => ['=ENTITY_ID' => self::SHARED_STORAGE_ENTITY_PREFIX . $siteId],
			'limit' => 1,
		])->fetch();

		return $this->sharedStorageId = $row ? (int)$row['ID'] : null;
	}

	/**
	 * @param array<string, array{diskName: string, parentId: int}> $found
	 *
	 * @return array<string, string>
	 */
	private function buildUrls(array $found): array
	{
		$folders = $this->loadFolders(array_values(array_unique(array_column($found, 'parentId'))));

		$urls = [];
		foreach ($found as $requestedName => $file)
		{
			$segments = $this->buildPathSegments($file['parentId'], $folders);
			$segments[] = $file['diskName'];
			$urls[$requestedName] = $this->buildUrl($segments);
		}

		return $urls;
	}

	/**
	 * Loads folders with all ancestors, one query per nesting level.
	 *
	 * @param int[] $folderIds
	 *
	 * @return array<int, array{name: string, parentId: int}>
	 */
	private function loadFolders(array $folderIds): array
	{
		$pendingIds = array_values(array_diff($folderIds, array_keys($this->folders)));

		for ($depth = 0; $depth < self::MAX_FOLDER_DEPTH && $pendingIds !== []; $depth++)
		{
			$rows = FolderTable::getList([
				'select' => ['ID', 'NAME', 'PARENT_ID'],
				'filter' => ['=ID' => $pendingIds],
			])->fetchAll();

			$parentIds = [];
			foreach ($rows as $row)
			{
				$this->folders[(int)$row['ID']] = [
					'name' => (string)$row['NAME'],
					'parentId' => (int)$row['PARENT_ID'],
				];
				$parentIds[(int)$row['PARENT_ID']] = (int)$row['PARENT_ID'];
			}

			unset($parentIds[0]);
			$pendingIds = array_values(array_diff_key($parentIds, $this->folders));
		}

		return $this->folders;
	}

	/**
	 * Returns folder names from top to the file's folder, excluding the storage root.
	 *
	 * @param array<int, array{name: string, parentId: int}> $folders
	 *
	 * @return string[]
	 */
	private function buildPathSegments(int $folderId, array $folders): array
	{
		$segments = [];
		$currentId = $folderId;

		for ($depth = 0; $depth < self::MAX_FOLDER_DEPTH; $depth++)
		{
			if (!isset($folders[$currentId]) || $folders[$currentId]['parentId'] <= 0)
			{
				break;
			}

			array_unshift($segments, $folders[$currentId]['name']);
			$currentId = $folders[$currentId]['parentId'];
		}

		return $segments;
	}

	/**
	 * @param string[] $segments Folder names followed by the file name.
	 */
	private function buildUrl(array $segments): string
	{
		return self::SHARED_FILE_URL . implode('/', array_map('rawurlencode', $segments));
	}
}

<?php

namespace Custom\EventFiles\Service;

use Bitrix\Disk\Driver;
use Bitrix\Disk\Internals\FileTable;
use Bitrix\Disk\Internals\FolderTable;
use Bitrix\Disk\Storage;
use Bitrix\Main\Context;
use Bitrix\Main\Data\Cache;
use Bitrix\Sale\Cashbox\FullPrepaymentCheck;

/**
 * Finds Shared Drive files by name and builds their URLs.
 * Only files the current user is allowed to read are returned.
 */
class FileUrlProvider
{
	private const SHARED_STORAGE_ENTITY_PREFIX = 'shared_files_';

	private const SHARED_FILE_URL = '/docs/shared/file/';

	private const MAX_FOLDER_DEPTH = 50;

	private const DELETED_TYPE_NONE = 0;

	private const CACHE_DIR = '/custom/eventfiles/file_urls';
	private const CACHE_TTL = 300;

	/** @var array<int, array{name: string, parentId: int}> */
	private array $folders = [];

	/**
	 * Returns URLs of Shared Drive files readable by the current user; for duplicate names the newest readable file wins.
	 *
	 * @param string[] $fileNames Unique validated file names.
	 *
	 * @return array<string, string> File name => file URL; missing and unreadable files are omitted.
	 */
	public function getUrlsByNames(array $fileNames): array
	{
		if ($fileNames === [])
		{
			return [];
		}

		$files = $this->findReadableFiles($fileNames);
		if ($files === [])
		{
			return [];
		}

		$cacheId = md5(serialize($files));
		$cache = Cache::createInstance();
		if ($cache->initCache(self::CACHE_TTL, $cacheId, self::CACHE_DIR))
		{
			$cachedVars = $cache->getVars();
			if (!empty($cachedVars['urls']) && is_array($cachedVars['urls']))
			{
				return $cachedVars['urls'];
			}
		}
		
		$urls = $this->buildUrls($files);

		$cache->forceRewriting(true);

		if ($cache->startDataCache(self::CACHE_TTL, $cacheId, self::CACHE_DIR))
		{
			$cache->endDataCache(['urls' => $urls]);
		}

		return $urls;
	}

	/**
	 * Looks up non-deleted Shared Drive files the current user can read.
	 *
	 * @param string[] $fileNames
	 *
	 * @return array<string, array{diskName: string, parentId: int}> Requested name => Disk name and parent folder.
	 */
	private function findReadableFiles(array $fileNames): array
	{
		$sharedStorage = $this->getSharedStorage();
		if ($sharedStorage === null)
		{
			return [];
		}

		$securityContext = $sharedStorage->getCurrentUserSecurityContext();

		$requestedNames = [];
		foreach ($fileNames as $fileName)
		{
			$requestedNames[mb_strtolower($fileName)] = $fileName;
		}

		$result = FileTable::getList([
			'select' => ['ID', 'NAME', 'PARENT_ID'],
			'filter' => [
				'=NAME' => $fileNames,
				'=STORAGE_ID' => $sharedStorage->getId(),
				'=DELETED_TYPE' => self::DELETED_TYPE_NONE,
			],
			'order' => ['ID' => 'DESC'],
		]);

		$files = [];
		while ($row = $result->fetch())
		{
			$diskName = (string)$row['NAME'];
			$requestedName = $requestedNames[mb_strtolower($diskName)] ?? $diskName;

			if (isset($files[$requestedName]) || !$securityContext->canRead((int)$row['ID']))
			{
				continue;
			}

			$files[$requestedName] = [
				'diskName' => $diskName,
				'parentId' => (int)$row['PARENT_ID'],
			];
		}

		return $files;
	}

	private function getSharedStorage(): ?Storage
	{
		$siteId = (string)Context::getCurrent()->getSite();
		if ($siteId === '')
		{
			return null;
		}

		return Driver::getInstance()->getStorageByCommonId(self::SHARED_STORAGE_ENTITY_PREFIX . $siteId);
	}

	/**
	 * @param array<string, array{diskName: string, parentId: int}> $files
	 *
	 * @return array<string, string>
	 */
	private function buildUrls(array $files): array
	{
		$folders = $this->loadFolders(array_values(array_unique(array_column($files, 'parentId'))));

		$urls = [];
		foreach ($files as $requestedName => $file)
		{
			$pathSegments = $this->buildFolderPathSegments($file['parentId'], $folders);
			$pathSegments[] = $file['diskName'];
			$urls[$requestedName] = $this->buildUrl($pathSegments);
		}

		return $urls;
	}


	/**
	 * Loads folders with all ancestors using a single database query.
	 *
	 * @param int[] $folderIds
	 *
	 * @return array<int, array{name: string, parentId: int}>
	 */
	private function loadFolders(array $folderIds): array
	{
		$pendingIds = array_diff($folderIds, array_keys($this->folders));

		if ($pendingIds === [])
		{
			return $this->folders;
		}

		$rows = FolderTable::getList([
			'select' => ['ID', 'NAME', 'PARENT_ID'],
		])->fetchAll();

		$foldersById = [];

		foreach ($rows as $row)
		{
			$folderId = (int)$row['ID'];

			$foldersById[$folderId] = [
				'name' => (string)$row['NAME'],
				'parentId' => (int)$row['PARENT_ID'],
			];
		}

		foreach ($pendingIds as $folderId)
		{
			$currentId = (int)$folderId;

			for ($depth = 0; $depth < self::MAX_FOLDER_DEPTH && $currentId !== 0; $depth++)
			{
				if (isset($this->folders[$currentId]))
				{
					break;
				}

				if (!isset($foldersById[$currentId]))
				{
					break;
				}

				$this->folders[$currentId] = $foldersById[$currentId];

				$currentId = $foldersById[$currentId]['parentId'];
			}
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
	private function buildFolderPathSegments(int $folderId, array $folders): array
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
	 * @param string[] $pathSegments Folder names followed by the file name.
	 */
	private function buildUrl(array $pathSegments): string
	{
		return self::SHARED_FILE_URL . implode('/', array_map('rawurlencode', $pathSegments));
	}
}

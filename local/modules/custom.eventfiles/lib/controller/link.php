<?php

namespace Custom\EventFiles\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use Custom\EventFiles\Service\FileUrlProvider;

/**
 * AJAX controller returning Shared Drive file links.
 */
class Link extends Controller
{
	private const MAX_FILE_NAMES = 100;

	private const MAX_FILE_NAME_LENGTH = FileUrlProvider::MAX_FILE_NAME_LENGTH;

	private const FILE_NAME_FORBIDDEN_CHARS_PATTERN = '/[\\\\\/:*?"<>|\x00-\x1F]/u';

	/**
	 * @param string[] $fileNames
	 *
	 * @return array<string, string>|null File name => file URL.
	 */
	public function getFileUrlsAction(array $fileNames): ?array
	{
		$normalizedNames = $this->normalizeFileNames($fileNames);
		if ($normalizedNames === null)
		{
			return null;
		}

		try
		{
			return (new FileUrlProvider())->getFileUrls($normalizedNames);
		}
		catch (\Throwable $exception)
		{
			$this->addError(new Error($exception->getMessage(), 'FILE_URLS_FAILED'));

			return null;
		}
	}

	/**
	 * Trims, validates and deduplicates names; adds an error if the list is empty or too long.
	 *
	 * @return string[]|null
	 */
	private function normalizeFileNames(array $fileNames): ?array
	{
		if ($fileNames === [])
		{
			$this->addError(new Error('The list of file names must not be empty.', 'EMPTY_FILE_NAMES'));

			return null;
		}

		if (count($fileNames) > self::MAX_FILE_NAMES)
		{
			$this->addError(new Error('Too many file names in one request.', 'TOO_MANY_FILE_NAMES'));

			return null;
		}

		$uniqueNames = [];
		foreach ($fileNames as $fileName)
		{
			$fileName = is_string($fileName) ? trim($fileName) : '';

			if ($this->isValidFileName($fileName))
			{
				$uniqueNames[$fileName] = $fileName;
			}
		}

		return array_values($uniqueNames);
	}

	private function isValidFileName(string $fileName): bool
	{
		return $fileName !== ''
			&& mb_strlen($fileName) <= self::MAX_FILE_NAME_LENGTH
			&& preg_match(self::FILE_NAME_FORBIDDEN_CHARS_PATTERN, $fileName) === 0;
	}
}

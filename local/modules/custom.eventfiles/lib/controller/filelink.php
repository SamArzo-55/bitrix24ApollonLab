<?php

namespace Custom\EventFiles\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use Custom\EventFiles\Service\FileUrlProvider;

/**
 * AJAX controller returning Shared Drive links to files mentioned by name in the CRM history.
 */
class FileLink extends Controller
{
	private const MAX_FILE_NAMES = 100;

	private const MAX_FILE_NAME_LENGTH = 255;

	private const FILE_NAME_FORBIDDEN_CHARS_PATTERN = '/[\\\\\/:*?"<>|\x00-\x1F]/u';

	/**
	 * @param string[] $fileNames
	 *
	 * @return array<string, string>|null File name => file URL; only files readable by the current user.
	 */
	public function getUrlsAction(array $fileNames): ?array
	{
		$validFileNames = $this->prepareFileNames($fileNames);
		if ($validFileNames === null)
		{
			return null;
		}

		return (new FileUrlProvider())->getUrlsByNames($validFileNames);
	}

	/**
	 * Trims, validates and deduplicates names; adds an error if the list is empty or too long.
	 *
	 * @return string[]|null
	 */
	private function prepareFileNames(array $fileNames): ?array
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

		$uniqueFileNames = [];
		foreach ($fileNames as $fileName)
		{
			$fileName = is_string($fileName) ? trim($fileName) : '';

			if ($this->isValidFileName($fileName))
			{
				$uniqueFileNames[$fileName] = $fileName;
			}
		}

		return array_values($uniqueFileNames);
	}

	private function isValidFileName(string $fileName): bool
	{
		return $fileName !== ''
			&& mb_strlen($fileName) <= self::MAX_FILE_NAME_LENGTH
			&& preg_match(self::FILE_NAME_FORBIDDEN_CHARS_PATTERN, $fileName) === 0;
	}
}

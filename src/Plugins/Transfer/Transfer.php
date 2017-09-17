<?php

namespace JustDeploy\Plugins\Transfer;

use Exception;
use InvalidArgumentException;
use JustDeploy\FilesystemInterface;
use League\Flysystem\Util;

class Transfer {

	protected $fromFilesystem;
	protected $fromPath;
	protected $toFilesystem;
	protected $toPath;
	protected $filterPatterns;
	protected $filterInverse;
	protected $recursive;
	protected $overwriteFiles;
	protected $overwriteEmptyDirectories;
	protected $overwriteNonEmptyDirectories;
	protected $replaceDirectories;
	protected $onProgress;
	protected $onBeforeListing;
	protected $onAfterListing;
	protected $onBeforeTransfer;
	protected $onAfterTransfer;


	public function __construct($options)
	{
		$this->fromFilesystem = isset($options['fromFilesystem']) ? $options['fromFilesystem'] : null;
		$this->fromPath = isset($options['fromPath']) ? Util::normalizePath($options['fromPath']) : '';
		$this->toFilesystem = isset($options['toFilesystem']) ? $options['toFilesystem'] : null;
		$this->toPath = isset($options['toPath']) ? Util::normalizePath($options['toPath']) : '';
		$this->filterPatterns = isset($options['filterPatterns']) ? $options['filterPatterns'] : null;
		$this->filterInverse = isset($options['filterInverse']) ? $options['filterInverse'] : false;
		$this->recursive = isset($options['recursive']) ? $options['recursive'] : false;
		$this->overwriteFiles = isset($options['overwriteFiles']) ? $options['overwriteFiles'] : false;
		$this->overwriteEmptyDirectories = isset($options['overwriteEmptyDirectories']) ? $options['overwriteEmptyDirectories'] : false;
		$this->overwriteNonEmptyDirectories = isset($options['overwriteNonEmptyDirectories']) ? $options['overwriteNonEmptyDirectories'] : false;
		$this->replaceDirectories = isset($options['replaceDirectories']) ? $options['replaceDirectories'] : false;
	}

	protected function getFromFilesystem()
	{
		if (!$this->fromFilesystem instanceof FilesystemInterface) {
			throw new InvalidArgumentException("Specify a 'from' filesystem.");
		}

		return $this->fromFilesystem;
	}

	protected function getToFilesystem()
	{
		if (!$toFilesystem = $this->toFilesystem) {
			$toFilesystem = $this->fromFilesystem;
		}

		if (!$toFilesystem instanceof FilesystemInterface) {
			throw new InvalidArgumentException("Specify a 'to' filesystem.");
		}

		return $toFilesystem;
	}

	public function transfer()
	{
		$this->callBeforeListingCallback();

		$files = $this->getFilesToTransfer();
		$bytesTotal = $this->getFileSizesSum($files);
		$filesTotal = count($files);
		
		$this->callAfterListingCallback($files, $filesTotal, $bytesTotal);

		$bytesTransferred = 0;
		$filesTransferred = 0;

		$this->callProgressCallback($filesTransferred, $filesTotal, $bytesTransferred, $bytesTotal);

		foreach ($files as $file) {

			$toFilePath = $this->rebaseFilePath($file['path'], $this->fromPath, $this->toPath);

			$this->callBeforeTransferCallback($file, $toFilePath);
			$this->transferFile($file, $toFilePath);
			$this->callAfterTransferCallback($file, $toFilePath);

			$filesTransferred += 1;
			$bytesTransferred += $this->getFileSize($file, 0);

			$this->callProgressCallback($filesTransferred, $filesTotal, $bytesTransferred, $bytesTotal);
		}
	}

	protected function rebaseFilePath($path, $base, $newBase)
	{
		$path = ltrim($path, '/');
		$base = ltrim($base, '/');
		$newBase = rtrim($newBase, '/');

		$baseLength = strlen($base);
		$relativePath = ltrim(substr($path, $baseLength), '/');

		$cutBase = substr($path, 0, $baseLength);
		if ($cutBase !== $base) {
			throw new InvalidArgumentException("Path '$path' does not contain base path '$base'.");
		}

		return ltrim($newBase .'/'. $relativePath, '/');
	}

	protected function transferFile($file, $toPath)
	{
		if ($file['type'] === 'dir') {

			$this->createDirectory(
				$this->getToFilesystem(),
				$toPath,
				$this->overwriteEmptyDirectories,
				$this->overwriteNonEmptyDirectories
			);

		} else if ($file['type'] === 'file') {

			$this->copyFile(
				$this->getFromFilesystem(),
				$file['path'],
				$this->getToFilesystem(),
				$toPath,
				$this->overwriteFiles
			);

		} else {
			throw new Exception("Can't transfer file type '{$file['type']}'. Path: {$file['path']}");
		}
	}

	protected function callBeforeListingCallback()
	{
		if ($this->onBeforeListing) {
			call_user_func($this->onBeforeListing);
		}
	}

	protected function callAfterListingCallback($files, $filesTotal, $bytesTotal)
	{
		if ($this->onAfterListing) {
			call_user_func($this->onAfterListing, $files, $filesTotal, $bytesTotal);
		}
	}

	protected function callBeforeTransferCallback($file, $transferringToPath)
	{
		if ($this->onBeforeTransfer) {
			call_user_func($this->onBeforeTransfer, $file, $transferringToPath);
		}
	}

	protected function callAfterTransferCallback($file, $transferredToPath)
	{
		if ($this->onAfterTransfer) {
			call_user_func($this->onAfterTransfer, $file, $transferredToPath);
		}
	}

	protected function callProgressCallback($filesTransferred, $filesTotal, $bytesTransferred, $bytesTotal)
	{
		if ($this->onProgress) {
			call_user_func($this->onProgress, $filesTransferred, $filesTotal, $bytesTransferred, $bytesTotal);
		}
	}

	protected function getRootFile()
	{
		if ($this->fromPath === '') {
			return [
				'type' => 'dir',
				'path' => '',
			];
		} else {
			return $this->fromFilesystem->getMetadata($this->fromPath);
		}
	}

	protected function getFilesToTransfer()
	{
		$rootFile = $this->getRootFile();

		if ($rootFile['type'] === 'dir') {

			$directoryContents = $this->fromFilesystem->filterContents(
				$this->filterPatterns,
				$this->fromPath,
				$this->recursive,
				$this->filterInverse
			);

			return array_merge([$rootFile], $directoryContents);

		} else {
			return [$rootFile];
		}
	}

	protected function getFileSizesSum($files)
	{
		$sum = 0;

		foreach ($files as $file) {
			$sum += $this->getFileSize($file, 0);
		}

		return $sum;
	}

	protected function getFileSize($file, $default=null)
	{
		if (isset($file['size'])) {
			return $file['size'];
		} else {
			return $default;
		}
	}

	protected function createDirectory($filesystem, $path, $overwriteEmptyDirectories, $overwriteNonEmptyDirectories)
	{
		$isRoot = ($path === '');
		$exists = $isRoot || $filesystem->has($path);

		if (!$overwriteNonEmptyDirectories && $exists) {

			if ($overwriteEmptyDirectories) {

				$files = $filesystem->listContents($path);
				$empty = !count($files);

				if (!$empty) {
					throw new Exception("Directory '$path' already exists and is not empty.");
				}

			} else {
				throw new Exception("Directory '$path' already exists.");
			}
		}

		if (!$isRoot) {
			$filesystem->createDir($path);
		}
	}

	protected function copyFile($fromFilesystem, $fromPath, $toFilesystem, $toPath, $overwrite)
	{
		if ($fromFilesystem === $toFilesystem) {

			if ($overwrite) {
				$fromFilesystem->delete($toPath);
			}

			$fromFilesystem->copy($fromPath, $toPath);

		} else {

			$stream = $fromFilesystem->readStream($fromPath);

			if ($overwrite) {
				$toFilesystem->putStream($toPath, $stream);
			} else {
				$toFilesystem->writeStream($toPath, $stream);
			}

			if (is_resource($stream)) {
				fclose($stream);
			}
		}
	}

}
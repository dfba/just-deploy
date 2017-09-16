<?php

namespace JustDeploy\Flysystem;

use Exception;
use InvalidArgumentException;
use JustDeploy\FilesystemInterface;
use League\Flysystem\Util;

class Transfer {

	protected $fromFilesystem = null;
	protected $fromPath = '';
	protected $toFilesystem = null;
	protected $toPath = '';
	protected $filterPatterns = null;
	protected $filterInverse = false;
	protected $recursive = false;
	protected $overwriteFiles = false;
	protected $overwriteEmptyDirectories = false;
	protected $overwriteNonEmptyDirectories = false;
	protected $replaceDirectories = false;
	protected $onProgress = null;
	protected $onBeforeListing = null;
	protected $onAfterListing = null;
	protected $onBeforeTransfer = null;
	protected $onAfterTransfer = null;

	public function from($filesystem, $path = '')
	{
		if (is_string($filesystem)) {
			return $this->from($this->fromFilesystem, $filesystem);
		}

		if (!is_null($filesystem) && !$filesystem instanceof FilesystemInterface) {
			throw new InvalidArgumentException("First argument must be either a filesystem or a path.");
		}

		$this->fromFilesystem = $filesystem;
		$this->fromPath = Util::normalizePath($path);

		return $this;
	}

	public function to($filesystem, $path = '')
	{
		if (is_string($filesystem)) {
			return $this->to(null, $filesystem);
		}

		if (!is_null($filesystem) && !$filesystem instanceof FilesystemInterface) {
			throw new InvalidArgumentException("First argument must be either a filesystem or a path.");
		}

		$this->toFilesystem = $filesystem;
		$this->toPath = Util::normalizePath($path);
		
		return $this;
	}

	public function filter($patterns, $inverse = false)
	{
		if (!is_string($patterns) && !is_array($patterns)) {
			throw new InvalidArgumentException("Arguments `$patterns` must be a string or an array.");
		}

		$this->filterPatterns = $patterns;
		$this->filterInverse = !!$inverse;
		
		return $this;
	}

	public function recursive($recursive = true)
	{
		$this->recursive = !!$recursive;
		
		return $this;
	}

	public function overwrite($files = true, $emptyDirectories = true, $nonEmptyDirectories = true, $replaceDirectories = false)
	{
		if (func_num_args() <= 1) {
			return $this->overwrite($files, $files, $files, false);
		}

		$this->overwriteFiles = !!$files;
		$this->overwriteEmptyDirectories = !!$emptyDirectories;
		$this->overwriteNonEmptyDirectories = !!$nonEmptyDirectories;
		$this->replaceDirectories = !!$replaceDirectories;
		
		return $this;
	}

	public function onProgress(callable $onProgress = null)
	{
		$this->onProgress = $onProgress;
		
		return $this;
	}

	public function onBeforeListing(callable $onBeforeListing = null)
	{
		$this->onBeforeListing = $onBeforeListing;
		
		return $this;
	}

	public function onAfterListing(callable $onAfterListing = null)
	{
		$this->onAfterListing = $onAfterListing;
		
		return $this;
	}

	public function onBeforeTransfer(callable $onBeforeTransfer = null)
	{
		$this->onBeforeTransfer = $onBeforeTransfer;
		
		return $this;
	}

	public function onAfterTransfer(callable $onAfterTransfer = null)
	{
		$this->onAfterTransfer = $onAfterTransfer;
		
		return $this;
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

	public function start()
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
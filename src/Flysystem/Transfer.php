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

	public function start()
	{
		if (!$toFilesystem = $this->toFilesystem) {
			$toFilesystem = $this->fromFilesystem;
		}

		if (!$this->fromFilesystem instanceof FilesystemInterface) {
			throw new InvalidArgumentException("Specify a 'from' filesystem.");
		}

		if (!$toFilesystem instanceof FilesystemInterface) {
			throw new InvalidArgumentException("Specify a 'to' filesystem.");
		}


		$file = [
			'type' => 'dir',
			'path' => '',
		];

		if ($this->fromPath !== '') {
			$file = $this->fromFilesystem->getMetadata($this->fromPath);
		}


		if ($file['type'] === 'file') {

			$fileSize = @$file['size'] ?: 0;

			$this->callProgressCallback(0, 1, 0, $fileSize);
			$this->callBeforeTransferCallback($file, $this->toPath);

			$this->copyFile(
				$this->fromFilesystem,
				$this->fromPath,
				$toFilesystem,
				$this->toPath,
				$this->overwriteFiles
			);

			$this->callAfterTransferCallback($file, $this->toPath);
			$this->callProgressCallback(1, 1, $fileSize, $fileSize);

		} else if ($file['type'] === 'dir') {

			$files = $this->fromFilesystem->filterContents(
				$this->filterPatterns,
				$this->fromPath,
				$this->recursive,
				$this->filterInverse
			);

			$totalFiles = count($files) + 1;
			$totalFileSize = $this->getFileSizesSum($files);



			$this->callProgressCallback(0, $totalFiles, 0, $totalFileSize);
			$this->callBeforeTransferCallback($file, $this->toPath);

			$this->createDirectory(
				$toFilesystem,
				$this->toPath,
				$this->overwriteEmptyDirectories,
				$this->overwriteNonEmptyDirectories
			);

			$this->callAfterTransferCallback($file, $this->toPath);
			$this->callProgressCallback(1, $totalFiles, 0, $totalFileSize);


			$filesTransferred = 1;
			$bytesTransferred = 0;
			$prefixLength = strlen($this->fromPath);

			foreach ($files as $file) {

				$fileSize = @$file['size'] ?: 0;
				$fromFilePath = $file['path'];
				$pathRelativeToTransfer = substr($fromFilePath, $prefixLength);
				$toFilePath = $this->toPath . $pathRelativeToTransfer;


				$this->callBeforeTransferCallback($file, $toFilePath);

				if ($file['type'] === 'dir') {

					$this->createDirectory(
						$toFilesystem,
						$toFilePath,
						$this->overwriteEmptyDirectories,
						$this->overwriteNonEmptyDirectories
					);

				} else if ($file['type'] === 'file') {

					$this->copyFile(
						$this->fromFilesystem,
						$fromFilePath,
						$toFilesystem,
						$toFilePath,
						$this->overwriteFiles
					);

				} else {
					throw new Exception("Can't transfer file type '{$file['type']}'. Path: $fromFilePath");
				}

				$this->callAfterTransferCallback($file, $toFilePath);

				$filesTransferred += 1;
				$bytesTransferred += $fileSize;
				$this->callProgressCallback($filesTransferred, $totalFiles, $bytesTransferred, $totalFileSize);
			}

		} else {
			throw new Exception("Can't transfer file type '{$file['type']}'. Path: {$file['path']}");
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

	protected function getFileSizesSum($files)
	{
		$sum = 0;

		foreach ($files as $file) {
			$size = @$file['size'];

			if (!is_null($size)) {
				$sum += $size;
			}
		}

		return $sum;
	}

	protected function createDirectory($filesystem, $path, $overwriteEmptyDirectories, $overwriteNonEmptyDirectories)
	{
		if (!$overwriteNonEmptyDirectories && $filesystem->has($path)) {

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

		$filesystem->createDir($path);
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
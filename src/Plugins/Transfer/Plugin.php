<?php

namespace JustDeploy\Plugins\Transfer;

use Exception;
use InvalidArgumentException;
use JustDeploy\FilesystemInterface;
use JustDeploy\HasFilesystemInterface;
use JustDeploy\Plugins\AbstractPlugin;
use League\Flysystem\Util;

class Plugin extends AbstractPlugin {

	public function getDefaultOptions()
	{
		return [
			'sourcePath' => '/',
			'destinationPath' => '/',
			'filterInverse' => false,
			'recursive' => true,
			'overwriteFiles' => false,
			'overwriteEmptyDirectories' => false,
			'overwriteNonEmptyDirectories' => false,
			'logProgress' => false,
		];
	}

	protected function log($message)
	{
		if ($this->logProgress) {
			echo $message ."\n";
		}
	}

	protected function getSourcePath()
	{
		return Util::normalizePath($this->sourcePath);
	}

	protected function getSourceFilesystem()
	{
		if ($this->source instanceof FilesystemInterface) {
			return $this->source;

		} else if ($this->source instanceof HasFilesystemInterface) {
			return $this->source->getFilesystem();

		} else {
			throw new InvalidArgumentException("Option 'source' is not a valid filesystem.");
		}
	}

	protected function getDestinationPath()
	{
		return Util::normalizePath($this->destinationPath);
	}

	protected function getDestinationFilesystem()
	{
		if ($this->destination instanceof FilesystemInterface) {
			return $this->destination;

		} else if ($this->destination instanceof HasFilesystemInterface) {
			return $this->destination->getFilesystem();

		} else {
			throw new InvalidArgumentException("Option 'destination' is not a valid filesystem.");
		}
	}

	public function transfer()
	{
		$startTime = microtime(true);

		$this->log("Locating files to transfer...");

		$files = $this->getFilesToTransfer();
		$bytesTotal = $this->getFileSizesSum($files);
		$filesTotal = count($files);

		$formattedFilesize = $this->formatFilesize($bytesTotal);
		$this->log("Found $filesTotal files with a total size of $formattedFilesize.");

		$bytesTransferred = 0;
		$filesTransferred = 0;

		foreach ($files as $file) {

			$progress = $this->calculateProgress($filesTransferred, $filesTotal, $bytesTransferred, $bytesTotal);
			$this->log("(".number_format($progress*100, 1)."%) /". $file['path']);

			$toFilePath = $this->rebaseFilePath($file['path'], $this->getSourcePath(), $this->getDestinationPath());

			$this->transferFile($file, $toFilePath);

			$filesTransferred += 1;
			$bytesTransferred += $this->getFileSize($file, 0);
		}

		$endTime = microtime(true);
		$formattedDuration = number_format($endTime - $startTime, 1);

		$this->log("Completed transfer of $filesTotal files ($formattedFilesize) in $formattedDuration seconds.");
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
				$this->getDestinationFilesystem(),
				$toPath,
				$this->overwriteEmptyDirectories,
				$this->overwriteNonEmptyDirectories
			);

		} else if ($file['type'] === 'file') {

			$this->copyFile(
				$this->getSourceFilesystem(),
				$file['path'],
				$this->getDestinationFilesystem(),
				$toPath,
				$this->overwriteFiles
			);

		} else {
			throw new Exception("Can't transfer file type '{$file['type']}'. Path: {$file['path']}");
		}
	}

	protected function getRootFile()
	{
		if ($this->getSourcePath() === '') {
			return [
				'type' => 'dir',
				'path' => '',
			];
		} else {
			return $this->getSourceFilesystem()->getMetadata($this->getSourcePath());
		}
	}

	protected function getFilesToTransfer()
	{
		$rootFile = $this->getRootFile();

		if ($rootFile['type'] === 'dir') {

			$directoryContents = $this->getSourceFilesystem()->filterContents(
				$this->filterPatterns,
				$this->getSourcePath(),
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

	protected function formatFilesize($bytes, $precision = 2)
	{ 
		$units = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);

		$bytes /= pow(1024, $pow);

		return round($bytes, $precision) . ' ' . $units[$pow];
	}

	protected function calculateProgress($filesTransferred, $filesTotal, $bytesTransferred, $bytesTotal)
	{
		$filesProgress = $filesTotal ? $filesTransferred/$filesTotal : 1;
		$bytesProgress = $bytesTotal ? $bytesTransferred/$bytesTotal : 1;

		$weightedProgress = 0;

		if ($filesTotal && $bytesTotal) {
			// Usually there is a significant overhead associated with filesystem access. For example: 
			// creating a thousand empty files still take some time even though the combined filesize is 0 bytes.
			// That's the reason we weight in the total number of files, besides the filesize.
			$weightedProgress = $filesProgress*0.3 + $bytesProgress*0.7;

		} else if ($filesTotal) {
			$weightedProgress = $filesProgress;

		} else if ($bytesProgress) {
			$weightedProgress = $bytesProgress;
		}

		// Only return 100% if that's actually the case:
		if ($filesTransferred === $filesTotal && $bytesTransferred === $bytesTotal) {
			$weightedProgress = 1;

		} else if ($weightedProgress > 0.999) {
			// Never round up to 100%:
			$weightedProgress = 0.999;
		}

		return $weightedProgress;
	}

}
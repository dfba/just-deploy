<?php

namespace JustDeploy\Plugins\AtomicSymlinkDeployment;

use InvalidArgumentException;
use JustDeploy\Plugins\AbstractPlugin;
use JustDeploy\HasFilesystemInterface;
use JustDeploy\FilesystemInterface;
use JustDeploy\HasShellInterface;
use JustDeploy\ShellInterface;
use League\Flysystem\Util;

class Plugin extends AbstractPlugin {

	public function getDefaultOptions()
	{
		return [
			'currentLink' => 'current',
			'directory' => 'deployments',
			'successFile' => '.deployment-successful',
			'keepSuccessful' => true,
			'keepFailed' => true,
			'logProgress' => false,
			'generateName' => [$this, 'generateNameDefault'],
			'compareName' => [$this, 'compareNameDefault'],
		];
	}

	protected function getFilesystem()
	{
		if ($this->destination instanceof FilesystemInterface) {
			return $this->destination;

		} else if ($this->destination instanceof HasFilesystemInterface) {
			return $this->destination->getFilesystem();

		} else {
			throw new InvalidArgumentException("Option 'destination' is not a valid filesystem.");
		}
	}

	protected function getShell()
	{
		if ($this->destination instanceof ShellInterface) {
			return $this->destination;

		} else if ($this->destination instanceof HasShellInterface) {
			return $this->destination->getShell();

		} else {
			throw new InvalidArgumentException("Option 'destination' is not a valid shell.");
		}
	}

	protected function log($message)
	{
		if ($this->logProgress) {
			echo $message ."\n";
		}
	}

	protected function generateNameDefault()
	{
		return date('Y.m.d-H.i.s') .'-'. uniqid('', true);
	}

	protected function compareNameDefault($fileA, $fileB)
	{
		return -1 * strcmp($fileA['basename'], $fileB['basename']);
	}

	public function deploy(callable $prepare, callable $finalize = null)
	{
		$deploymentName = call_user_func($this->generateName);
		$deploymentPath = Util::normalizePath($this->directory .'/'. $deploymentName);
		$filesystem = $this->getFilesystem();

		if (strpos($this->successFile, '/') !== false) {
			throw new InvalidArgumentException("Option 'successFile' may not contain slashes.");
		}

		$this->log("Starting atomic deployment: $deploymentName");

		$filesystem->createDir($deploymentPath);

		call_user_func($prepare, $deploymentPath);

		$this->log("Publishing deployment...");

		$filesystem->write($deploymentPath .'/'. $this->successFile, '');
		$this->atomicSymlink($this->currentLink, $deploymentPath);

		$this->log("Deployment published!");

		if ($finalize) {
			call_user_func($finalize, $deploymentPath);
		}

		$this->cleanup($deploymentName);

	}

	public function atomicSymlink($from, $to)
	{
		$shell = $this->getShell();

		$fromArgument = $shell->escape($shell->resolvePath($from));
		$toArgument = $shell->escape($shell->resolvePath($to));

		$shell->exec("ln -snf $toArgument $fromArgument");
	}

	protected function cleanup($current)
	{
		$this->log("Locating old deployments...");

		$filesystem = $this->getFilesystem();
		$files = $filesystem->listContents($this->directory);

		$successfulDeployments = [];
		$failedDeployments = [];

		foreach ($files as $file) {

			if ($file['basename'] === $current) {
				continue;
			}

			$successful = $filesystem->has($file['path'] .'/'. $this->successFile);

			if ($successful) {
				$successfulDeployments[] = $file;
			} else {
				$failedDeployments[] = $file;
			}
		}

		$this->log(
			"Found ". count($successfulDeployments) .
			" old deployments and ". count($failedDeployments) .
			" failed deployments."
		);

		usort($successfulDeployments, $this->compareName);
		usort($failedDeployments, $this->compareName);

		if ($this->keepFailed !== true) {

			$keepFailed = (int) $this->keepFailed;

			$this->log(
				"Keeping $keepFailed recently failed deployment(s) and removing ". 
				(count($failedDeployments) - $keepFailed) ."."
			);

			$this->removeDeployments(
				array_slice($failedDeployments, $keepFailed)
			);
		}

		if ($this->keepSuccessful !== true) {

			$keepSuccessful = (int) $this->keepSuccessful;

			$this->log(
				"Keeping $keepSuccessful recently succeeded deployment(s) and removing ". 
				(count($successfulDeployments) - $keepSuccessful) ."."
			);

			$this->removeDeployments(
				array_slice($successfulDeployments, (int) $this->keepSuccessful)
			);
		}
	}

	protected function removeDeployments($deployments)
	{
		foreach ($deployments as $file) {
			$this->log("Removing deployment: ". $file['basename']);
			$this->removeDirectory($file['path']);
		}
	}

	protected function removeDirectory($path)
	{
		$shell = $this->getShell();

		$pathArgument = $shell->escape($shell->resolvePath($path));
		// Using `rm -r` is *much* faster than its equivalent: `$this->getFilesystem()->deleteDir($file['path']);`
		$shell->exec("rm -r $pathArgument");
	}

}
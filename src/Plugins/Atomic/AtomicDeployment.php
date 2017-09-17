<?php

namespace JustDeploy\Plugins\Atomic;

use InvalidArgumentException;
use JustDeploy\FilesystemInterface;
use League\Flysystem\Util;

class AtomicDeployment {

	protected $filesystem;
	protected $shell;

	protected $currentLink;
	protected $directory;
	protected $successFile;

	protected $keepFailed;
	protected $keepSuccessful;

	protected $generateName;
	protected $compareName;

	protected $logProgress;

	public function __construct($options)
	{
		$this->filesystem = $options['filesystem'];
		$this->shell = @$options['shell'] ?: $this->filesystem->getShell();

		$this->currentLink = Util::normalizePath(@$options['currentLink'] ?: 'current');
		$this->directory = Util::normalizePath(@$options['directory'] ?: 'deployments');
		$this->successFile = Util::normalizePath(@$options['successFile'] ?: '.deployment-successful');

		$this->keepSuccessful = isset($options['keepSuccessful']) ? $options['keepSuccessful'] : true;
		$this->keepFailed = isset($options['keepFailed']) ? $options['keepFailed'] : true;
		
		$this->generateName = @$options['generateName'] ?: [$this, 'generateNameDefault'];
		$this->compareName = @$options['compareName'] ?: [$this, 'compareNameDefault'];

		$this->logProgress = isset($options['logProgress']) ? $options['logProgress'] : false;


		if (!$this->filesystem instanceof FilesystemInterface) {
			throw new InvalidArgumentException("Option 'filesystem' is not a valid filesystem.");
		}

		if (strpos($this->currentLink, '/') !== false) {
			throw new InvalidArgumentException("Option 'currentLink' may not contain slashes.");
		}

		if (strpos($this->directory, '/') !== false) {
			throw new InvalidArgumentException("Option 'directory' may not contain slashes.");
		}

		if (strpos($this->successFile, '/') !== false) {
			throw new InvalidArgumentException("Option 'successFile' may not contain slashes.");
		}

		if (!is_callable($this->generateName)) {
			throw new InvalidArgumentException("Option 'generateName' must be a callable.");
		}

		if (!is_callable($this->compareName)) {
			throw new InvalidArgumentException("Option 'compareName' must be a callable.");
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

		$this->log("Starting atomic deployment: $deploymentName");

		$this->filesystem->createDir($deploymentPath);

		call_user_func($prepare, $deploymentPath);

		$this->log("Publishing deployment...");

		$this->filesystem->write($deploymentPath .'/'. $this->successFile, '');
		$this->atomicSymlink($this->currentLink, $deploymentPath);

		$this->log("Deployment published!");

		if ($finalize) {
			call_user_func($finalize, $deploymentPath);
		}

		$this->cleanup($deploymentName);

	}

	public function atomicSymlink($from, $to)
	{
		$fromArgument = $this->shell->escape($this->shell->resolvePath($from));
		$toArgument = $this->shell->escape($this->shell->resolvePath($to));

		$this->shell->exec("ln -snf $toArgument $fromArgument");
	}

	protected function cleanup($current)
	{
		$this->log("Locating old deployments...");

		$files = $this->filesystem->listContents($this->directory);

		$successfulDeployments = [];
		$failedDeployments = [];

		foreach ($files as $file) {

			if ($file['basename'] === $current) {
				continue;
			}

			$successful = $this->filesystem->has($file['path'] .'/'. $this->successFile);

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
		$pathArgument = $this->shell->escape($this->shell->resolvePath($path));
		// Using `rm -r` is *much* faster than its equivalent: `$this->filesystem->deleteDir($file['path']);`
		$this->shell->exec("rm -r $pathArgument");
	}

}
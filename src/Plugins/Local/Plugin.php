<?php

namespace JustDeploy\Plugins\Local;

use JustDeploy\HasFilesystemInterface;
use JustDeploy\HasShellInterface;
use JustDeploy\ShellException;
use JustDeploy\ShellInterface;
use JustDeploy\Plugins\AbstractPlugin;
use JustDeploy\Flysystem\FilterContentsPlugin;
use League\Flysystem\Util;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Adapter\Local as LocalAdapter;

class Plugin extends AbstractPlugin implements ShellInterface, HasShellInterface, HasFilesystemInterface {

	public function getDefaultOptions()
	{
		return [
			'path' => '/',
			'writeFlags' => LOCK_EX,
			'linkHandling' => LocalAdapter::DISALLOW_LINKS,
			'permissions' => [],
		];
	}

	public function getShell()
	{
		return $this;
	}
	
	public function getFilesystem()
	{
		return $this->flysystem;
	}

	protected function memoizeFlysystem()
	{
		$flysystem = new Flysystem(new LocalAdapter(
			$this->path,
			$this->writeFlags,
			$this->linkHandling,
			$this->permissions
		));

		$flysystem->addPlugin(new FilterContentsPlugin());

		return $flysystem;
	}

	public function resolvePath($path)
	{
		$basePath = realpath($this->path);
		$normalizedPath = Util::normalizePath($path);

		if (strlen($normalizedPath)) {
			return $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
		} else {
			return $basePath;
		}
	}

	public function escape($argument)
	{
		return escapeshellarg($argument);
	}

	protected function buildArguments(array $arguments)
	{
		$suffix = '';

		foreach ($arguments as $key => $value) {

			$value = trim($value);

			if (is_numeric($key)) {
				if (strlen($value)) {
					$suffix .= ' '. trim($value);
				}
			} else {

				$key = trim($key);

				if (strlen($key) || strlen($value)) {
					$suffix .= ' '. $key .'='. trim($value);
				}
			}
		}

		return $suffix;
	}

	public function exec($command, array $arguments=[], $cwd='/')
	{
		$process = proc_open(
			$command . $this->buildArguments($arguments),
			[
				1 => ['pipe', 'w'],
				2 => ['pipe', 'w'],
			],
			$pipes,
			$this->resolvePath($cwd)
		);

		if (!$process) {
			$lastError = error_get_last();

			throw new ShellException("Command `$command` exited with error message: \"". $lastError['message'] ."\"");
		}

		$stdout = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);

		$exitStatus = proc_close($process);

		if ($exitStatus) {
			throw new ShellException("Command `$command` exited with status code: $exitStatus. Message: \"". trim($stderr) ."\"", $exitStatus);
		}

		return [
			'stdout' => $stdout,
			'stderr' => $stderr,
		];
	}

	public function __call($method, $arguments)
	{
		return call_user_func_array([$this->filesystem, $method], $arguments);
	}

}
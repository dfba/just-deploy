<?php

namespace JustDeploy\Plugins\SSH;

use League\Flysystem\Util;
use JustDeploy\ShellException;
use JustDeploy\ShellInterface;
use JustDeploy\Plugins\AbstractPlugin;
use JustDeploy\Flysystem\FilterContentsPlugin;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Sftp\SftpAdapter;

class Plugin extends AbstractPlugin implements ShellInterface {

	protected $connection = null;

	public function getDefaultOptions()
	{
		return [
			'port' => 22,
			'path' => '/',
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

	protected function memoizeSftpAdapter()
	{
		return new SftpAdapter([
			'host' => $this->host,
			'port' => $this->port,
			'username' => $this->username,
			'password' => $this->password,
			'root' => $this->path,
		]);
	}

	protected function memoizeFlysystem()
	{
		$flysystem = new Flysystem($this->sftpAdapter);

		$flysystem->addPlugin(new FilterContentsPlugin());

		return $flysystem;
	}

	public function resolvePath($path)
	{
		$path = Util::normalizePath($this->path .'/'. $path);
		$path = '/'. rtrim($path, '/');

		if (strlen($path)) {
			return $path;

		} else {
			return '/';
		}
	}

	public function escape($argument)
	{
		return "'". str_replace("'", "\\'", $argument) ."'";
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
		$ssh = $this->sftpAdapter->getConnection();

		$wasQuietModeEnabled = $ssh->isQuietModeEnabled();
		if (!$wasQuietModeEnabled) {
			$ssh->enableQuietMode();
		}

		try {
			$cwdArgument = $this->escape($this->resolvePath($cwd));
			$argumentsSuffix = $this->buildArguments($arguments);

			$stdout = $ssh->exec("cd $cwdArgument\n$command$argumentsSuffix");
			$stderr = $ssh->getStdError();
			$exitStatus = $ssh->getExitStatus();

			if ($exitStatus) {
				throw new ShellException("SSH Command `$command` exited with status code: $exitStatus. Message: \"". trim($stderr) ."\"", $exitStatus);
			}

			return [
				'stdout' => $stdout,
				'stderr' => $stderr,
			];

		} finally {
			if (!$wasQuietModeEnabled) {
				$ssh->disableQuietMode();
			}
		}
	}

	public function __call($method, $arguments)
	{
		return call_user_func_array([$this->filesystem, $method], $arguments);
	}

}
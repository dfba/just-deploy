<?php

namespace JustDeploy\Plugins\FTP;

use JustDeploy\HasFilesystemInterface;
use JustDeploy\Plugins\AbstractPlugin;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Adapter\Ftp as FtpAdapter;

class Plugin extends AbstractPlugin implements HasFilesystemInterface {

	public function getDefaultOptions()
	{
		return [
			'port' => 21,
			'path' => '/',
		];
	}

	public function getFilesystem()
	{
		return $this->flysystem;
	}

	protected function memoizeFlysystemAttribute()
	{
		return new Flysystem(new FtpAdapter([
			'host' => $this->host,
			'port' => $this->port,
			'username' => $this->username,
			'password' => $this->password,
			'root' => $this->path,
		]));
	}

	public function __call($method, $arguments)
	{
		return call_user_func_array([$this->getFilesystem(), $method], $arguments);
	}

}
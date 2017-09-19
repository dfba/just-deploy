<?php

namespace JustDeploy\Plugins\FTP;

use JustDeploy\Plugins\AbstractPlugin;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Adapter\Ftp as FtpAdapter;

class Plugin extends AbstractPlugin {

	public function getDefaultOptions()
	{
		return [
			'port' => 21,
			'path' => '/',
		];
	}

	protected function memoizeFilesystem()
	{
		return new Flysystem(new FtpAdapter([
			'host' => $this->host,
			'port' => $this->port,
			'username' => $this->username,
			'password' => $this->password,
			'root' => $this->path,
		]));
	}

}
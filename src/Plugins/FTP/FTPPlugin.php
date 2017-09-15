<?php

namespace JustDeploy\Plugins\FTP;

use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Adapter\Ftp as FtpAdapter;

class FTPPlugin {

	public function create($options)
	{
		return (object) [
			'filesystem' => $this->createFilesystem($options),
		];
	}

	protected function createFilesystem($options)
	{
		return new Flysystem(new FtpAdapter([
			'host' => @$options['host'],
			'port' => @$options['port'],
			'username' => @$options['username'],
			'password' => @$options['password'],
			'root' => @$options['path'],
		]));
	}

}
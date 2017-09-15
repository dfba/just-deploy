<?php

namespace JustDeploy\Plugins\SSH;

use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Sftp\SftpAdapter;
use Falc\Flysystem\Plugin\Symlink\Sftp as SftpSymlinkPlugin;

class SSHPlugin {

	public function create($options)
	{
		return (object) [
			'shell' => $this->createShell($options),
			'filesystem' => $this->createFilesystem($options),
		];
	}

	protected function createShell($options)
	{
		return new Shell([
			'host' => @$options['host'],
			'port' => @$options['port'],
			'username' => @$options['username'],
			'password' => @$options['password'],
			'cwd' => @$options['path'],
		]);
	}

	protected function createFilesystem($options)
	{
		$filesystem = new Flysystem(new SftpAdapter([
			'host' => @$options['host'],
			'port' => @$options['port'],
			'username' => @$options['username'],
			'password' => @$options['password'],
			'root' => @$options['path'],
		]));

		$filesystem->addPlugin(new SftpSymlinkPlugin\Symlink());
		$filesystem->addPlugin(new SftpSymlinkPlugin\DeleteSymlink());
		$filesystem->addPlugin(new SftpSymlinkPlugin\IsSymlink());

		return $filesystem;
	}

}
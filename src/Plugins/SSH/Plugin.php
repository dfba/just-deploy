<?php

namespace JustDeploy\Plugins\SSH;

use JustDeploy\Plugins\AbstractPlugin;
use JustDeploy\Flysystem\FilterContentsPlugin;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Sftp\SftpAdapter;
use Falc\Flysystem\Plugin\Symlink\Sftp as SftpSymlinkPlugin;

class Plugin extends AbstractPlugin {

	public function getDefaultOptions()
	{
		return [
			'port' => 22,
			'path' => '/',
		];
	}

	protected function memoizeShell()
	{
		return new Shell([
			'host' => $this->host,
			'port' => $this->port,
			'username' => $this->username,
			'password' => $this->password,
			'cwd' => $this->path,
		]);
	}

	protected function memoizeFilesystem()
	{
		$filesystem = new Flysystem(new SftpAdapter([
			'host' => $this->host,
			'port' => $this->port,
			'username' => $this->username,
			'password' => $this->password,
			'root' => $this->path,
		]));

		$filesystem->addPlugin(new SftpSymlinkPlugin\Symlink());
		$filesystem->addPlugin(new SftpSymlinkPlugin\DeleteSymlink());
		$filesystem->addPlugin(new SftpSymlinkPlugin\IsSymlink());
		$filesystem->addPlugin(new FilterContentsPlugin());

		return $filesystem;
	}

}
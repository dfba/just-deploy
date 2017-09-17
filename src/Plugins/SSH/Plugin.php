<?php

namespace JustDeploy\Plugins\SSH;

use JustDeploy\Flysystem\ShellPlugin;
use JustDeploy\Flysystem\FilterContentsPlugin;
use JustDeploy\Flysystem\NewTransferPlugin;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Sftp\SftpAdapter;
use Falc\Flysystem\Plugin\Symlink\Sftp as SftpSymlinkPlugin;

class Plugin {

	public function make($options)
	{
		$filesystem = $this->createFilesystem($options);
		$filesystem->addPlugin(new FilterContentsPlugin());
		$filesystem->addPlugin(new NewTransferPlugin());
		$filesystem->addPlugin(new ShellPlugin(
			$this->createShell($options)
		));

		return $filesystem;
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
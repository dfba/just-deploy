<?php

namespace JustDeploy;

use Exception;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Adapter\Ftp as FtpAdapter;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\Sftp\SftpAdapter;
use Falc\Flysystem\Plugin\Symlink\Sftp as SftpSymlinkPlugin;

class Filesystem {

	protected $flysystem = null;

	public function __construct(Flysystem $flysystem)
	{
		$this->flysystem = $flysystem;
	}

	public static function local(array $options)
	{
		$root = $options['root'];

		if (!strlen($root)) {
			throw new Exception("Root path is a required option.");
		}

		$writeFlags = isset($options['writeFlags']) ? $options['writeFlags'] : LOCK_EX;
		$linkHandling = isset($options['linkHandling']) ? $options['linkHandling'] : LocalAdapter::DISALLOW_LINKS;
		$permissions = isset($options['permissions']) ? $options['permissions'] : [];

		return new static(new Flysystem(new LocalAdapter(
			$root,
			$writeFlags,
			$linkHandling,
			$permissions
		)));
	}

	public static function ftp(array $options)
	{
		return new static(new Flysystem(new FtpAdapter($options)));
	}

	public static function sftp(array $options)
	{
		$filesystem = new static(new Flysystem(new SftpAdapter($options)));

		$filesystem->addPlugin(new SftpSymlinkPlugin\Symlink());
		$filesystem->addPlugin(new SftpSymlinkPlugin\DeleteSymlink());
		$filesystem->addPlugin(new SftpSymlinkPlugin\IsSymlink());

		return $filesystem;
	}


	public function __call($name, array $arguments)
	{
		$result = call_user_func_array([$this->flysystem, $name], $arguments);

		if ($result === $this->flysystem) {
			return $this;
		} else {
			return $result;
		}
	}

}
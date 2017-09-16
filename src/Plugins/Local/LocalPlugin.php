<?php

namespace JustDeploy\Plugins\Local;

use Exception;
use JustDeploy\Flysystem\ShellPlugin;
use JustDeploy\Flysystem\FilterContentsPlugin;
use JustDeploy\Flysystem\NewTransferPlugin;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Adapter\Local as LocalAdapter;

class LocalPlugin {

	public function create($options)
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
			'cwd' => @$options['path'],
		]);
	}

	protected function createFilesystem($options)
	{
		$root = $options['path'];

		if (!strlen($root)) {
			throw new Exception("Root path is a required option.");
		}

		$writeFlags = isset($options['writeFlags']) ? $options['writeFlags'] : LOCK_EX;
		$linkHandling = isset($options['linkHandling']) ? $options['linkHandling'] : LocalAdapter::DISALLOW_LINKS;
		$permissions = isset($options['permissions']) ? $options['permissions'] : [];

		return new Flysystem(new LocalAdapter(
			$root,
			$writeFlags,
			$linkHandling,
			$permissions
		));
	}

}
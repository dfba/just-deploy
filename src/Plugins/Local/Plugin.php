<?php

namespace JustDeploy\Plugins\Local;

use JustDeploy\Plugins\AbstractPlugin;
use JustDeploy\Flysystem\FilterContentsPlugin;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Adapter\Local as LocalAdapter;

class Plugin extends AbstractPlugin {

	public function getDefaultOptions()
	{
		return [
			'path' => '/',
			'writeFlags' => LOCK_EX,
			'linkHandling' => LocalAdapter::DISALLOW_LINKS,
			'permissions' => [],
		];
	}

	protected function memoizeShell()
	{
		return new Shell([
			'cwd' => $this->path,
		]);
	}

	protected function memoizeFilesystem()
	{
		$filesystem = new Flysystem(new LocalAdapter(
			$this->path,
			$this->writeFlags,
			$this->linkHandling,
			$this->permissions
		));

		$filesystem->addPlugin(new FilterContentsPlugin());

		return $filesystem;
	}

}
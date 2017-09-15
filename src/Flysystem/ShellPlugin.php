<?php

namespace JustDeploy\Flysystem;

use League\Flysystem\Plugin\AbstractPlugin;

class ShellPlugin extends AbstractPlugin {

	protected $shell;

	public function __construct($shell)
	{
		$this->shell = $shell;
	}

	public function getMethod()
	{
		return 'getShell';
	}

	public function handle()
	{
		return $this->shell;
	}

}
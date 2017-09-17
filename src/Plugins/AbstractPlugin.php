<?php

namespace JustDeploy\Plugins;

use JustDeploy\Deployment;

abstract class AbstractPlugin {

	protected $deployment;

	public function __construct(Deployment $deployment)
	{
		$this->deployment = $deployment;
	}

	abstract public function make($options);

}
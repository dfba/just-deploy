<?php

namespace JustDeploy\Plugins\Atomic;

use JustDeploy\Plugins\AbstractPlugin;

class Plugin extends AbstractPlugin {

	public function make($options)
	{
		return new AtomicDeployment($options);
	}

}
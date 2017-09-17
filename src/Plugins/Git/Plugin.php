<?php

namespace JustDeploy\Plugins\Git;

use JustDeploy\Plugins\AbstractPlugin;

class Plugin extends AbstractPlugin {

	public function make($options)
	{
		return new Git($options['shell']);
	}

}
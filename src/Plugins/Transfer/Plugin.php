<?php

namespace JustDeploy\Plugins\Transfer;

use JustDeploy\Plugins\AbstractPlugin;

class Plugin extends AbstractPlugin {

	public function make($options)
	{
		return new Transfer($options);
	}

}
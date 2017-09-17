<?php

namespace JustDeploy\Plugins\Git;

class Plugin {

	public function make($options)
	{
		return new Git($options['shell']);
	}

}
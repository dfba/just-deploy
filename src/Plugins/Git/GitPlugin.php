<?php

namespace JustDeploy\Plugins\Git;

class GitPlugin {

	public function make($options)
	{
		return new Git($options['shell']);
	}

}
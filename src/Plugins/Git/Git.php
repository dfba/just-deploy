<?php

namespace JustDeploy\Plugins\Git;

class Git {

	protected $shell;

	public function __construct($shell)
	{
		$this->shell = $shell;
	}

	public function exec($command)
	{
		$result = $this->shell->exec('git '. $command);

		return rtrim($result['stdout']);
	}

	public function getCurrentBranch()
	{
		$branch = $this->exec('rev-parse --abbrev-ref HEAD');

		if (strlen($branch) && $branch !== 'HEAD') {
			return $branch;
		}

		return null;
	}

	public function getCurrentCommit()
	{
		$commit = $this->exec('rev-parse --verify HEAD');

		if (strlen($commit)) {
			return $commit;
		}

		return null;
	}

	public function checkout($branch)
	{
		$this->exec('checkout '.$this->shell->escape($branch).' --quiet');

		return $this;
	}

	public function status()
	{
		return $this->exec('status --porcelain');
	}

}
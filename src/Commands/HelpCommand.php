<?php

namespace JustDeploy\Commands;

class HelpCommand extends Command {

	protected function help()
	{
		return "
Available commands:
deploy                   Deploy the application. Take all these steps:
test-local-requirements  Test whether this machine has all required packages
                          installed.
test-connection          Test the SSH connection.
test-remote-requirements Test whether the remote server has all required 
                          packages installed.
run-before-deploy        Run the `before-deploy` script remotely.
git-checkout             Checkout the remote repository.
run-after-deploy         Run the `after-deploy` script remotely.
		";
	}

	public function run()
	{
		$this->output($this->getHelpText());
	}

}
<?php

namespace GitSshDeploy\Commands;

use InvalidArgumentException;

abstract class Command {

	protected $name;
	protected $arguments = [];
	protected $quiet = true;
	protected $help = false;
	protected $workingDirectory;

	public function __construct($arguments)
	{
		$this->name = $arguments['command'];
		$this->arguments = $arguments['arguments'];
		$this->quiet = !!@$arguments['--quiet'];
		$this->help = !!@$arguments['--help'];
		$this->workingDirectory = realpath(@$arguments['--directory'] ?: getcwd());

		if ($this->workingDirectory === false) {
			throw new InvalidArgumentException("Invalid or non-existing working directory.");
		}
	}

	protected function argument($name, $defaultValue=null)
	{
		return isset($this->arguments[$name]) ? $this->arguments[$name] : $defaultValue;
	}

	protected function help()
	{
		return "No help available.";
	}

	abstract public function run();

	public function execute()
	{
		if ($this->help) {
			$this->output($this->getHelpText());

		} else {
			$this->run();
		}
	}

	protected function getHelpText()
	{
		$helpText = $this->help();
		$helpText = trim(preg_replace("/\r\n|\r|\n/", PHP_EOL, $helpText));

		return "Git SSH Deploy (c) ". date('Y') ." Dave Bakker". PHP_EOL . $helpText;
	}

	public function output($data)
	{
		if (!$this->quiet) {

			if (is_scalar($data)) {
				echo $data . PHP_EOL;
				
			} else {
				echo var_export($data, true) . PHP_EOL;
			}
		}
	}

}
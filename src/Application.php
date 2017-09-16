<?php

namespace JustDeploy;

use Exception;
use InvalidArgumentException;

class Application {
	
	public function execute(array $cmdArguments, array $env)
	{
		$environment = $this->parseEnvironmentVariables($env);
		$arguments = $this->parseArguments($cmdArguments);
		$task = $arguments['task'] ?: 'default';
		$taskName = lcfirst($this->camelCase($task));

		$deployFile = realpath(@$arguments['--deploy-file'] ?: getcwd() .'/just-deploy.php');

		if (!file_exists($deployFile)) {
			throw new Exception("Deploy file does not exist: $deployFile");
		}

		// Relative paths used in the deploy file should be relative to the deploy file's parent directory:
		chdir(dirname($deployFile));
		require_sandboxed($deployFile);

		if (!class_exists('Deployment')) {
			throw new Exception("Deploy file does not define a class called 'Deployment'.");
		}

		$deployment = new \Deployment();

		$tasks = $deployment->getTasks();

		if (!in_array($taskName, $tasks)) {
			throw new Exception("Deploy file does not define a task called '$taskName'.");
		}

		$deployment->runTask($taskName);
		
	}

	protected function camelCase($string)
	{
		$normalizedString = trim(preg_replace('/\s+/u', ' ', preg_replace('/[^A-Za-z0-9]/u', ' ', $string)));

		return str_replace(' ', '', mb_convert_case($normalizedString, MB_CASE_TITLE));
	}

	protected function parseEnvironmentVariables(array $env)
	{
		$variables = [];

		foreach ($env as $key => $value) {
			$lowercaseKey = mb_strtolower($key);

			if (substr($lowercaseKey, 0, 11) === 'justdeploy_') {
				$variables[substr($key, 11)] = $value;
			}
		}

		return $variables;
	}

	protected function parseArguments(array $cmdArguments)
	{
		$arguments = [
			'task' => null,
			'arguments' => [],
		];

		foreach ($cmdArguments as $index => $argument) {
			
			if (mb_substr($argument, 0, 1) === '-') { // Is named argument?

				$name = null;
				$value = null;

				$nameEnd = mb_strpos($argument, '=');
				if ($nameEnd === false) { // Value-less?

					$name = $argument;
					$value = true;

				} else { // With value

					$name = mb_substr($argument, 0, $nameEnd);
					$value = mb_substr($argument, $nameEnd+1);
				}

				$arguments['arguments'][$name] = $value;

			} else if ($index === 0) { // Is task name?

				$arguments['task'] = $argument;

			} else { // Must be an ordinary anonymous argument

				$arguments['arguments'][] = $argument;
			}
		}

		return $arguments;
	}

}
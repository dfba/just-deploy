<?php

namespace JustDeploy;

use Exception;
use InvalidArgumentException;

class Application {
	
	public function execute(array $cmdArguments, array $env)
	{
		$environmentVariables = $this->parseEnvironmentVariables($env);
		$arguments = $this->parseArguments($cmdArguments);

		$deployClassName = 'JustDeploy'. $this->camelCase(@$arguments[0] ?: '');
		$defaultDeployDirectory = getcwd();
		$suppliedDeployDirectory = @$arguments['--deploy-directory'];
		$deployDirectory = $suppliedDeployDirectory ?: $defaultDeployDirectory;

		if (!file_exists($deployDirectory)) {
			throw new Exception("Deploy directory does not exist: $deployDirectory");
		}

		$deployFiles = $this->findDeployFiles($deployDirectory);

		if (!array_key_exists($deployClassName, $deployFiles)) {
			throw new Exception("No deploy file found called '$deployClassName.php'.");
		}

		$this->registerAutoloader($deployDirectory, $deployFiles);

		if (!class_exists($deployClassName)) {
			throw new Exception("Deploy file does not define a class called '$deployClassName'.");
		}

		$task = lcfirst($this->camelCase(@$arguments['--task'] ?: 'default'));

		$this->runDeployment($deployDirectory, $deployClassName, $task);
		
	}

	protected function runDeployment($deployDirectory, $deployClassName, $task)
	{
		$this->runInCwd($deployDirectory, function() use ($deployClassName, $task) {
			
			$instantiateClassName = '\\'. $deployClassName;

			$deployment = new $instantiateClassName();

			if (!$deployment instanceof Deployment) {
				throw new Exception("Deploy class '$deployClassName' must extend `JustDeploy\Deployment`.");
			}

			$tasks = $deployment->getTasks();

			if (!in_array($task, $tasks)) {
				throw new Exception("Deploy file does not define a task called '$task'.");
			}

			$deployment->runTask($task);
		});
	}

	protected function registerAutoloader($deployDirectory, array $deployFiles)
	{
		spl_autoload_register(function($className) use($deployDirectory, $deployFiles) {

			if (array_key_exists($className, $deployFiles)) {

				$classFile = $deployFiles[$className];

				$this->runInCwd($deployDirectory, function() use ($classFile) {
					require_sandboxed($classFile);
				});

				return true;
			}

		}, true, true);
	}

	protected function findDeployFiles($directory)
	{
		$deployFiles = [];

		foreach (scandir($directory) as $file) {
			$lowercaseFile = mb_convert_case($file, MB_CASE_LOWER);

			if (substr($lowercaseFile, 0, 10) === 'justdeploy' &&
				substr($lowercaseFile, -4) === '.php') {

				$className = substr($file, 0, -4);

				$deployFiles[$className] = realpath($directory .DIRECTORY_SEPARATOR. $file);
			}
		}

		return $deployFiles;
	}

	protected function runInCwd($cwd, callable $callback)
	{
		$previousCwd = getcwd();
		chdir($cwd);

		try {
			return call_user_func($callback);
		} finally {
			chdir($previousCwd);
		}
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
		$arguments = [];

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

				$arguments[$name] = $value;

			} else { // Must be an ordinary anonymous argument

				$arguments[] = $argument;
			}
		}

		return $arguments;
	}

}
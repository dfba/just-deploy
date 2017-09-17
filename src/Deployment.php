<?php

namespace JustDeploy;

use ReflectionClass;

use JustDeploy\Plugins\Local\Plugin as LocalPlugin;
use JustDeploy\Plugins\FTP\Plugin as FTPPlugin;
use JustDeploy\Plugins\SSH\Plugin as SSHPlugin;
use JustDeploy\Plugins\Git\Plugin as GitPlugin;

class Deployment {

	public $defaultPlugins = [
		'local' => LocalPlugin::class,
		'ftp' => FTPPlugin::class,
		'ssh' => SSHPlugin::class,
		'sftp' => SSHPlugin::class, // Alias
		'git' => GitPlugin::class,
	];

	public $plugins = [];

	public function __construct()
	{
		$this->constructPlugins(
			$this->getPlugins()
		);

		$this->initializePlugins(
			$this->getPluginInitializers()
		);
	}

	private function getPlugins()
	{
		return array_merge($this->defaultPlugins, $this->plugins);
	}

	private function constructPlugins(array $plugins)
	{
		$cache = [];

		foreach ($plugins as $name => $className) {

			if (!array_key_exists($className, $cache)) {
				$cache[$className] = new $className();
			}

			$this->{$name} = $cache[$className];
		}
	}

	private function initializePlugins(array $initializers)
	{
		foreach ($initializers as $initializer) {

			$name = $initializer['name'];
			$plugin = $this->{$initializer['plugin']};
			
			$options = call_user_func([$this, $initializer['initializer']]);
			$this->{$name} = $plugin->make($options);

		}
	}

	public function runTask($task, $arguments = [])
	{
		$methodName = 'task'. ucfirst($task);

		return call_user_func_array([$this, $methodName], $arguments);
	}

	public function getTasks()
	{
		$tasks = [];

		$methods = $this->getCallableMethods();

		foreach ($methods as $method) {
			$methodName = $method->getName();

			if (substr($methodName, 0, 4) === 'task') {
				$tasks[] = lcfirst(substr($methodName, 4));
			}
		}

		return $tasks;
	}

	private function getCallableMethods()
	{
		$methods = [];

		$class = new ReflectionClass($this);

		foreach ($class->getMethods() as $method) {

			if (!$method->isAbstract() && 
				!$method->isConstructor() && 
				!$method->isDestructor() && 
				!$method->isStatic()
			) {
				$methods[] = $method;
			}
		}

		return $methods;
	}

	private function getPluginInitializers()
	{
		$initializers = [];

		$plugins = $this->getPlugins();
		$methods = $this->getCallableMethods();

		foreach ($methods as $method) {

			$methodName = $method->getName();

			preg_match('/[A-Z]/', $methodName, $matches, PREG_OFFSET_CAPTURE);

			if (isset($matches[0][1])) {
				$plugin = substr($methodName, 0, $matches[0][1]);
				$name = lcfirst(substr($methodName, $matches[0][1]));

				if (array_key_exists($plugin, $plugins)) {

					$initializers[] = [
						'initializer' => $methodName,
						'plugin' => $plugin,
						'name' => $name,
					];
				}
			}
		}

		return $initializers;
	}

}
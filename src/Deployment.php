<?php

namespace JustDeploy;

use ReflectionClass;

use JustDeploy\Plugins\Local\Plugin as LocalPlugin;
use JustDeploy\Plugins\FTP\Plugin as FTPPlugin;
use JustDeploy\Plugins\SSH\Plugin as SSHPlugin;
use JustDeploy\Plugins\Git\Plugin as GitPlugin;
use JustDeploy\Plugins\Atomic\Plugin as AtomicPlugin;
use JustDeploy\Plugins\Transfer\Plugin as TransferPlugin;

class Deployment {

	public $defaultPlugins = [
		'local' => LocalPlugin::class,
		'ftp' => FTPPlugin::class,
		'ssh' => SSHPlugin::class,
		'sftp' => SSHPlugin::class, // Alias
		'git' => GitPlugin::class,
		'atomicSymlinkDeployment' => AtomicPlugin::class,
		'transfer' => TransferPlugin::class,
	];

	public $plugins = [];

	protected $attributes = [];

	protected function getPlugins()
	{
		return array_merge($this->defaultPlugins, $this->plugins);
	}

	public function getAttribute($attribute, $default = null)
	{
		if (array_key_exists($attribute, $this->attributes)) {

			return $this->attributes[$attribute];
		}

		if (method_exists($this, $attribute)) {

			return $this->attributes[$attribute] = call_user_func([$this, $attribute]);
		}

		$plugins = $this->getPlugins();
		if (array_key_exists($attribute, $plugins)) {

			$pluginClassName = $plugins[$attribute];
			$plugin = new $pluginClassName($this);

			$this->setAttribute($attribute, $plugin);

			return $plugin;
		}

		return $default;
	}

	public function setAttribute($attribute, $value)
	{
		$this->attributes[$attribute] = $value;

		return $value;
	}

	public function __get($attribute)
	{
		return $this->getAttribute($attribute);
	}

	public function __set($attribute, $value)
	{
		return $this->setAttribute($attribute, $value);
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

	protected function getCallableMethods()
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

}
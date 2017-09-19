<?php

namespace JustDeploy\Plugins;

use RuntimeException;
use JustDeploy\Deployment;

abstract class AbstractPlugin {

	protected $deployment;

	protected $options;

	protected $memoizedValues = [];

	public function __construct(Deployment $deployment, array $options = [])
	{
		$this->deployment = $deployment;
		$this->options = array_merge($this->getDefaultOptions(), $options);
	}

	public function getDefaultOptions()
	{
		return [];
	}

	public function setup(array $options = [])
	{
		return new static(
			$this->deployment,
			array_merge($this->options, $options)
		);
	}

	public function getOptions()
	{
		return $this->options;
	}

	public function getOption($name, $default = null)
	{
		if (array_key_exists($name, $this->options)) {
			return $this->options[$name];
		} else {
			return $value;
		}
	}

	public function getAttribute($attribute, $default = null)
	{
		$getterName = 'get'. $attribute;

		if (method_exists($this, $getterName)) {
			return call_user_func([$this, $getterName]);
		}


		$memoizedGetterName = 'memoize'. $attribute;

		if (method_exists($this, $memoizedGetterName)) {

			if (!array_key_exists($attribute, $this->memoizedValues)) {
				$this->memoizedValues[$attribute] = call_user_func([$this, $memoizedGetterName]);
			}

			return $this->memoizedValues[$attribute];
		}

		return $this->getOption($attribute, $default);
	}

	public function setAttribute($attribute, $value)
	{
		$setterName = 'set'. $attribute;

		if (method_exists($this, $setterName)) {
			return call_user_func([$this, $setterName], $value);
		}

		throw RuntimeException("Can't set property '$attribute'.");
	}

	public function __get($attribute)
	{
		return $this->getAttribute($attribute);
	}

	public function __set($attribute, $value)
	{
		return $this->setAttribute($attribute, $value);
	}

}
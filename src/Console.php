<?php

namespace JustDeploy;

use InvalidArgumentException;

class Console {

	protected $globalArguments = [
		'--directory',
		'--quiet',
		'--help',
	];
	
	public function execute(array $cmdArguments)
	{
		$arguments = $this->parseArguments($cmdArguments);

		$commandName = $arguments['command'] ?: 'help';
		$commandClassName = $this->generateCommandClassName($commandName);

		if (class_exists($commandClassName)) {

			$command = new $commandClassName($arguments);
			$command->execute();

		} else {
			throw new InvalidArgumentException("Invalid command name: $commandName");
		}

	}

	protected function generateCommandClassName($commandName)
	{
		$commandClass = $this->camelCase($commandName);

		return "JustDeploy\\Commands\\{$commandClass}Command";
	}

	protected function camelCase($string)
	{
		$normalizedString = trim(preg_replace('/\s+/u', ' ', preg_replace('/[^A-Za-z0-9]/u', ' ', $string)));

		return str_replace(' ', '', mb_convert_case($normalizedString, MB_CASE_TITLE));
	}

	protected function parseArguments(array $cmdArguments)
	{
		$arguments = [
			'command' => null,
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

				if (in_array($name, $this->globalArguments)) {
					$arguments[$name] = $value;
				} else {
					$arguments['arguments'][$name] = $value;
				}

			} else if ($index === 0) { // Is command name?

				$arguments['command'] = $argument;

			} else { // Must be an ordinary anonymous argument

				$arguments['arguments'][] = $argument;
			}
		}

		return $arguments;
	}

}
<?php

namespace JustDeploy\Plugins\Local;

use JustDeploy\ShellException;

class Shell {

	protected $cwd;

	public function __construct(array $options)
	{
		$this->cwd = realpath($options['cwd']);
	}

	public function chdir($cwd)
	{
		$this->cwd = realpath($cwd);

		return $this;
	}

	public function getcwd()
	{
		return $this->cwd;
	}

	public function escape($argument)
	{
		return escapeshellarg($argument);
	}

	public function exec($command)
	{
		if (!$this->cwd) {
			throw new ShellException("Invalid working directory: {$this->cwd}");
		}

		$process = proc_open($command, [
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		], $pipes, $this->cwd);

		if (!$process) {
			$lastError = error_get_last();

			throw new ShellException("Command `$command` exited with error message: \"". $lastError['message'] ."\"");
		}

		$stdout = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);

		$exitStatus = proc_close($process);

		if ($exitStatus) {
			throw new ShellException("Command `$command` exited with status code: $exitStatus. Message: \"". trim($stderr) ."\"", $exitStatus);
		}

		return [
			'stdout' => $stdout,
			'stderr' => $stderr,
		];
	}

}
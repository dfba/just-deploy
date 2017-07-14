<?php

namespace GitSshDeploy;

use Exception;

class LocalShell {

	protected $workingDirectory = null;

	public function setWorkingDirectory($workingDirectory)
	{
		$this->workingDirectory = $workingDirectory;

		return $this;
	}

	public function escapeArgument($argument)
	{
		return escapeshellarg($argument);
	}

	public function execute($command)
	{
		$cwd = realpath($this->workingDirectory ?: '.');

		$proc = proc_open($command, [
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		], $pipes, $cwd);

		if (!$proc) {
			throw new Exception("Command `$command` exited with error message: \"". error_get_last()['message'] ."\"");
		}

		$stdout = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);

		$exitStatus = proc_close($proc);

		if (strlen($stderr)) {
			throw new Exception("Command `$command` exited with error message: \"". trim($stderr) ."\" (status code: $exitStatus)");
		}

		if ($exitStatus) {
			throw new Exception("Command `$command` exited with status code: $exitStatus");
		}

		return $stdout;
	}

}
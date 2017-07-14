<?php

namespace GitSshDeploy;

use phpseclib\Crypt\RSA;
use phpseclib\Net\SSH2;

use InvalidArgumentException;
use Exception;

class RemoteShell {

	protected $ssh = null;
	protected $workingDirectory = null;

	public function connect($host, $port, $user, $password)
	{
		$this->ssh = new SSH2($host, $port);

		try {

			if (!$this->ssh->login($user, $password)) {
				throw new Exception("Failed to log in $user@$host:$port");
			}

			$this->ssh->enableQuietMode();

		} catch(Exception $e) {
			$this->ssh = null;
			throw $e;
		}
	}

	public function setWorkingDirectory($workingDirectory)
	{
		$this->workingDirectory = $workingDirectory;

		return $this;
	}

	public function escapeArgument($argument)
	{
		return "'". str_replace("'", "\\'", $argument) ."'";
	}

	public function execute($command)
	{
		$exec = '';
		if ($this->workingDirectory !== null) {
			$exec = 'cd '. $this->escapeArgument($this->workingDirectory) ."\n";
		}

		$exec .= $command;

		$result = $this->ssh->exec($exec);

		$stdError = $this->ssh->getStdError();
		$exitStatus = $this->ssh->getExitStatus();

		if (strlen($stdError)) {
			throw new Exception("SSH Command `$command` exited with error message: \"". trim($stdError) ."\" (status code: $exitStatus)");
		}

		if ($exitStatus) {
			throw new Exception("SSH command exited with status: $exitStatus");
		}

		return $result;
	}

}
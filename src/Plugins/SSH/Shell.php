<?php

namespace JustDeploy\Plugins\SSH;

use Exception;
use phpseclib\Net\SSH2;
use JustDeploy\ShellException;


class Shell {

	protected $ssh = null;

	protected $host;
	protected $port;
	protected $username;
	protected $password;
	protected $cwd;

	public function __construct(array $options)
	{
		$this->host = $options['host'];
		$this->port = @$options['port'] ?: 22;
		$this->username = @$options['username'];
		$this->password = @$options['password'];
		$this->cwd = @$options['cwd'] ?: '/';
	}

	protected function getSSHConnection()
	{
		if (!$this->ssh) {

			$this->ssh = new SSH2($this->host, $this->port);

			try {

				if (!$this->ssh->login($this->username, $this->password)) {
					throw new Exception("Failed to log in {$this->username}@{$this->host}:{$this->port}");
				}

				$this->ssh->enableQuietMode();

			} catch(Exception $e) {
				$this->ssh = null;
				throw $e;
			}
		}

		return $this->ssh;
	}

	public function chdir($cwd)
	{
		$this->cwd = $cwd;

		return $this;
	}

	public function getcwd()
	{
		return $this->cwd;
	}

	public function escape($argument)
	{
		return "'". str_replace("'", "\\'", $argument) ."'";
	}

	public function exec($command)
	{
		$ssh = $this->getSSHConnection();

		$exec = '';
		if ($this->cwd !== null) {
			$exec = 'cd '. $this->escape($this->cwd) ."\n";
		}

		$exec .= $command;

		$stdout = $ssh->exec($exec);

		$stderr = $ssh->getStdError();
		$exitStatus = $ssh->getExitStatus();

		if ($exitStatus) {
			throw new ShellException("SSH Command `$command` exited with status code: $exitStatus. Message: \"". trim($stderr) ."\"", $exitStatus);
		}

		return [
			'stdout' => $stdout,
			'stderr' => $stderr,
		];
	}

}
<?php

namespace JustDeploy;

interface ShellInterface {

	public function resolvePath($path);

	public function escape($argument);

	public function exec($command, array $arguments=[], $cwd='/');

}
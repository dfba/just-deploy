<?php

class JustDeployProduction extends JustDeploy {

	public function remote()
	{
		return parent::remote()->setup([
			'host' => 'example.com',
			'port' => 22,
			'username' => 'my-user-name',
			'password' => 'my-password',
			'path' => '/home/my-user-name/domains/example.com',
		]);
	}

}
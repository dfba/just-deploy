<?php

class Deployment extends JustDeploy\Deployment {

	public function project()
	{
		return $this->local->setup([
			/**
			 * Path to the local project files. If the path does not start with a slash, 
			 * it will be relative to the containing directory of this `just-deploy.php` file.
			 */
			'path' => './',
		]);
	}

	public function remote()
	{
		return $this->ssh->setup([

		]);
	}

	public function ftpRemote()
	{
		return $this->ftp->setup([

		]);
	}

	public function projectTransfer()
	{
		return $this->transfer->setup([
			'source' => $this->project,
			'destination' => $this->remote,
			'filterPatterns' => [
				'/node_modules/',
				'/\.git',
				'^/public_html/source/',
				'^/public_html/uploads/',
				'^/vendor/',
				'^/storage/',
				'^/\.env',
			],
			'filterInverse' => true,
			'recursive' => true,
			'overwriteFiles' => false,
			'overwriteEmptyDirectories' => true,
			'overwriteNonEmptyDirectories' => false,
			'logProgress' => true,
		]);
	}

	public function sharedTransfer()
	{
		return $this->transfer->setup([
			'source' => $this->project,
			'destination' => $this->remote,
			'filterPatterns' => [
				'/\.git',
			],
			'filterInverse' => true,
			'recursive' => true,
			'overwriteFiles' => true,
			'overwriteEmptyDirectories' => true,
			'overwriteNonEmptyDirectories' => true,
			'logProgress' => true,
		]);
	}

	public function atomicDeployment()
	{
		return $this->atomicSymlinkDeployment->setup([
			/**
			 * Where should the deployment be done?
			 */
			'destination' => $this->remote,

			/**
			 * Name of the directory that holds all the deployments. Current, old and failed.
			 */
			'directory' => 'deployments',

			/**
			 * Name of the symbolic link that points to the current deployment.
			 */
			'currentLink' => 'current',

			/**
			 * After a deployment has completed successfully, a small file is placed inside the deployment folder. 
			 * The next time a deployment is performed, it will use that file to determine whether the 
			 * previous deployment was successful or a failure.
			 */
			'successFile' => '.deployment-successful',

			/**
			 * Everytime a deployment is performed, a fresh copy of your code is set up.
			 * Those copies (a.k.a. deployments) are not removed, unless you tell it to do so.
			 * 'keepSuccessful' specifies how many old successful deployments should be preserved after each deployment.
			 * This can be a number, 
			 * or `false` (remove all old successful deployments),
			 * or `true` (don't remove any deployments).
			 *
			 * For example: `'keepSuccessful' => 3` means there will be up to 4 deployment folders at the destination:
			 * one for the live deployment and three old deployments.
			 */
			'keepSuccessful' => 3,

			/**
			 * Same as 'keepSuccessful', except that it specifies how many failed deployments to keep.
			 */
			'keepFailed' => false,

			/**
			 * Print progress information to the console?
			 */
			'logProgress' => true,
		]);
	}


	public function taskDefault()
	{
		$this->atomicDeployment->deploy(
			[$this, 'prepareDeployment'],
			[$this, 'afterDeployment']
		);
	}


	public function prepareDeployment($path)
	{
		// Copy all the files to the deployment folder:
		$this->projectTransfer->setup([
			'destinationPath' => $path,
		])->transfer();

		// Set up the folders that are shared between deployments:
		$this->makeSharedDirectory($path, '/storage');
		$this->makeSharedDirectory($path, '/public_html/uploads');

		// Run `composer install`:
		$this->runComposerInstall($path);
	}

	public function afterDeployment($path)
	{
		// Redirect Apache to the new deployment's public_html folder:
		$this->atomicDeployment->atomicSymlink('public_html', $path .'/public_html');
	}

	public function makeSharedDirectory($deploymentPath, $sharedPath)
	{
		$stateContainer = 'shared';

		$this->sharedTransfer->setup([
			'sourcePath' => $sharedPath,
			'destinationPath' => $stateContainer .'/'. $sharedPath,
		])->transfer();

		$this->atomicDeployment->atomicSymlink(
			$deploymentPath .'/'. $sharedPath,
			$stateContainer .'/'. $sharedPath
		);
	}

	public function runComposerInstall($path)
	{
		echo "Running composer install...\n";

		$result = $this->remote->exec(
			'php composer.phar',
			[
				'install',
				'--optimize-autoloader',
				'--no-interaction',
				'--no-ansi',
				'--no-progress',
				'--no-suggest',
			],
			$path
		);

		echo "\n";
		echo $result['stdout'];
		echo $result['stderr'];
		echo "\n";
	}

}
<?php

class JustDeploy extends JustDeploy\Deployment {

	public function project()
	{
		return $this->local->setup([
			/**
			 * Path to the local project files. If the path does not start with a slash, 
			 * it will be relative to the containing directory of this file.
			 */
			'path' => './',
		]);
	}

	public function remote()
	{
		return $this->ssh->setup([
			/**
			 * IP address or domain name of the SSH server.
			 */
			'host' => '<Enter host here>',

			/**
			 * SSH port number. (22 is the default port)
			 */
			'port' => 22,

			/**
			 * Username.
			 */
			'username' => '<Enter username here>',

			/**
			 * Password.
			 */
			'password' => '<Enter password here>',

			/**
			 * Absolute path to remote directory. For example: /home/user/domain.com/
			 */
			'path' => '<Enter path here>',
		]);
	}

	public function projectTransfer()
	{
		return $this->transfer->setup([
			/**
			 * Which files should be transferred?
			 */
			'source' => $this->project,

			/**
			 * To where should the files be transferred?
			 */
			'destination' => $this->remote,

			/**
			 * Filter the files to be transferred.
			 *
			 * If 'filterInverse' is false, *only* the files matching 'filterPatterns' will be transferred.
			 * If 'filterInverse' is true, the files matching 'filterPatterns' will *not* be transferred.
			 *
			 * 'filterPatterns' is an array of regular expressions (PCRE). Forward slashes are automatically escaped.
			 * Paths always start with a slash. Directory paths always end with a slash.
			 */
			'filterInverse' => true,
			'filterPatterns' => [
				'^/public_html/uploads/', // Shared folder.
				'^/storage/', // Shared folder.
				'^/vendor/', // Composer's vendor folder will get recreated using `composer install`.
				'^/\.env', // Don't upload any .env file. Only the correct one will get uploaded later.
				'/node_modules/', // Node.js dependencies.
				'/\.git', // Repository data.
			],

			/**
			 * Transfer subdirectories and their contents?
			 */
			'recursive' => true,

			/**
			 * Overwrite any existing files at the destination?
			 */
			'overwriteFiles' => false,

			/**
			 * Continue transferring if the destination folder exists but is empty?
			 */
			'overwriteEmptyDirectories' => true,

			/**
			 * Continue transferring if the destination folder exists and is not empty?
			 */
			'overwriteNonEmptyDirectories' => false,

			/**
			 * Print progress information to the console?
			 */
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
			 * 
			 * 'keepSuccessful' specifies how many old successful deployments should be preserved after each deployment.
			 * This can be a number (keep n deployments),
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


	/**
	 * This is the entrypoint of your deployment.
	 * Do whatever you've got to do here.
	 */
	public function taskDefault()
	{
		// Perform an atomic deployment:
		$this->atomicDeployment->deploy(
			[$this, 'prepareDeployment'],
			[$this, 'afterDeployment']
		);
	}


	/**
	 * Called after the deployment folder is created, but before the symlinks are switched.
	 * Use this function to set up your application.
	 * 
	 * @param $path Path to the newly created deployment folder.
	 */
	public function prepareDeployment($path)
	{
		// Copy all the files to the deployment folder:
		$this->projectTransfer->setup([
			'destinationPath' => $path,
		])->transfer();

		// Set up the folders that are shared between deployments:
		// If you add a folder here, you should probably also add it to the ignore list of `projectTransfer`.
		$this->makeSharedDirectory($path, '/storage');
		$this->makeSharedDirectory($path, '/public_html/uploads');

		// Run `composer install`:
		$this->runComposerInstall($path);
	}


	/**
	 * Called immediately after the symlinks are switched.
	 * 
	 * @param $path Path to the now active deployment folder.
	 */
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
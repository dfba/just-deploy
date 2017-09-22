<?php

class Deployment extends JustDeploy\Deployment {

	public $shared = [
		'/storage',
		'/.env',
		'/public_html/uploads',
	];

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

	

	public function projectRepository()
	{
		return $this->git->setup([
			'shell' => $this->source->getShell(),
		]);
	}

	public function taskDefault()
	{
		$this->atomicDeployment->deploy(function($path) {

			$this->projectTransfer->setup([
				'destinationPath' => $path,
			])->transfer();

		});

		// $this->taskVerifyCleanGit();

		// $branch = 'tags/production';

		// echo "Switching to branch '$branch' ...\n";
		// $previousBranch = $this->taskGitCheckout($branch);
		
		// try {

			// $this->symlinkDeployment->deploy(
			// 	// Tasks to run after the deployment folder has been created, but before it has been published:
			// 	function($deploymentPath) {

			// 		$this->projectTransfer->setup([
			// 			'toPath' => $deploymentPath,
			// 		])->transfer();

			// 		// $this->taskUpload($deploymentPath);
			// 		// $this->taskComposerInstall($deploymentPath);

			// 	},

			// 	// Tasks to run after the deployment has been published, but before cleanup is run:
			// 	function($deploymentPath) {

			// 		// $this->symlinkDeployment->atomicSymlink('/public_html', $deploymentPath . '/public_html');

			// 	}
			// );

		// } finally {

		// 	echo "Switching back to previous branch '$previousBranch' ...\n";
		// 	$this->taskGitCheckout($previousBranch);

		// }
	}

	protected function getCurrentGitRef()
	{
		$branch = $this->sourceGit->getCurrentBranch();

		if (!is_null($branch)) {
			return $branch;
		}

		// Repository is probably in detached head state.
		$commit = $this->sourceGit->getCurrentCommit();

		if (!is_null($commit)) {
			return $commit;
		}

		throw new Exception("Could not determine current branch/tag/commit.");
	}

	public function taskGitCheckout($branch)
	{
		$previousRef = $this->getCurrentGitRef();

		$this->sourceGit->checkout($branch);

		return $previousRef;
	}

	public function taskVerifyCleanGit()
	{
		$status = $this->sourceGit->status();
		
		if (strlen($status)) {
			echo "Local git repository is not clean. The following files have changed:\n";
			echo $status;
			echo "\n\nABORTING NOW!\n";
			
			throw new Exception("Uncommitted changes present.");
		}
	}

	public function taskComposerInstall($deploymentPath)
	{
		$shell = $this->remote->getShell();

		$cwd = $shell->getcwd();
		$shell->chdir($cwd .'/'. $deploymentPath);

		echo "Running composer install...\n";

		try {
			$result = $shell->exec("php composer.phar install --optimize-autoloader --no-interaction --no-ansi --no-progress --no-suggest");
		} finally {
			$shell->chdir($cwd);
		}

		echo "\n";
		echo $result['stdout'];
		echo $result['stderr'];
		echo "\n";
	}

}
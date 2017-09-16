<?php

namespace JustDeploy\Flysystem;

use JustDeploy\FilesystemInterface;
use League\Flysystem\Plugin\AbstractPlugin;

class NewTransferPlugin extends AbstractPlugin {

	public function getMethod()
	{
		return 'newTransfer';
	}

	public function handle(
		$fromPath = '',
		$toFilesystem = null,
		$toPath = ''
	) {
		if (is_string($toFilesystem)) {
			return $this->handle($fromPath, $this->filesystem, $toFilesystem);
		}

		$transfer = new Transfer();
		$transfer->from($this->filesystem, $fromPath);
		$transfer->to($toFilesystem, $toPath);

		return $transfer;
	}

}
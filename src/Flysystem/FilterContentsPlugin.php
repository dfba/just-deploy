<?php

namespace JustDeploy\Flysystem;

use League\Flysystem\Plugin\AbstractPlugin;

class FilterContentsPlugin extends AbstractPlugin {

	public function getMethod()
	{
		return 'filterContents';
	}

	public function handle(
		$filePatterns = [],
		$path = '',
		$recursive = false,
		$inverse = false
	) {

		$filePatterns = (array) $filePatterns;

		if (!count($filePatterns)) {
			if ($inverse) {
				return $this->filesystem->listContents($path, $recursive);
			} else {
				return [];
			}
		}

		$pregPatterns = $this->fileToPregPatterns($filePatterns);

		return $this->_listContentsFiltered(
			$pregPatterns,
			$path,
			$recursive,
			!$inverse
		);
	}

	protected function _listContentsFiltered(
		$pregPatterns,
		$path,
		$recursive,
		$include
	) {

		$filteredFiles = [];
		$unfilteredFiles = $this->filesystem->listContents($path);

		foreach ($unfilteredFiles as $file) {

			$matchesPatterns = $this->matchesPatterns($file, $pregPatterns);
			if ($include === $matchesPatterns) {

				$filteredFiles[] = $file;

				if ($recursive && $file['type'] === 'dir') {
					$subdirectoryFiles = $this->_listContentsFiltered(
						$pregPatterns,
						$file['path'],
						$recursive,
						$include
					);

					$filteredFiles = array_merge($filteredFiles, $subdirectoryFiles);
				}
			}
		}

		return $filteredFiles;
	}

	protected function matchesPatterns($file, $patterns)
	{
		$subject = '/'. $file['path'];

		if ($file['type'] === 'dir') {
			$subject .= '/';
		}

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $subject)) {
				return true;
			}
		}

		return false;
	}

	protected function fileToPregPatterns(array $filePatterns)
	{
		$pregPatterns = [];

		foreach ($filePatterns as $filePattern) {
			$pregPatterns[] = '/'. str_replace('/', '\/', $filePattern) .'/';
		}

		return $pregPatterns;
	}

}
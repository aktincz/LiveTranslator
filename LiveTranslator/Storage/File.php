<?php

namespace LiveTranslator\Storage;

use LiveTranslator\TranslatorException;

class File implements \LiveTranslator\ITranslatorStorage
{
	/** @var string */
	protected $storageDir;

	/** @var resource[] */
	protected $handlers = array();

	/** @var array */
	private $newTranslations = array();

	/** @var array */
	private $metaData = array();


	/**
	 * @param string $storageDir
	 * @throws TranslatorException
	 */
	public function __construct($storageDir)
	{
		$this->storageDir = realpath($storageDir);

		if (FALSE === $this->storageDir) {
			throw new TranslatorException("Directory $storageDir was not found.");
		}
	}


	/**
	 * @param string $original
	 * @param string $lang
	 * @param int $variant
	 * @param string $namespace
	 * @return string|null
	 */
	public function getTranslation($original, $lang, $variant = 0, $namespace = NULL)
	{
		$changed = $this->tryGetChanged($original, $lang, $variant, $namespace);
		if (FALSE !== $changed) {
			return $changed;
		}

		$handler = $this->getFileHandler($lang, $namespace);
		$initPos = ftell($handler);
		for ($i = 0; $i < 2; ++$i) {
			$pos = ftell($handler);
			if ($i == 1 && $pos >= $initPos) {
				break;
			}
			while ($translation = fgets($handler)) {
				if (!empty($prepend)) {
					$translation = $prepend . $translation;
					$prepend = NULL;
				}
				if (substr($translation, -4) !== "\";}\n") {
					$prepend = $translation;
					continue;
				}

				$translation = unserialize($translation);
				if ($original === $translation[0]) {
					while (!isset($translation[$variant +1])) {
						--$variant;
						if ($variant < 0) {
							return NULL;
						}
					}
					return $translation[$variant +1];
				}
			}
			rewind($handler);
		}
		return NULL;
	}


	/**
	 * @param string $lang
	 * @param string $namespace
	 * @return array
	 */
	public function getAllTranslations($lang, $namespace = NULL)
	{
		$handler = $this->getFileHandler($lang, $namespace);
		rewind($handler);
		$translations = array();

		while ($translation = fgets($handler)) {
			if (!empty($prepend)) {
				$translation = $prepend . $translation;
				$prepend = NULL;
			}
			if (substr($translation, -4) !== "\";}\n") {
				$prepend = $translation;
				continue;
			}

			$translation = unserialize($translation);
			$translations[array_shift($translation)] = $translation;
		}
		return $translations;
	}


	/**
	 * @param string $original
	 * @param string $translated
	 * @param string $lang
	 * @param int $variant
	 * @param string $namespace
	 * @return void
	 */
	public function setTranslation($original, $translated, $lang, $variant = 0, $namespace = NULL)
	{
		$this->saveTranslation($original, $translated, $lang, $namespace, $variant);
	}


	/**
	 * @param string $original
	 * @param string $lang
	 * @param string $namespace
	 * @return void
	 */
	public function removeTranslation($original, $lang, $namespace = NULL)
	{
		$this->saveTranslation($original, FALSE, $lang, $namespace);
	}


	public function __destruct()
	{
		if ($this->newTranslations) {
			foreach ($this->metaData as $meta => $originals) {
				list($lang, $namespace) = unserialize($meta);
				$filePath = $this->storageDir . DIRECTORY_SEPARATOR . $this->getFilename($lang, $namespace);
				$data = file_exists($filePath) ? file($filePath) : array();

				foreach ($data as $i => &$row) {
					if (!empty($prepend)) {
						$row = $prepend . $row;
						$prepend = NULL;
					}
					if (substr($row, -4) !== "\";}\n") {
						$prepend = $row;
						continue;
					}

					$translation = unserialize($row);
					$index = array_search($translation[0], $originals);

					if (FALSE !== $index) {
						unset($originals[$index]);
						if (FALSE === $this->newTranslations[$translation[0]]) {
							unset($data[$i]);
						} else {
							$translation = $this->newTranslations[$translation[0]] + $translation;
							ksort($translation);
							$row = serialize($translation) . "\n";
						}
					}
				}

				foreach ($originals as $original) {
					$new = array($original) + $this->newTranslations[$original];
					ksort($new);
					$data[] = serialize($new) . "\n";
				}

				$handler = $this->getFileHandler($lang, $namespace);
				ftruncate($handler, 0);
				rewind($handler);
				$content = implode('', $data);
				fwrite($handler, $content, strlen($content));
			}
		}

		foreach ($this->handlers as $handler) {
			fclose($handler);
		}
	}


	/**
	 * @param string $lang
	 * @param string $namespace
	 * @return resource
	 */
	protected function getFileHandler($lang, $namespace = NULL)
	{
		$file = $this->getFilename($lang, $namespace);

		if (isset($this->handlers[$file])) {
			return $this->handlers[$file];
		}

		$filePath = $this->storageDir . DIRECTORY_SEPARATOR . $file;

		if (file_exists($filePath)) {
			$handler = fopen($filePath, 'r+');
		} else {
			$handler = fopen($filePath, 'w+');;
		}

		return $this->handlers[$file] = $handler;
	}


	/**
	 * @param string $lang
	 * @param string $namespace
	 * @return string
	 */
	protected function getFilename($lang, $namespace = NULL)
	{
		return $lang . ($namespace === NULL ? '' : ".$namespace");
	}


	private function saveTranslation($original, $new, $lang, $namespace, $variant = NULL)
	{
		if (FALSE !== $new) {
			if (isset($this->newTranslations[$original])) {
				$this->newTranslations[$original][$variant +1] = $new;
				return;
			}
			$new = array($variant +1 => $new);
		}

		$meta = serialize(array($lang, $namespace));
		if (!isset($this->metaData[$meta])) {
			$this->metaData[$meta] = array();
		}

		$this->metaData[$meta][] = $original;
		$this->newTranslations[$original] = $new;
	}


	/**
	 * Returns string translation when change found,
	 * returns NULL when translation removed,
	 * returns FALSE when change not found.
	 */
	private function tryGetChanged($original, $lang, $variant, $namespace)
	{
		if (array_key_exists($original, $this->newTranslations)) {
			$meta = serialize(array($lang, $namespace));

			if (!isset($this->metaData[$meta]) || !in_array($original, $this->metaData[$meta])) {
				return FALSE;
			}
			if (FALSE === $this->newTranslations[$original]) {
				return NULL;
			}

			$seekVariant = $variant +1;
			while (!isset($this->newTranslations[$original][$seekVariant])) {
				--$seekVariant;
				if (!$seekVariant) {
					break;
				}
			}

			if ($seekVariant) {
				return $this->newTranslations[$original][$seekVariant];
			}
		}
		return FALSE;
	}
}

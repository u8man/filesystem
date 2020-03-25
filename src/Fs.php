<?php

namespace Manuylenko\Filesystem;

use Exception;
use FilesystemIterator;

class Fs
{
    /**
     * Считывает содержимое из файла.
     * @param string $path
     * @param bool $lock
     * @return string
     */
    public function read($path, $lock = false) {
        if (! $this->exists($path)) {
            throw new Exception(sprintf('Файл "%s" не существует', $path));
        }
        if (! $this->isFile($path)) {
            throw new Exception(sprintf('Указанный путь "%s" не является файлом', $path));
        }
        $content = '';
        if ($lock) {
            if ($f = fopen($path, 'rb')) {
                try {
                    if (flock($f, LOCK_SH)) {
                        clearstatcache(true, $path);
                        $content = fread($f, $this->size($path) ?: 1);
                        flock($f, LOCK_UN);
                    }
                } finally {
                    fclose($f);
                }
            }
        } else {
            $content = file_get_contents($path);
        }
        if ($content) {
            return $content;
        }
        return '';
    }

    /**
     * Записывает содержимое в файл.
     * @param string $path
     * @param string $content
     * @param bool $lock
     * @return bool
     */
    public function write($path, $content, $lock = false) {
        return file_put_contents($path, $content, ($lock ? LOCK_EX : 0)) !== false;
    }

	/**
	 * Записывает содержимое в конец файла.
	 * @param string $path
	 * @param string $content
	 * @param bool $lock
	 * @return bool
	 */
	public function append($path, $content, $lock = false) {
		return file_put_contents($path, $content, (($lock ? LOCK_EX : 0 ) | FILE_APPEND)) !== false;
	}

    /**
     * Записывает содержимое в начало файла.
     * @param string $path
     * @param string $content
     * @param bool $lock
     * @return bool
     */
    public function prepend($path, $content, $lock = false) {
        if ($this->exists($path)) {
            return $this->write($path, $content.$this->read($path, $lock), $lock);
        }
        return $this->write($path, $content, $lock);
    }

    /**
     * Получает содержимое каталога в виде массива имен каталогов/файлов.
     * @param string $path
     * @param bool $onlyFiles
     * @param string|string[] $extensions
     * @return array
     */
    public function listDir($path, $onlyFiles = false, $extensions = null) {
        if (! $this->isDir($path)) {
            throw new Exception(sprintf('Указанный путь "%s" не является каталогом', $path));
        }
        if (! is_null($extensions)) {
            $extensions = is_array($extensions) ? $extensions : array($extensions);
        }
        $files = [];
        foreach (new FilesystemIterator($path) as $item) {
            if ($onlyFiles) {
                if ($item->isDir()) {
                    continue;
                }
                if (! is_null($extensions) && ! in_array($item->getExtension(), $extensions)) {
                    continue;
                }
            }
            $files[] = $item->getFileName();
        }
        return $files;
    }

	/**
	 * Проверяет существование указанного файла или каталога.
	 * @param string $path
	 * @return bool
	 */
    public function exists($path) {
        return file_exists($path);
    }

	/**
	 * Определяет, является ли указанный путь файлом.
	 * @param string $path
	 * @return bool
	 */
    public function isFile($path) {
        return is_file($path);
    }

	/**
	 * Определяет, является ли файл символической ссылкой.
	 * @param string $path
	 * @return bool
	 */
    public function isLink($path) {
        return is_link($path);
    }

	/**
	 * Определяет, является ли указанный путь каталогом.
	 * @param string $path
	 * @return bool
	 */
    public function isDir($path) {
        return is_dir($path);
    }

	/**
	 * Определяет, существование файла и доступен ли он для чтения.
	 * @param string $path
	 * @return bool
	 */
    public function isReadable($path) {
        return is_readable($path);
    }

    /**
	 * Определяет, доступен ли файл для записи.
	 * @param string $path
	 * @return bool
	 */
    public function isWritable($path) {
        return is_writable($path);
    }

	/**
	 * Получает размер файла или каталога.
	 * @param string $path
	 * @return int
	 */
    public function size($path) {
    	$size = 0;
    	if ($this->isDir($path)) {
			foreach ($this->listDir($path) as $file) {
				$size += $this->size($path.'/'.$file);
			}
		} elseif ($this->isFile($path)) {
			$size = filesize($path);
		}
        return $size;
    }

	/**
	 * Удаляет файл или каталог.
	 * @param string $path
	 * @param bool $recursive
	 * @return void
	 */
    public function remove($path, $recursive = true) {
    	if (! $this->exists($path)) {
    		throw new Exception(sprintf('Указанный путь "%s" не найден.', $path));
		}
        if ($this->isDir($path)) {
        	$files = $this->listDir($path);
        	if (! $recursive && ! empty($files)) {
				throw new Exception(sprintf('Не возможно удалить не пустой каталог "%s".', $path));
			}
			foreach (array_reverse($files) as $file) {
				$this->remove($path.'/'.$file);
			}
        	if (! @rmdir($path)) {
				throw new Exception(sprintf('Ошибка удаления каталога "%s".', $path));
			}
        } else {
			if (! @unlink($path)) {
				throw new Exception(sprintf('Ошибка удаления файла "%s".', $path));
			}
		}
    }

	/**
	 * Создает каталог с указанными правами доступа.
	 * @param string $path
	 * @param int $mode
	 * @param bool $recursive
	 * @return void
	 */
    public function mkdir($path, $mode = 0777, $recursive = true) {
        if ($this->isDir($path)) {
        	return;
		}
		if (! @mkdir($path, $mode, $recursive)) {
			throw new Exception(sprintf('Не удалось создать каталог "%s".', $path));
		}
    }
}


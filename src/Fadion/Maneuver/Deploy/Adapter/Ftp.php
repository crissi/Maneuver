<?php namespace Fadion\Maneuver\Deploy\Adapter;

use League\Flysystem\Adapter\Ftp as BaseFtp;
use League\Flysystem\AdapterInterface;

/**
 * Workaround until the day when issue with FTP Server on Windows will be fixed and merged to the repository.
 * @link https://github.com/thephpleague/flysystem/issues/451
 *
 * @package Fadion\Maneuver\Deploy\Adapter
 */
class Ftp extends BaseFtp
{
    protected function normalizeObject($item, $base)
    {
        if (stripos(ftp_systype($this->connection), 'windows') !== false) {
            return $this->normalizeWindowsObject($item, $base);
        }

        return parent::normalizeObject($item, $base);
    }

    protected function normalizeWindowsObject($item, $base)
    {
        $type = "file";

        if (strpos($item, '<DIR>') !== false) {
            $type = "dir";

            str_replace('<DIR>', '', $item);
        }

        $item = preg_replace('#\s+#', ' ', trim($item));

        list ($date, $time, $size, $name) = explode(' ', $item, 4);
        list($month, $day, $year) = explode('-', $date);

        $timestamp = strtotime($month . ' ' . $day . ' ' . $time);
        $path = empty($base) ? $name : $base . $this->separator . $name;

        if ($type === 'dir') {
            return compact('type', 'path', 'timestamp');
        }

        $visibility = AdapterInterface::VISIBILITY_PUBLIC;
        $size = (int) $size;

        return compact('type', 'path', 'visibility', 'size', 'timestamp');
    }
}
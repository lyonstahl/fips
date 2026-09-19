<?php

declare(strict_types=1);

namespace LyonStahl\Fips\Tools;

use RuntimeException;
use ZipArchive;

final class GazetteerArchive
{
    public function extract(string $archive, string $entry): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'fips-archive-');
        if ($temporary === false || file_put_contents($temporary, $archive) === false) {
            throw new RuntimeException('Unable to create a temporary Census archive.');
        }

        $zip = new ZipArchive();
        $opened = false;
        try {
            if ($zip->open($temporary) !== true) {
                throw new RuntimeException('Unable to open a Census Gazetteer archive.');
            }
            $opened = true;

            $contents = $zip->getFromName($entry);
            if ($contents === false) {
                throw new RuntimeException(sprintf('The Census archive does not contain %s.', $entry));
            }

            return $contents;
        } finally {
            if ($opened) {
                $zip->close();
            }
            @unlink($temporary);
        }
    }
}

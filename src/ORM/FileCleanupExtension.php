<?php

namespace Dynamic\Salsify\ORM;

use Dynamic\Salsify\Task\ImportTask;
use SilverStripe\Assets\File;
use SilverStripe\Core\Extension;

/**
 * Class FileCleanupExtension
 * @package Dynamic\Salsify\ORM
 */
class FileCleanupExtension extends Extension
{

    /**
     *
     */
    public function onAfterMap()
    {
        $count = 0;

        /** @var File $file */
        foreach ($this->getFiles() as $file) {
            if($file->findOwners()->count() == 0) {
                $file->delete();
                $count++;
            }
        }

        ImportTask::output("Removed {$count} unused images imported from salsify.");
    }

    /**
     * @return \Generator
     */
    function getFiles()
    {
        foreach (File::get()->filter('SalsifyID:not', [null, '']) as $file) {
            yield $file;
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOldFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'restream:check-old-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $basePath = base_path('public/channels');

        $files = $this->processDirectories($basePath);

        $this->removeOldFiles($files);

        $this->info('Old files removed successfully.');
    }

    private function processDirectories($basePath, $pattern = '.ts') {
        $filePaths = [];

        // get all directories and files inside the current directory
        $items = glob($basePath . '/*');

        // loop through all items (directories and files)
        foreach ($items as $item) {
            // check if the item is a file and matches the specified pattern
            if (is_file($item) && preg_match('/' . preg_quote($pattern, '/') . '$/', $item)) {
                $filePaths[] = $item; // add the full path to the array
            } elseif (is_dir($item)) {
                // recursively call the function for subdirectories
                $subDirectoryFilePaths = $this->processDirectories($item, $pattern);

                // merge subdirectory file paths with the current directory file paths
                $filePaths = array_merge($filePaths, $subDirectoryFilePaths);
            }
        }

        return $filePaths;
    }

    private function removeOldFiles($files, $maxAgeMinutes = 40)
    {
        $now = time();

        foreach ($files as $filePath) {
            // get the last modification time of the file
            $fileMtime = filemtime($filePath);


            // check if the file is older than the specified maximum age
            if (($now - $fileMtime) > ($maxAgeMinutes * 60)) {
                // remove the file
                unlink($filePath);

                Log::info('File removed: ' . $filePath);

                // Optionally, you can print a message indicating the file was removed
                echo "Removed file: $filePath\n";
            }
        }
    }
}

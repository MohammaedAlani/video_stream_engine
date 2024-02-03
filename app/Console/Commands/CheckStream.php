<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;



class CheckStream extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'restream:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will check if the stream is online or not';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $basePath = base_path('public/channels');

        $files = $this->processDirectories($basePath);

        foreach ($files as $file) {
            // open the file and get the process id
            $process = file_get_contents($file);
            $process = json_decode($process, true);

            // process id
            $pid = $process['process_id'];
            // command
            $command = $process['command'];

            // check if the process is still running
            $isRunning = $this->isProcessRunning($pid);

            if (!$isRunning) {
                // process is not running, so we need to restart it
                $process = Process::fromShellCommandline($command);
                $process->setTimeout(900); // Set the timeout to null (no timeout)
                $process->start();

                usleep(100000); // 100 milliseconds

                $processId = $process->getPid();

                // save the process id to a file $path

                file_put_contents($file, json_encode([
                    'process_id' => $processId,
                    'command' => $command,
                ], JSON_PRETTY_PRINT));
            }
        }
    }

    private function processDirectories($basePath, $pattern = '_process_id.json') {
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

    private function getFilesWithPattern($directory, $pattern = '_process_id.json') {
        $matchingFiles = [];

        // get all files in the directory
        $files = glob($directory . '/*');

        // loop through all files
        foreach ($files as $file) {
            // check if the file matches the specified pattern
            if (is_file($file) && preg_match('/' . preg_quote($pattern, '/') . '$/', $file)) {
                $matchingFiles[] = $file;
            }
        }

        return $matchingFiles;
    }

    private function isProcessRunning($pid)
    {
        if (!is_numeric($pid) || $pid <= 0) {
            return false; // Invalid or non-positive process ID
        }
    
        // Use ps command to check if the process is running
        exec("ps -p $pid", $output, $returnCode);
    
        // Check if the ps command was successful and the output contains the process information
        return ($returnCode === 0 && count($output) > 1);
    }

    private function removeLastPartOfUrl($url)
    {
        $pathInfo = pathinfo($url);
        return $pathInfo['dirname'];
    }
}

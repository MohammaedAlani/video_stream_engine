<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\StreamChannel;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class RestreamHLS extends Command
{
    protected $signature = 'restream:hls';
    protected $description = 'Restream HLS feed';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $channels = Channel::all();
        $basePath = base_path('public/channels');

        // Stop existing FFMPEG processes outside the loop
//        $this->stopExistingProcesses();

        foreach ($channels as $channel) {
            $channelUrl = $channel->stream_url;
            // Create Guzzle client and set headers
            $client = new Client();
            $headers = [
                'Origin' => 'http://web.mytvplus.net',
                'Referer' => 'http://web.mytvplus.net/',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'Accept-Encoding' => 'gzip, deflate',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept' => '*/*',
            ];

            // Make the request
            $response = $client->request('GET', $channelUrl, [
                'headers' => $headers,
            ]);


            $baseUrl = $this->removeLastPartOfUrl($channelUrl);
            $allData = $response->getBody()->getContents();
            $allChannels = $this->getChannels($allData);

            // Define $outputPath here, before using it in the loop
            $outputPath = $this->getOutputPathFromUrlAdptive($channelUrl, $basePath);
            $newAdaptiveUrl = config('app.url') . "/" . $this->getPathAfterPublic($outputPath);
            $newAdaptiveUrl = $newAdaptiveUrl . "/" . $this->getFileNameFromUrl($channelUrl);

            $insertChannel = StreamChannel::updateOrCreate(
                ['stream_url' => $newAdaptiveUrl],
                ['name' => $this->extractNameFromUrl($channelUrl), 'parent_id' => 0, 'stream_url' => $newAdaptiveUrl]
            );

            // Save the content to a file in the same directory as $outputPath
            $this->saveM3U8ToFile($allData, $outputPath, $this->getFileNameFromUrl($channelUrl));

            foreach ($allChannels as $allChannel) {
                $streamUrlTarget = $baseUrl . '/' . $allChannel;

                $outputPath = $this->getOutputPathFromUrl($streamUrlTarget, $basePath);
                $newUrl = config('app.url') . "/" . $this->getPathAfterPublic($outputPath);
                $channelName = $this->extractNameFromUrl($streamUrlTarget);

                $ffmpegCommand = "ffmpeg -i " . escapeshellarg($streamUrlTarget) . " -c copy -f hls -hls_time 10 -hls_list_size 6 -hls_flags delete_segments " . escapeshellarg($outputPath) . " > /dev/null 2>&1";

                // No need to check for existing FFMPEG processes within the loop

                $process = Process::fromShellCommandline($ffmpegCommand);
                // Set the timeout to 15 minutes (900 seconds)
                $process->setTimeout(900);
                $process->start();

                usleep(100000); // 100 milliseconds

                $processId = $process->getPid()+1;


                $processIdFilePath = $this->removeLastPartOfUrl($outputPath) . '/' . $this->getFileNameFromUrl($outputPath) . '_process_id.json';
                file_put_contents($processIdFilePath, json_encode([
                    'process_id' => $processId,
                    'command' => $ffmpegCommand,
                    'stream_url' => $streamUrlTarget,
                ], JSON_PRETTY_PRINT));

                StreamChannel::updateOrCreate(
                    ['stream_url' => $newUrl],
                    ['name' => $channelName, 'parent_id' => $insertChannel->id, 'stream_url' => $newUrl]
                );

                try {
                    $this->info('The HLS stream is being restreamed.' . $allChannel);
                } catch (ProcessFailedException $exception) {
                    $this->error('The HLS stream restreaming process failed: ' . $exception->getMessage());
                }
            }
        }
    }

    private function getPathAfterPublic($path)
    {
        $keyword = 'public';
        $position = strpos($path, $keyword);

        if ($position === false) {
            // The keyword 'public' was not found in the path
            return null;
        }

        // Add the length of the keyword to the starting position and 1 more for the trailing slash
        $startPosition = $position + strlen($keyword) + 1;

        return substr($path, $startPosition);
    }


    private function stopExistingProcesses()
    {
        try {
            $existingProcessesCommand = "pkill -f 'ffmpeg'";
            $existingProcesses = Process::fromShellCommandline($existingProcessesCommand);
            $existingProcesses->run();

            // Check if the process terminated with a non-zero exit code
            if (!$existingProcesses->isSuccessful()) {
                $this->error('The process failed: ' . $existingProcesses->getErrorOutput());
            }
        } catch (Exception $exception) {
            $this->error('An error occurred: ' . $exception->getMessage());
        }
    }


    private function getChannels($channelUrl)
    {
        $pattern = '/\b\S+\.m3u8\b/m';
        preg_match_all($pattern, $channelUrl, $matches);
        return $matches[0];
    }

    private function removeLastPartOfUrl($url)
    {
        $pathInfo = pathinfo($url);
        return $pathInfo['dirname'];
    }

    private function getFileNameFromUrl($url)
    {
        $pathInfo = pathinfo($url);
        return $pathInfo['basename'];
    }

    private function getOutputPathFromUrlAdptive($url, $basePath)
    {
        // Remove the filename from the URL path
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'];
        // remove from the last / to the end
        $path = substr($path, 0, strrpos($path, '/'));
        $directoryPath = rtrim($basePath, '/') . $path;

        // Ensure the base directory exists
        if (!file_exists($directoryPath)) {
            mkdir($directoryPath, 0777, true); // Create the directory if it doesn't exist
        }

        return $directoryPath;
    }

    private function getOutputPathFromUrl($url, $basePath)
    {
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'];

        // Ensure the base directory exists
        $fullPath = rtrim($basePath, '/') . $path;
        $directory = pathinfo($fullPath, PATHINFO_DIRNAME);

        if (!file_exists($directory)) {
            mkdir($directory, 0777, true); // Create the directory if it doesn't exist
        }

        return $fullPath;
    }

    private function saveM3U8ToFile($content, $outputPath, $filename)
    {
        $filePath = $outputPath . '/' . $filename;

        // Try to save the content to the file
        if (file_put_contents($filePath, $content) === false) {
            $this->error('Failed to save M3U8 file: ' . $filePath);
        } else {
            $this->info('M3U8 file saved successfully: ' . $filePath);
        }
    }

    private function extractNameFromUrl($url)
    {
        $parsedUrl = parse_url($url, PHP_URL_PATH);
        $pathParts = explode('/', $parsedUrl);
        $filename = end($pathParts);

        // Remove the file extension
        $name = pathinfo($filename, PATHINFO_FILENAME);

        return $name;
    }
}

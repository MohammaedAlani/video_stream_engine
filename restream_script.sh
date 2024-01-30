#!/bin/bash

# write log to /var/log
echo "Restream HLS started at $(date)" >> /var/log/restream_hls.log

# Change to the directory where your Laravel project is located
cd /var/www/html/video_stream_engine

# Kill all ffmpeg processes
sudo killall ffmpeg

# Run php artisan restream:hls and wait for it to complete
php artisan restream:hls

# The script will exit after the php artisan restream:hls command is completed

# Add log to /var/log
echo "Restream HLS completed at $(date)" >> /var/log/restream_hls.log

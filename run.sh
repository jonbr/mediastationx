#!/usr/bin/env bash

# kill all running instances of http-server
if pgrep http-server; then pkill http-server; fi

echo "Starting the http-server\n"
http-server ~/Videos --cors&

echo "Crawling through your media folders and files...\n"
php content.php

# copy menu.json to correct location
cp menu.json ~/Videos/msx

echo "Media Server running..."
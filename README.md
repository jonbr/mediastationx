# Media Station X - Server Side code
This is the server side code for a PHP server for generating JSON for displaying them in your Smart TV through Media Station X application.

Setup
=====
In third party file PTN.php line 54 minor modification is needed for parsing to work correctly.
    "array('season' => '(s?([0-9]{1,2}))'),"
To reduce warning messages comment out line 101 in third party file ISO639.php
    "//array('', '', '', 'lld', 'Ladin', 'ladin, lingua ladina'),"

to start up http-server and application run
    ./run.is

Roadmap
========
* Add better support for fast-forwarding when playing video files.
* Resume option; to continue from where you left of last time you whatched a particular video file.
* Re-scan of media folders, either by a dedicated action or restarting the application.

** Need beter parser for .srt file names.

** Fix MacOs app, full screen not working properly.

<?php

require 'config.php';

$limit = 10;    // top 10 folders

// scan $downloads_folder for folders (only one level depth), ignore . and ..
$folders = array();

$dir = new DirectoryIterator($downloads_folder);
foreach ($dir as $fileinfo) {
    if ($fileinfo->isDir() && !$fileinfo->isDot()) {
        $folder = $fileinfo->getFilename();
        $subdir = new DirectoryIterator($downloads_folder . '/' . $folder);
        $count = 0;
        foreach ($subdir as $subfileinfo) {
            if ($subfileinfo->isDir() && !$subfileinfo->isDot()) {
                $count++;
            }
        }
        $folders[$folder] = $count;
    }
}

// sort folders by number of subsubfolders
arsort($folders);

// print results
$folders = array_slice($folders, 0, $limit);
echo "Top $limit folders:" . PHP_EOL;
foreach ($folders as $folder => $count) {
    echo "\t$folder: $count" . PHP_EOL;
}
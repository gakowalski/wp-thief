<?php

require 'config.php';

if (php_sapi_name() == 'cli') {
    if (count($argv) == 2) {
        $url = $argv[1];
    } else {
        echo "Enter URL of the directory listing: ";
        $url = trim(fgets(STDIN));
    }
} else {
    $url = $_GET['url'];
}

if (is_dir($url)) {
    // recursively scan the directory for ZIP files
    $zip_files = array();
    $directory_iterator = new RecursiveDirectoryIterator($url);
    $iterator = new RecursiveIteratorIterator($directory_iterator);
    foreach ($iterator as $file) {
        if (preg_match('/\.zip$/', $file)) {
            $zip_files[] = $file->getPathname();
        }
    }
    
    $direct_zip_links = $zip_files;
    goto process_direct_zip_links;

} else if (is_file($url)) {
    // if url is not a directory, assume it is a ZIP file
    $zip_files = array(realpath($url));

    $direct_zip_links = $zip_files;
    goto process_direct_zip_links;
}

// if url contains query parameters, remove them
if (preg_match('/\?/', $url)) {
    $url = preg_replace('/\?.*/', '', $url);
}

// if url lacks the trailing slash, add it
if (!preg_match('/\/$/', $url)) {
    $url .= '/';
}

// retrieve base server URL from the URL of the directory listing
$base_url = preg_replace('/^(https?:\/\/[^\/]+)\/.*/', '$1', $url);

$stream_context = [
    'http' => [
        'method' => 'GET', 
        'header' => 'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0'
    ],
    'ssl' => [
        'cafile' => '/path/to/bundle/cacert.pem',
        'verify_peer'=> false,
        'verify_peer_name'=> false,
    ],
];

// get the HTML content of the directory listing
$html = file_get_contents($url, false, stream_context_create($stream_context));

if ($html === false) {
    echo "Error: Could not retrieve the HTML content of the directory listing.\n";
    exit(1);
}

// retrieve all links from the HTML content
$links = array();
preg_match_all('/<a href="([^"]+)">/', $html, $links);

// filter links for ZIP files
$zip_links = array();
foreach ($links[1] as $link) {
    if (preg_match('/\.zip$/', $link)) {
        $zip_links[] = $link;
    }
}

const URL_DIRECT = 1;
const URL_RELATIVE_TO_BASE = 2;
const URL_RELATIVE_TO_LISTING = 3;

// take the first ZIP link and check if it is direct, relative to the base URL or relative to the listing URL
$zip_link = $zip_links[0];
if (preg_match('/^http/', $zip_link)) {
    $zip_link_type = URL_DIRECT;
} elseif (preg_match('/^\//', $zip_link)) {
    $zip_link_type = URL_RELATIVE_TO_BASE;
} else {
    $zip_link_type = URL_RELATIVE_TO_LISTING;
}

// echo the base URL with proper comment
echo "Base URL: $base_url\n";
echo "Listing URL: $url\n";
echo "Number of all links: " . count($links[1]) . "\n";
echo "Number of ZIP links: " . count($zip_links) . "\n";

$direct_zip_links = [];

// make direct links to the ZIP files
if ($zip_link_type == URL_DIRECT) {
    echo "ZIP links are already direct.\n";
    $direct_zip_links = $zip_links;
} else if ($zip_link_type == URL_RELATIVE_TO_BASE) {
    echo "ZIP links are relative to the base URL.\n";
    echo "Direct ZIP links:\n";
    foreach ($zip_links as $zip_link) {
        $direct_zip_links[] = $base_url . $zip_link; 
        echo $base_url . $zip_link . PHP_EOL;
    }
} else if ($zip_link_type == URL_RELATIVE_TO_LISTING) {
    echo "ZIP links are relative to the listing URL.\n";
    echo "Direct ZIP links:\n";
    foreach ($zip_links as $zip_link) {
        $direct_zip_links[] = $url . $zip_link;
        echo $url . $zip_link . PHP_EOL;
    }
}

process_direct_zip_links:

require 'unzip.php';

$saved_files_array = [];
// download the ZIP files if they are not already downloaded
echo "Downloading ZIP files:\n";

foreach ($direct_zip_links as $zip_link) {
    $zip_file_name = basename($zip_link);
    $file_contents = file_get_contents($zip_link, false, stream_context_create($stream_context));

    if ($file_contents === false) {
        echo "Error, can't download: " . $zip_link . PHP_EOL;
        continue;
    }

    $uncompressed_data = unzip_in_memory($file_contents, function($filename) {
        $exclude_paths = [
            'vendor/',
        ];

        // filter out file pahts that contain entries from array $exclude_paths
        foreach ($exclude_paths as $exclude_path) {
            if (strpos($filename, $exclude_path) !== false) {
                echo "Skipping: " . $filename . PHP_EOL;
                return false;
            }
        }

        // filter out all files except PHP files
        echo "Checking: " . $filename . PHP_EOL;
        $result = preg_match('/\.php$/', $filename);
        if (!$result) {
            echo "Skipping: " . $filename . PHP_EOL;
        }
        return $result;
    });

    $main_php_file = null;
    $main_php_count = 0;

    foreach ($uncompressed_data as $file) {
        //echo $file['is_dir'] ? 'DIR: ' : 'FILE: ';
        //echo $file['filename'] . PHP_EOL;

        // if filename ends with php and cointains the string "Plugin Name:" in first 200 characters, save it to $main_php_file
        //if (preg_match('/\.php$/', $file['filename'])) {
            if (preg_match('/Plugin Name:/', substr($file['content'], 0, $search_window_length))) {
                $main_php_file = $file['content'];
                $main_php_count++;
            }
        //}
    }

    if ($main_php_file && $main_php_count == 1) {
        // extract package name from the main PHP file
        $package = preg_match('/@[pP]ackage\s(.*)/', $main_php_file, $matches) ? trim($matches[1]) : null;

        // extract text domain from the main PHP file
        $text_domain = preg_match('/Text Domain:\s(.*)/', $main_php_file, $matches) ? trim($matches[1]) : null;

        // extract plugin name from the main PHP file
        $plugin_name = preg_match('/Plugin Name:\s(.*)/', $main_php_file, $matches) ? trim($matches[1]) : null;
        $plugin_name = strtr($plugin_name, [
            '/' => '_',
            '\\' => '_',
            ':' => '_',
            '*' => '_',
            '?' => '_',
            '"' => '_',
            '<' => '_',
            '>' => '_',
            '|' => '_',
        ]);

        // extract plugin version from the main PHP file
        $plugin_version = preg_match('/Version: (.*)/', $main_php_file, $matches) ? trim($matches[1]) : null;
        $plugin_version = strtr($plugin_version, [
            '/' => '_',
            '\\' => '_',
            ':' => '_',
            '*' => '_',
            '?' => '_',
            '"' => '_',
            '<' => '_',
            '>' => '_',
            '|' => '_',
        ]);

        $standarized_name = $text_domain ?? $package ?? $plugin_name;

        // create the folder for the plugin
        $plugin_folder = $downloads_folder . '/' . $standarized_name  . '/' . $plugin_version;
        if (!file_exists($plugin_folder)) {
            if (false === mkdir($plugin_folder, 0777, true)) {
                echo "Error creating folder: $plugin_folder" . PHP_EOL;
                continue;
            }
        } else {
            echo "Plugin folder already exists: $plugin_folder" . PHP_EOL;
            continue;
        }

        $save_to_filename = $plugin_folder . '/' . $zip_file_name;
    } else {
        $date_now = date('Y-m-d_H-i-s');
        if ($main_php_count > 1) {
            echo "Error, more than one main PHP file in: " . $zip_link . PHP_EOL;
            $save_to_filename = $bundles_folder . '/' . $date_now . '-' . $zip_file_name;
        } else {
            echo "Error, can't find main PHP file in: " . $zip_link . PHP_EOL;
            $save_to_filename = $unknowns_folder . '/' . $date_now . '-' . $zip_file_name;
        }
    }

    file_put_contents($save_to_filename, $file_contents);
    echo "Saved to: " . $save_to_filename . PHP_EOL;
    $saved_files_array[] = $save_to_filename;
}

echo "Done!" . PHP_EOL;

// pretty print list of saved files
echo PHP_EOL;
echo "Saved files:\n";

$file_count = count($saved_files_array);

foreach ($saved_files_array as $saved_file) {
    echo "\t" . $saved_file . PHP_EOL;
}

echo "Total files: $file_count" . PHP_EOL;
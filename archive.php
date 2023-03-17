<?php

$downloads_folder = 'downloads'; // folder where the ZIP files will be downloaded

if (php_sapi_name() == 'cli') {
    echo "Enter URL of the directory listing: ";
    $url = trim(fgets(STDIN));
} else {
    $url = $_GET['url'];
}

// if urls lacks the trailing slash, add it
if (!preg_match('/\/$/', $url)) {
    $url .= '/';
}

// retrieve base server URL from the URL of the directory listing
$base_url = preg_replace('/^(https?:\/\/[^\/]+)\/.*/', '$1', $url);

// get the HTML content of the directory listing
$html = file_get_contents($url);

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

function unzip_in_memory($data, $filter_function = null) {
    $sectors = explode("\x50\x4b\x01\x02", $data);
    array_pop($sectors);
    $files = explode("\x50\x4b\x03\x04", implode("\x50\x4b\x01\x02", $sectors));
    array_shift($files);

    $result = array();
    foreach($files as $file) {
        $header = unpack("vversion/vflag/vmethod/vmodification_time/vmodification_date/Vcrc/Vcompressed_size/Vuncompressed_size/vfilename_length/vextrafield_length", $file);
        $header['filename'] = substr($file, 26, $header['filename_length']);
        if ($filter_function) {
            if (!$filter_function($header['filename'])) {
                continue;
            }
        }
        // is it a directory?
        $header['is_dir'] = substr($header['filename'], -1) == '/' ? true : false;
        if ($header['is_dir']) {
            $content = null;
        } else {
            if ($header['method'] == 8) {
                $content = @gzinflate(substr($file, 26 + $header['filename_length'] + $header['extrafield_length'], $header['compressed_size']));
                if ($content === false) {
                    echo "Trying to skip additional header fields: " . $header['filename'] . PHP_EOL;
                    $content = gzinflate(substr($file, 26 + $header['filename_length'] + $header['extrafield_length'], -12));
                    if ($content === false) {
                        echo "Failure!" . PHP_EOL;
                    } else {
                        echo "Success!" . PHP_EOL;
                    }
                }
            } else {
                $content = substr($file, 26 + $header['filename_length'] + $header['extrafield_length'], $header['uncompressed_size']);
            }
        }

        if ($content === false) {
            echo "Error: " . $header['filename'] . PHP_EOL;
            echo "Method: " . $header['method'] . PHP_EOL;
        }

        array_push($result, [
            'filename' => $header['filename'],
            'is_dir' => $header['is_dir'],
            'content' => $content,
        ]);
    }
    
    return $result;
}

// download the ZIP files if they are not already downloaded
echo "Downloading ZIP files:\n";

foreach ($direct_zip_links as $zip_link) {
    $zip_file_name = basename($zip_link);
    $file_contents = file_get_contents($zip_link);

    if ($file_contents === false) {
        echo "Error, can't download: " . $zip_link . PHP_EOL;
        continue;
    }

    $uncompressed_data = unzip_in_memory($file_contents, function($filename) {
        // filter out all files except PHP files
        echo "Checking: " . $filename . PHP_EOL;
        $result = preg_match('/\.php$/', $filename);
        if (!$result) {
            echo "Skipping: " . $filename . PHP_EOL;
        }
        return $result;
    });
    $main_php_file = null;

    foreach ($uncompressed_data as $file) {
        //echo $file['is_dir'] ? 'DIR: ' : 'FILE: ';
        //echo $file['filename'] . PHP_EOL;

        // if filename ends with php and cointains the string "Plugin Name:" in first 200 characters, save it to $main_php_file
        //if (preg_match('/\.php$/', $file['filename'])) {
            if (preg_match('/Plugin Name:/', substr($file['content'], 0, 200))) {
                $main_php_file = $file['content'];
            }
        //}
    }

    if ($main_php_file) {
        // extract package name from the main PHP file
        $package = preg_match('/@[pP]ackage\s(.*)/', $main_php_file, $matches) ? trim($matches[1]) : null;

        // extract text domain from the main PHP file
        $text_domain = preg_match('/Text Domain:\s(.*)/', $main_php_file, $matches) ? trim($matches[1]) : null;

        // extract plugin name from the main PHP file
        $plugin_name = preg_match('/Plugin Name:\s(.*)/', $main_php_file, $matches) ? trim($matches[1]) : null;

        // extract plugin version from the main PHP file
        $plugin_version = preg_match('/Version: (.*)/', $main_php_file, $matches) ? trim($matches[1]) : null;

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

        file_put_contents($plugin_folder . '/' . $zip_file_name, $file_contents);
    }
}
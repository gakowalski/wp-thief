<?php 

/* 
 * Unzip in memory without writing to disk. 
 * 
 * @param string $data
 * @param callable $filter_function
 * @return array 
*/
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
<?php 

function sanitize_name($name) {
    if ($name) {
        $name = strtr($name, [
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
    }
    return $name;
}
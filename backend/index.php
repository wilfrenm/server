<?php

$minPhpVersion = '8.1';
works!!!
// If you update this, don't forget to update `spark`.
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $message = sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION
    );

    // header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo $message;

    exit(1);
}
?>
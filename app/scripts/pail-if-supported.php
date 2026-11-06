<?php

if (! function_exists('pcntl_fork')) {
    fwrite(STDOUT, "Skipping Laravel Pail: pcntl is not available on this PHP platform.\n");

    exit(0);
}

$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/../artisan').' pail --timeout=0';
passthru($command, $exitCode);

exit($exitCode);

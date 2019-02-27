<?php

if (!class_exists(\Archman\Whisper\Master::class)) {
    require __DIR__ . '/../vendor/autoload.php';
}

require_once __DIR__ . '/basic/Master.php';

$master = new Master();
$master->run();
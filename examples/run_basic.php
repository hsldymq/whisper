<?php

if (!class_exists(\Archman\Whisper\AbstractMaster::class)) {
    require __DIR__ . '/../vendor/autoload.php';
}

require_once __DIR__ . '/basic/AbstractMaster.php';

$master = new AbstractMaster();
$master->run();
<?php 

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload

#require __DIR__ . '/../src/TestRailExtension.php';
use TestRailExtension\TestRail;
TestRail::configure2();

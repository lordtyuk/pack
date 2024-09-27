<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . "loader.php";

$encoder = new Encoder(Config::test());
$encoder->decode();

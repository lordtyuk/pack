<?php

class Config
{
    public function __construct(
        public string $prefix,
        public string $filePath
    )
    {
    }

    public static function test(): self
    {
        return new self('test', __DIR__.'/../data/test');
    }

    public static function prod(): self
    {
        return new self('prod', __DIR__.'/../data/enwik9');
    }
}

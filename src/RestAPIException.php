<?php

namespace Chocofamily\Exception;

interface RestAPIException
{
    public function getDebug(): array;

    public function setDebug(array $debug = []);

    public function getData(): array;

    public function setData(array $data = []);

    public function getDebugAsString(): string;
}

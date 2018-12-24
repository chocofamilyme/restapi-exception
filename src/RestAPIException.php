<?php

namespace Chocofamily\Exception;

interface RestAPIException
{
    public function getDebug(): array;
    public function setDebug(array $debug = []);
}

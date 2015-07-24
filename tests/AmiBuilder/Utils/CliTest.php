<?php

namespace Io\Samk\Tests\AmiBuilder\Utils;

use Io\Samk\AmiBuilder\Utils\Cli;
use Io\Samk\Tests\AmiBuilder\BaseTestCase;
use Katzgrau\KLogger\Logger;
use Psr\Log\LogLevel;

class CliTest extends BaseTestCase
{

    function testProofOfLife()
    {
        $cli = new Cli(new Logger($this->testsLogPath, LogLevel::CRITICAL));
        $this->assertNotNull($cli);
    }

}
<?php

namespace Io\Samk\Tests\AmiBuilder\Utils;


use Io\Samk\AmiBuilder\Utils\Config;
use Io\Samk\Tests\AmiBuilder\BaseTestCase;

class ConfigTest extends BaseTestCase
{

    function testProofOfLife()
    {
        $cli = new Config("{$this->testFixturesPath}/config/empty.json");
        $this->assertNotNull($cli);
    }

}
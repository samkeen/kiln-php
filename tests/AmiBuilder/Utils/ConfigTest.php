<?php

namespace Io\Samk\Tests\AmiBuilder\Utils;


use Io\Samk\AmiBuilder\Utils\Config;
use Io\Samk\Tests\AmiBuilder\BaseTestCase;

class ConfigTest extends BaseTestCase
{

    function testProofOfLife()
    {
        $cli = new Config("{$this->testFixturesPath}/config/empty.yml");
        $this->assertNotNull($cli);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    function testInvalidYmlFilePathThrowsExpectedException()
    {
        $cli = new Config("{$this->testFixturesPath}/config/not-a-file.yml");
        $this->assertNotNull($cli);
    }

    /**
     * @expectedException Io\Samk\AmiBuilder\Utils\ConfigParseException
     */
    function testMalformedYmlThrowsExpectedException()
    {
        $cli = new Config("{$this->testFixturesPath}/config/malformed.yml");
        $this->assertNotNull($cli);
    }

}
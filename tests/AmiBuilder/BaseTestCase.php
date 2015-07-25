<?php

namespace Io\Samk\Tests\AmiBuilder;

use Katzgrau\KLogger\Logger;

class BaseTestCase extends \PHPUnit_Framework_TestCase
{

    protected $topDir = null;
    protected $testsLogPath = null;
    protected $testFixturesPath = null;
    protected $testLogger;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->topDir = realpath(__DIR__ . "/../..");
        $this->testsLogPath = "{$this->topDir}/logs/test.log";
        $this->testLogger = new Logger($this->testsLogPath);
        $this->testFixturesPath = "{$this->topDir}/tests/fixtures";
        parent::setUp();
    }


}
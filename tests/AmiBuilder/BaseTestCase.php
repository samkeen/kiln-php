<?php

namespace Io\Samk\Tests\AmiBuilder;

class BaseTestCase extends \PHPUnit_Framework_TestCase
{

    protected $topDir = null;
    protected $testsLogPath = null;
    protected $testFixturesPath = null;

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->topDir = realpath(__DIR__ . "/../..");
        $this->testsLogPath = "{$this->topDir}/logs/test.log";
        $this->testFixturesPath = "{$this->topDir}/tests/fixtures";
        parent::setUp();
    }


}
<?php

namespace Io\Samk\AmiBuilder\Utils;

class Config
{
    /**
     * @var array
     */
    protected $config;

    /**
     * Config constructor.
     * @param string $pathToConfigFile
     * @throws ConfigParseException
     */
    public function __construct($pathToConfigFile)
    {
        $pathToConfigFile = trim($pathToConfigFile);
        if (!file_exists($pathToConfigFile) || !is_readable($pathToConfigFile)) {
            throw new \InvalidArgumentException(
                "The config file at path: '{$pathToConfigFile}' was not found and/or is not readable");
        }
        try {
            $this->config = spyc_load_file($pathToConfigFile);
        } catch(\Exception $e) {
            throw new ConfigParseException("There was an error parsing the config file at path: '{$pathToConfigFile}'."
                . " Error Message: '{$e->getMessage()}'");
        }


    }

    /**
     * @param string $key
     * @param bool $exceptionOnMissing
     * @return mixed
     */
    public function get($key, $exceptionOnMissing = false)
    {
        $response = null;
        if (!$this->config[$key] && $exceptionOnMissing) {
            throw new \InvalidArgumentException("Key '{$key}' does not exist in config.");
        }

        return $this->config[$key];
    }

    /**
     * @param string $sectionKey
     * @param string $itemKey
     * @param mixed $value
     */
    public function setSectionValue($sectionKey, $itemKey, $value)
    {
        $this->config[$sectionKey][$itemKey] = $value;
    }

    public function dumpYamlTo($filePath)
    {
        file_put_contents($filePath, spyc_dump($this->config));
    }

}
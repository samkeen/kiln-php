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

    public function get($key, $exceptionOnMissing = true)
    {
        $response = null;
        if (!$this->config[$key] && $exceptionOnMissing) {
            throw new \InvalidArgumentException("Key '{$key}' does not exist in config.");
        }

        return $this->config[$key];
    }

}
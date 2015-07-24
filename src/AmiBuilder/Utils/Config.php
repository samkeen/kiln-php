<?php
/**
 * Created by PhpStorm.
 * User: sam
 * Date: 7/22/15
 * Time: 4:31 PM
 */

namespace Io\Samk\AmiBuilder\Utils;


class Config
{
    /**
     * @var array
     */
    protected $config;

    /**
     * Config constructor.
     */
    public function __construct($pathToConfigFile)
    {
        $pathToConfigFile = trim($pathToConfigFile);
        if (!file_exists($pathToConfigFile) || !is_readable($pathToConfigFile)) {
            throw new \InvalidArgumentException("The config file was not found and/or is not readable");
        }

        $config = json_decode(file_get_contents($pathToConfigFile), true);
        if (json_last_error()) {
            throw new \InvalidArgumentException("There was a JSON parse error on config.json: " . json_last_error_msg());
        }
        $this->config = $this->parseConfig($config);
    }

    public function get($key, $exceptionOnMissing = true)
    {
        $response = null;
        if (!$this->config[$key] && $exceptionOnMissing) {
            throw new \InvalidArgumentException("Key '{$key}' does not exist in config.");
        }

        return $this->config[$key];
    }

    protected function parseConfig($config)
    {
        $config = (array)$config;
        $this->removeCommentElements($config);

        return $config;
    }

    function removeCommentElements(&$array)
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $this->removeCommentElements($value);
            }
            if ($key == '#') {
                unset($array[$key]);
            }
        }
    }
}
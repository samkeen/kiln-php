<?php

/**
 * If command was `php run.php --config s3://myBucket/myObject`
 * then getCliArgValue($argv, '--config')
 * would return 's3://myBucket/myObject'
 *
 * @param $cliArgs
 * @param $argKey
 * @return string
 */
function getCliArgValue($cliArgs, $argKey)
{
    $cliArgValue = null;
    $configArgIndex = array_search($argKey, $cliArgs);
    if ($configArgIndex !== false) {
        $cliArgValue = isset($cliArgs[$configArgIndex + 1]) ? $cliArgs[$configArgIndex + 1] : null;
    }
    return trim($cliArgValue, "' ");
}

/**
 * Default path for config is './config/config.yml'
 * If the cli arg --config is given, that path is used.  It supports s3 bucket paths
 *
 *   ex: `php run.php --config s3:///kiln-config/testing/config.yml`
 *
 * @param string $configPath
 * @param \Psr\Log\LoggerInterface $logger
 * @return \Io\Samk\AmiBuilder\Utils\Config
 */
function getConfig($configPath, \Psr\Log\LoggerInterface $logger)
{
    try {
        return new \Io\Samk\AmiBuilder\Utils\Config($configPath);
    } catch (\Exception $e) {
        $logger->error(
            "There was a problem loading the config. Error: '{$e->getMessage()}'");
        shutDown("Problem Loading Config File.  Error: '{$e->getMessage()}'");
    }
}


/**
 * @param string $message
 * @param int $returnCode
 */
function shutDown($message, $returnCode = 1)
{
    $message = trim($message) . "\n";
    echo($message);
    exit($returnCode);
}

function getExecutionUuid()
{
    static $executionUuid;
    $executionUuid = $executionUuid ?: uniqid() . bin2hex(openssl_random_pseudo_bytes(2));
    return $executionUuid;
}

/**
 * @param string $executionUuid
 * @param string $logLevel One of Psr\Log\LogLevel
 * @return \Katzgrau\KLogger\Logger
 */
function getAppLogger($executionUuid, $logLevel = Psr\Log\LogLevel::DEBUG)
{
    return new Katzgrau\KLogger\Logger(
        __DIR__ . '/logs/app',
        $logLevel,
        [
            'extension' => 'log',
            'logFormat' => "[{date}] [{$executionUuid}] [{level}] {message}"
        ]
    );
}

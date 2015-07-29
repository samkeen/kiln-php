<?php

namespace Io\Samk\AmiBuilder\Utils;


use Psr\Log\LoggerInterface;

class Cli
{
    protected $logger;

    /**
     * Cli constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param $command
     * @param string $workingDirectory
     * @return array
     */
    public function execute($command, $workingDirectory = null)
    {
        $cwd = getcwd();
        $command = escapeshellcmd($command);
        $this->logger->info("Running Command: {$command}");
        if ($workingDirectory) {
            chdir($workingDirectory);
        }
        // redirect STDERR to STDOUT so it is captured in $output
        exec("{$command} 2>&1", $output, $returnCode);
        $this->logger->debug("Return code from command was: {$returnCode}");
        $this->logger->debug("Command output was: ", $output);
        if ($workingDirectory) {
            chdir($cwd);
        }

        return [$output, $returnCode];
    }

    /**
     * Goal is to build a command such as this:
     *
     *   packer build \
     *    -var 'aws_access_key=YOUR ACCESS KEY' \
     *    -var 'aws_secret_key=YOUR SECRET KEY' \
     *    packer-machine.json
     *
     * @param $executable
     * @param $pathToTemplate
     * @param null $awsAccessKey
     * @param null $awsSecretKey
     * @return array
     */
    public function runPackerBuild($executable, $pathToTemplate, $awsAccessKey = null, $awsSecretKey = null)
    {
        $awsVars = '';
        if ($awsAccessKey) {
            $awsVars = "-var 'aws_access_key={$awsAccessKey}' -var 'aws_secret_key={$awsSecretKey}'";
        }
        $command = "{$executable} build {$awsVars} {$pathToTemplate}";

        return $this->execute($command);
    }

}
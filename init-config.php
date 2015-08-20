<?php
date_default_timezone_set('UTC');
require 'vendor/autoload.php';
require './common.php';

$cliDefinitions = [
    'awsCliProfile' => [
        'required' => false,
        'description' => 'Value for optional AWS CLI Profile.',
        'errorMessage' => ""
    ],
    'templatesRepo' => [
        'required' => true,
        'description' => 'The checkout path the the Packer templates repo.  ex: "https://github.com/USERNAME/REPO.git"',
        'errorMessage' => "Value for required commandline parameter: '--templatesRepo' not found. "
        . "Typically this is the full path to your github repo."
    ],
    'awsRegion' => [
        'required' => true,
        'description' => 'The AWS region Kiln in deployed to.',
        'errorMessage' => "Value for required commandline parameter: '--awsRegion' not found."
    ]
];

function getUsage($cliDefinitions)
{
    $usage = "Usage:\n";
    foreach($cliDefinitions as $cliArgName => $argMetadata) {
        $required = $argMetadata['required'] ? 'Yes' : 'No';
        $usage .= "\targ: '--{$cliArgName}', required: {$required}, '{$argMetadata['description']}'\n";
    }
    return $usage;
}

function getCliArg($argName, $cliArgs, $cliDefinitions)
{
    $argValue = getCliArgValue($cliArgs, "--{$argName}");
    if($cliDefinitions[$argName]['required'] && empty($argValue)) {
        echo getUsage($cliDefinitions);
        shutDown($cliDefinitions[$argName]['errorMessage']);
    }

    return $argValue;
}

$executionUuid = getExecutionUuid();

$logger = getAppLogger($executionUuid);

$config = getConfig(__DIR__ . '/config/config.dist.yml', $logger);

$awsProfile = getCliArg('awsCliProfile', $argv, $cliDefinitions);
if(empty($awsProfile)) {
    $config->setSectionValue('awsConfig', 'profile', null);
}

$templatesRepo = getCliArg('templatesRepo', $argv, $cliDefinitions);
$config->setSectionValue('templates', 'templatesRepo', $templatesRepo);

$awsRegion = getCliArg('awsRegion', $argv, $cliDefinitions);
$config->setSectionValue('awsConfig', 'region', $awsRegion);

$config->dumpYamlTo(__DIR__ . "/config/config.yml");
<?php
date_default_timezone_set('UTC');
require 'vendor/autoload.php';
require './common.php';

$cliDefinitions = [
    'awsCliProfile' => [
        'required' => false,
        'configKeyPath' => 'awsConfig.profile',
        'description' => 'Value for optional AWS CLI Profile.',
        'errorMessage' => ""
    ],
    'templatesRepo' => [
        'required' => true,
        'configKeyPath' => 'templates.templatesRepo',
        'description' => 'The checkout path the the Packer templates repo.  ex: "https://github.com/USERNAME/REPO.git"',
        'errorMessage' => "Value for required commandline parameter: '--templatesRepo' not found. "
        . "Typically this is the full path to your github repo."
    ],
    'awsRegion' => [
        'required' => true,
        'configKeyPath' => 'awsConfig.region',
        'description' => 'The AWS region Kiln in deployed to.',
        'errorMessage' => "Value for required commandline parameter: '--awsRegion' not found."
    ],
    'auditTrailBucket' => [
        'required' => true,
        'configKeyPath' => 'executionDigest.bucketName',
        'description' => 'This is the bucket name that kiln audit trails are PUT to. (CF Template creates this bucket)',
        'errorMessage' => "Value for required commandline parameter: '--auditTrailBucket' not found."
    ],
    'buildRequestQueue' => [
        'required' => true,
        'configKeyPath' => 'sqsConfig.amiBuildRequestQueueUrl',
        'description' => 'This is the work queue that container builds are injected into',
        'errorMessage' => "Value for required commandline parameter: '--buildRequestQueue' not found."
    ]
];

/**
 * @param array $cliDefinitions
 * @return string
 */
function getUsage($cliDefinitions)
{
    $usage = "Usage:\n";
    foreach($cliDefinitions as $cliArgName => $argMetadata) {
        $required = $argMetadata['required'] ? 'Yes' : 'No';
        $usage .= "\targ: '--{$cliArgName}', required: {$required}, '{$argMetadata['description']}'\n";
    }
    return $usage;
}

/**
 * @param string $argName
 * @param array $cliArgs
 * @param array $cliDefinitions
 * @return string
 */
function getCliArg($argName, $cliArgs, $cliDefinitions)
{
    $argValue = getCliArgValue($cliArgs, "--{$argName}");
    if($cliDefinitions[$argName]['required'] && empty($argValue)) {
        echo getUsage($cliDefinitions);
        shutDown($cliDefinitions[$argName]['errorMessage']);
    }

    return $argValue;
}

/**
 * @param array $cliDefinitions
 * @param array $argv
 * @param \Io\Samk\AmiBuilder\Utils\Config $config
 */
function processCli($cliDefinitions, $argv, $config)
{
    foreach($cliDefinitions as $cliArgName => $cliArgDef) {
        list($sectionKey, $itemKey) = explode('.', $cliArgDef['configKeyPath']);
        $config->setSectionValue($sectionKey, $itemKey, getCliArg($cliArgName, $argv, $cliDefinitions));
    }
}

/**
 * @param string $awsRegion
 */
function createCloudWatchLogsConfigs($awsRegion) {
    $cloudWatchLogsConfDir = __DIR__ . '/config/cloudwatchLogs';
    $awsCliConfContent = file_get_contents("{$cloudWatchLogsConfDir}/awscli.dist.conf");
    $awsCliConfContent = str_replace('{{aws-region}}', $awsRegion,$awsCliConfContent);
    file_put_contents("{$cloudWatchLogsConfDir}/awscli.conf", $awsCliConfContent);
    // nothing to replace in this file
    copy("{$cloudWatchLogsConfDir}/awslogs.dist.conf", "{$cloudWatchLogsConfDir}/awslogs.conf");
}

$logger = getAppLogger(getExecutionUuid());
$config = getConfig(__DIR__ . '/config/config.dist.yml', $logger);
processCli($cliDefinitions, $argv, $config);
$config->dumpYamlTo(__DIR__ . "/config/config.yml");

// write the AWS CloudWatch Logs configs to ./config/cloudwatchLogs
// the deploy script will mv them to the proper place on the machine filesystem
createCloudWatchLogsConfigs(getCliArg('awsRegion', $argv, $cliDefinitions));

<?php
date_default_timezone_set('UTC');
require 'vendor/autoload.php';
require __DIR__ . '/common.php';

$executionStartTime = microtime(true);
$executionUuid = getExecutionUuid();

$executionMetrics = [
    "executionUuid" => $executionUuid,
    "startTime" => $executionStartTime,
    "amiBuildQueueName" => "",
    "jobMessage" => null,
    "jobBuildTemplate" => "",
    "jobBuildTemplateSha" => "",
    "jobReceived" => false,
    "createdAmiId" => "",
    "createdAmiRegion" => "",
    "endedInError" => false,
    "endTime" => ""
];

$logger = getAppLogger($executionUuid);

$aws = createAwsClient($argv, $logger);
/** @var \Aws\S3\S3Client $s3Client */
$s3Client = $aws->get('S3');
$s3Client->registerStreamWrapper();

$cliConfigArg = getCliArgValue($argv, '--config');
$configPath = $cliConfigArg ?: __DIR__ . "/config/config.yml";
$config = getConfig($configPath, $logger);

$appConfig = $config->get('appConfig');
// switch to log level in app config
$logger->setLogLevelThreshold($appConfig['logLevel']);
$sqsConfig = $config->get('sqsConfig');
$sqsQueueAttributes = $sqsConfig['queueAttributes'];

/** @var \Aws\Sqs\SqsClient $sqsClient */
$sqsClient = $aws->get('sqs');
$queueUrl = '';
$executionMetrics['amiBuildQueueName'] = $sqsConfig['amiBuildRequestQueueName'];
try {
    // create Queue if not exists
    $createQueueResponse = $sqsClient->createQueue([
        'QueueName' => $sqsConfig['amiBuildRequestQueueName'],
        'Attributes' => $sqsQueueAttributes
    ]);
    $queueUrl = $createQueueResponse->get('QueueUrl');
//    deleteQueue($sqsClient, $sqsConfig['amiBuildRequestQueueName'], $logger);

} catch (\Aws\Sqs\Exception\SqsException $e) {
    $logger->error("SqsException during create and/or access of SQS:  '{$e->getMessage()}''");
    shutDown("Error during create and/or access of SQS:  '{$e->getMessage()}''\n");
} catch (Exception $e) {
    $logger->error("Exception during create and/or access of SQS:  '{$e->getMessage()}''");
    shutDown("Error during create and/or access of SQS:  '{$e->getMessage()}''\n");
}

/**
 * if Work Found
 */
$work = $sqsClient->receiveMessage([
    "QueueUrl" => $queueUrl,
    "MaxNumberOfMessages" => 1,
    "MessageAttributeNames" => []
]);

$message = $work->get("Messages")[0];

/**
 * extract Template name and SHA
 */
$messagePayload = json_decode($message['Body'], true);
if (json_last_error()) {
    $message = "There was a JSON parse error on the work item Body in the SQS message. " . json_last_error_msg();
    $logger->error($message, $message['Body']);
    shutDown($message . "\n {$message['Body']}");
}

if (!$messagePayload) {
    $logger->info('No work found');
    shutDown("No Work found", 0);
}
$messagePayload = array_change_key_case($messagePayload, CASE_LOWER);
$template = ltrim(isset($messagePayload['templatename']) ? $messagePayload['templatename'] : null, ' /');
$templateSha = isset($messagePayload['sha']) ? $messagePayload['sha'] : null;

if (!$template || !$templateSha) {
    $message = "Not all required work message attributes found and/or had empty values. "
        . "templateName : '{$template}', sha: '{$templateSha}' ";
    $logger->error($message);
    shutDown($message);
}

$executionMetrics['jobMessage'] = $message;
// a lot of unneeded characters for digest message
unset($executionMetrics['jobMessage']['ReceiptHandle']);
$executionMetrics['jobBuildTemplate'] = $template;
$executionMetrics['jobBuildTemplateSha'] = $templateSha;
$logger->info("Found Work, request for template '{$template}' @ SHA '{$templateSha}'");
$executionMetrics['jobReceived'] = true;

$cli = new \Io\Samk\AmiBuilder\Utils\Cli(
    $logger,
    [
        "patterns" => ['/(aws_access_key)=([\w\+]+)(.*)/', '/(aws_secret_key)=([\w\+]+)(.*)/'],
        "replacements" => '$1=<$1>$3'
    ]
);

/**
 * update template checkout
 */
$templatesConfig = $config->get('templates');
$templatesRepo = $templatesConfig['templatesRepo'];
$templatesCheckoutPath = $templatesConfig['checkoutPath'];

if (!file_exists($templatesCheckoutPath)) {
    list($output, $returnCode) = $cli->execute("git clone {$templatesRepo} {$templatesCheckoutPath}");
}
$logger->info("checking out local packer templates repo '{$templatesCheckoutPath}' to SHA '{$templateSha}'");
list($output, $returnCode) = $cli->execute('git fetch --all', $templatesCheckoutPath);
list($output, $returnCode) = $cli->execute("git reset --hard origin/master", $templatesCheckoutPath);
list($output, $returnCode) = $cli->execute("git checkout {$templateSha}", $templatesCheckoutPath);

/**
 * Run packer and capture image region and name
 */
$pathToTemplate = trim("{$templatesCheckoutPath}/{$template}");
if (!file_exists($pathToTemplate)) {
    $logger->error("Path to build template not exists and/or not readable: '{$pathToTemplate}'");
    shutDown("Path to build template not exists and/or not readable: '{$pathToTemplate}'");
}
$packerConfig = $config->get('packer');

list($result, $returnCode) = $cli->runPackerBuild(
    $packerConfig['executablePath'],
    $pathToTemplate,
    isset($packerConfig['awsAccessKey']) ? $packerConfig['awsAccessKey'] : null,
    isset($packerConfig['awsSecretKey']) ? $packerConfig['awsSecretKey'] : null
);

if ($returnCode == 0) {
    list($region, $amiId) = array_map('trim', explode(":", $result[count($result) - 1]));
    $executionMetrics['createdAmiRegion'] = $region;
    $executionMetrics['createdAmiId'] = $amiId;
    $logger->info("Packer build succeeded: Region '{$region}', AMI id '{$amiId}'");
    deleteQueueMessage($sqsClient, $queueUrl, $message['ReceiptHandle'], $logger);
    $executionMetrics['endTime'] = microtime(true);
} else {
    $executionMetrics['endedInError'] = true;
    $logger->error("The packer build execution returned non zero result: '{$returnCode}'");
    $logger->error("Last 20 lines of output: ", array_slice($result, -20));
}

$date = date('Y-m-d\TH-i-s', $executionMetrics['startTime']);
$shaForPath = substr($templateSha, 0, 7);
$s3ObjectPath = "builds/{$template}/{$date}/{$shaForPath}.yml";
$logger->info("Writing results to S3: '{$s3ObjectPath}'");
writeExecutionDigest(
    $sqsClient,
    $s3ObjectPath,
    $config->get('executionDigest'),
    spyc_dump($executionMetrics),
    $logger
);

/**
 * Construct Job run manifest
 */

/**
 * Put manifest in S3
 * @param \Aws\Sqs\SqsClient $sqsClient
 * @param string $queueName
 */

/**
 * @param \Aws\Sqs\SqsClient $sqsClient
 * @param string $queueName
 * @param \Psr\Log\LoggerInterface $logger
 */
function deleteQueue(\Aws\Sqs\SqsClient $sqsClient, $queueName, \Psr\Log\LoggerInterface $logger)
{
    try {
        $queueUrlResponse = $sqsClient->getQueueUrl(['QueueName' => $queueName]);
        $sqsClient->deleteQueue(['QueueUrl' => $queueUrlResponse->get('QueueUrl')]);
    } catch (\Aws\Sqs\Exception\SqsException $e) {
        $logger->error("SqsException Deleting Queue '{$queueName}': {$e->getMessage()}");
        shutDown("ERROR Deleting Queue '{$queueName}': {$e->getMessage()}");
    } catch (Exception $e) {
        $logger->error("Exception Deleting Queue '{$queueName}': {$e->getMessage()}" . "\n");
        shutDown("ERROR Deleting Queue '{$queueName}': {$e->getMessage()}" . "\n");
    }
}

function deleteQueueMessage(
    \Aws\Sqs\SqsClient $sqsClient,
    $queueUrl,
    $messageReceiptHandle,
    \Psr\Log\LoggerInterface $logger
)
{
    try {
        $logger->info("deleting message on queue '{$queueUrl}' using message receipt: '{$messageReceiptHandle}'.");
        $result = $sqsClient->deleteMessage([
            'QueueUrl' => $queueUrl,
            'ReceiptHandle' => $messageReceiptHandle,
        ]);
    } catch (\Aws\Sqs\Exception\SqsException $e) {
        $logger->error("SqsException deleting message on queue '{$queueUrl}' using message receipt: "
            . "'{$messageReceiptHandle}'. Error: {$e->getMessage()}");
        shutDown("ERROR deleting message on queue '{$queueUrl}' using message receipt: '{$messageReceiptHandle}'."
            . "Error: {$e->getMessage()}");
    } catch (Exception $e) {
        $logger->error("Exception deleting message on queue '{$queueUrl}' using message receipt: "
            . "'{$messageReceiptHandle}'. Error: {$e->getMessage()}");
        shutDown("ERROR deleting message on queue '{$queueUrl}' using message receipt: '{$messageReceiptHandle}'."
            . "Error: {$e->getMessage()}");
    }
}

/**
 * @param \Aws\S3\S3Client $s3Client
 * @param string $keyName
 * @param array $digestConfig
 * @param string $digestContent
 * @param \Psr\Log\LoggerInterface $logger
 * @return mixed
 * @internal param \Aws\Common\Aws $aws
 */
function writeExecutionDigest(\Aws\S3\S3Client $s3Client, $keyName, $digestConfig, $digestContent, $logger)
{
    $bucketName = $digestConfig["bucketName"];
    try {
        $result = $s3Client->putObject(array(
            'Bucket' => $bucketName,
            'Key' => $keyName,
            'Body' => $digestContent
        ));
    } catch (\Aws\S3\Exception\S3Exception $e) {
        $logger->error("ERROR PUTing to bucket '{$bucketName}': {$e->getMessage()}" . "\n");
        shutDown("S3Exception PUTing to bucket '{$bucketName}': {$e->getMessage()}" . "\n");
    } catch (Exception $e) {
        $logger->error("Exception PUTing to bucket '{$bucketName}': {$e->getMessage()}" . "\n");
        shutDown("ERROR PUTing to bucket '{$bucketName}': {$e->getMessage()}" . "\n");
    }

    return $result;

}

/**
 * @param array $cliArgs
 * @param \Psr\Log\LoggerInterface $logger
 * @return \Aws\Common\Aws
 */
function createAwsClient(array $cliArgs, \Psr\Log\LoggerInterface $logger)
{
    $awsConfig['region'] = getCliArgValue($cliArgs, '--awsRegion');
    if(!$awsConfig['region']) {
        shutDown("Value for required commandline parameter: '--awsRegion' not found");
    }
    $profile = getCliArgValue($cliArgs, '--awsProfile');
    if($profile) {
        $awsConfig['profile'] = $profile;
    }

    return \Aws\Common\Aws::factory($awsConfig);
}

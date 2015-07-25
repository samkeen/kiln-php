<?php

require 'vendor/autoload.php';

$request_uuid = uniqid() . bin2hex(openssl_random_pseudo_bytes(2));

$logger = new Katzgrau\KLogger\Logger(
    __DIR__ . '/logs',
    Psr\Log\LogLevel::DEBUG,
    [
        'extension' => 'log',
        'filename' => 'app',
        'logFormat' => "[{date}] [{$request_uuid}] [{level}] {message}"
    ]
);

$configPath = __DIR__ . "/config/config.json";

try {
    $configFilePath = __DIR__ . "/config/config.yml";
    $config = new \Io\Samk\AmiBuilder\Utils\Config($configFilePath);
} catch (\Exception $e) {
    $logger->error(
        "There was a problem loading the config file are path: '{$configFilePath}'. Error: '{$e->getMessage()}'");
    exit("Problem Loading Config File.  Error: '{$e->getMessage()}'");
}

$awsConfig = $config->get('awsConfig');
$sqsConfig = $config->get('sqsConfig');
$sqsQueueAttributes = $sqsConfig['queueAttributes'];
/**
 * Init AWS SDK
 */
$aws = \Aws\Common\Aws::factory($awsConfig);

/**
 * Read SQS
 */
/** @var \Aws\Sqs\SqsClient $sqsClient */
$sqsClient = $aws->get('sqs');
$queueUrl = '';
try {
    // create Queue if not exists
    $createQueueResponse = $sqsClient->createQueue([
        'QueueName' => $sqsConfig['amiBuildRequestQueueName'],
        'Attributes' => $sqsQueueAttributes
    ]);
    $queueUrl = $createQueueResponse->get('QueueUrl');
//    $response = $sqsClient->deleteQueue(['QueueUrl' => $queueUrl]);

} catch (\Aws\Sqs\Exception\SqsException $e) {
    echo "ERROR: {$e->getMessage()}";
} catch (Exception $e) {
    echo "ERROR: {$e->getMessage()}" . "\n";
}

/**
 * if Work Found
 */
$logger->info('looking for work');
$work = $sqsClient->receiveMessage([
    "QueueUrl" => $queueUrl,
    "MaxNumberOfMessages" => 1,
    "MessageAttributeNames" => []
]);

print_r($work->toArray());

$message = $work->get("Messages")[0];

/**
 * extract Template name and SHA
 */
$messagePayload = json_decode($message['Body'], true);
if (json_last_error()) {
    exit("There was a JSON parse error on the work item Body: " . json_last_error_msg());
}

if (!$messagePayload) {
    exit("No Work found");
}

$template = $messagePayload['templateName'];

var_dump($template);

$cli = new \Io\Samk\AmiBuilder\Utils\Cli($logger);

/**
 * update template checkout
 */
$templatesConfig = $config->get('templates');
$templatesRepo = $templatesConfig['templatesRepo'];
$templatesCheckoutPath = $templatesConfig['checkoutPath'];

if (!file_exists($templatesCheckoutPath)) {
    list($output, $returnCode) = $cli->execute("git clone {$templatesRepo} {$templatesCheckoutPath}");
}
list($output, $returnCode) = $cli->execute('git fetch --all', $templatesCheckoutPath);
list($output, $returnCode) = $cli->execute('git reset --hard origin/master', $templatesCheckoutPath);

/**
 * Run packer and capture image region and name
 */
$template = ltrim($template, ' /');
$pathToTemplate = trim("{$templatesCheckoutPath}/{$template}");
if (!file_exists($pathToTemplate)) {
    exit("Path to build template not exists and/or not readable: '{$pathToTemplate}'");
}
$packerConfig = $config->get('packer');

//>>>>>>>>
exit("DEBUG STOP");

list($result, $returnCode) = runPackerBuild($packerConfig['executablePath'], $pathToTemplate, $packerConfig['awsAccessKey'],
    $packerConfig['awsSecretKey']);

print_r($result);
/**
 * Construct Job run manifest
 */

/**
 * Put manifest in S3
 */



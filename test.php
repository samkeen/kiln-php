<?php
date_default_timezone_set('UTC');
require 'vendor/autoload.php';
require __DIR__ . '/common.php';

$logger = new Katzgrau\KLogger\Logger(
    __DIR__ . '/logs/test',
    Psr\Log\LogLevel::DEBUG
);

/**
 * Test that we have proper access to
 *
 *   - Git Hub
 *   - SQS
 *   - S3
 */

try {
    $config = new \Io\Samk\AmiBuilder\Utils\Config(__DIR__ . "/config/config.yml");
} catch (\Exception $e) {
    $logger->error(
        "There was a problem loading the config. Error: '{$e->getMessage()}'");
    shutDown("Problem Loading Config File.  Error: '{$e->getMessage()}'");
}

$awsConfig = $config->get('awsConfig', true);
$sqsConfig = $config->get('sqsConfig', true);
/**
 * Init AWS SDK
 */
$aws = \Aws\Common\Aws::factory($awsConfig);

/**
 * Test SQS connectivity
 */
/** @var \Aws\Sqs\SqsClient $sqsClient */
$sqsClient = $aws->get('sqs');
$queueUrl = $sqsConfig['amiBuildRequestQueueUrl'];
try {

    echo "1. Checking for SQS connectivity\n\n";
    echo "Attempting receiveMessage call on SQS '{$queueUrl}'...\n";
    $work = $sqsClient->receiveMessage([
        "QueueUrl" => $queueUrl,
        "MaxNumberOfMessages" => 1,
        "VisibilityTimeout" => 1, // 1 sec time out
        "MessageAttributeNames" => []
    ]);
    echo "Was able to make receiveWork call on SQS '{$queueUrl}' without error\n\n";

} catch (Exception $e) {
    shutDown("Error when attempting to access and/or receive work from SQS: '{$queueUrl}'"
        . ".  Error message: {$e->getMessage()}");
}


$cli = new \Io\Samk\AmiBuilder\Utils\Cli(
    $logger,
    [
        "patterns" => ['/(aws_access_key)=([\w\+]+)(.*)/', '/(aws_secret_key)=([\w\+]+)(.*)/'],
        "replacements" => '$1=<$1>$3'
    ]
);

/**
 * Test Github connectivity
 */
try {
    echo "2. Checking git hub template repo connectivity\n\n";
    $templatesConfig = $config->get('templates', true);
    $templatesRepo = $templatesConfig['templatesRepo'];
    $templatesCheckoutPath = $templatesConfig['checkoutPath'];

    if (!file_exists($templatesCheckoutPath)) {
        echo "Template repo not found locally, attempting Clone of repo '{$templatesRepo}' to local dir '{$templatesCheckoutPath}'...\n";
        list($output, $returnCode) = $cli->execute("git clone {$templatesRepo} {$templatesCheckoutPath}");
        checkCliResponse($output, $returnCode);
    } else {
        echo "Local checkout of template repo '{$templatesRepo}', exists at '{$templatesCheckoutPath}', attempting git fetch...\n";
        list($output, $returnCode) = $cli->execute('git fetch --all', $templatesCheckoutPath);
        checkCliResponse($output, $returnCode);
    }


} catch (Exception $e) {
    shutDown("Error when attempting to access Githup repo: '{$templatesRepo}'"
        . ".  Error message: {$e->getMessage()}");
}

/**
 * Test S3 connectivity
 */
try {
    /** @var \Aws\S3\S3Client $s3Client */
    $s3Client = $aws->get('S3');
    $s3Config = $config->get('executionDigest', true);
    $bucketName = $s3Config["bucketName"];

    echo "3. Checking S3 digest bucket '{$bucketName}' connectivity\n\n";
    $result = $s3Client->headBucket(array(
        // Bucket is required
        'Bucket' => $bucketName,
    ));
    echo "Read access to '{$bucketName}', success\n";
    $objectPath = "connectivityTest/" . date("U") . ".txt";
    echo "Testing write access. Attempting to PUT '{$bucketName}/{$objectPath}' ...\n";
    $result = $s3Client->putObject(array(
        'Bucket' => $bucketName,
        'Key' => $objectPath,
        'Body' => "connectivity test object, OK to delete"
    ));
    echo "Write access success\n";

} catch (Exception $e) {
    shutDown("Error when attempting to access S3 digest bucket: '{$bucketName}'"
        . ".  Error message: {$e->getMessage()}");
}

/**
 * @param $cliOutput
 * @param $returnCode
 */
function checkCliResponse($cliOutput, $returnCode)
{
    if ($returnCode == 0) {
        echo "Success\n";
    } else {
        echo "Received non-zero return code '{$returnCode}', Output was:\n";
        echo implode("\n", $cliOutput);
        shutDown();
    }
}

# kiln

This is a [Packer](https://www.packer.io/) based AMI building system.  Its goal is to simplify and help organize the creation of AMIs.

It's current incarnation is in PHP, I plan to port that to Python, or possibly Golang in the near future.

[![Build Status](https://travis-ci.org/samkeen/kiln.svg?branch=master)](https://travis-ci.org/samkeen/kiln)

[![Coverage Status](https://coveralls.io/repos/samkeen/kiln/badge.svg?branch=master&service=github)](https://coveralls.io/github/samkeen/kiln?branch=master)

## Architecture

The build machine watches the work queue for build requests.  

Packer templates are kept in a specified Github Repo

Logging for the build machine is sent to [CloudWatch Logs](https://aws.amazon.com/about-aws/whats-new/2014/07/10/introducing-amazon-cloudwatch-logs/) so they can be easily monitored and/or browsed.

Each AMI build job creates a *build summary* document and places it in a specified S3 bucket.

![Overall Architecture](https://raw.githubusercontent.com/samkeen/kiln/master/docs/SeederArchitecture.png)

A build request job is sent to the work queue.  It specifies the name and version (git sha) of the template to build.

Then build machine picks up the job.  It pull the template at the correct version from your github repo and uses it for the Paker build of the AMI.

Once the AMI build is complete, the build machine puts a job summary document in the specified audit-trail s3 bucket.


## Install

Kiln installs via a CloudFormation Script.  It creates all its own resources.

![Overall Architecture](https://raw.githubusercontent.com/samkeen/kiln/master/docs/cf-template-config-screen.png)

![Overall Architecture](https://raw.githubusercontent.com/samkeen/kiln/master/docs/cf-template-complete-screen.png)

## Operation

### Trigger A Job

Simply send a Job to SQS that specifies the template name and version (git sha)
 
![Overall Architecture](https://raw.githubusercontent.com/samkeen/kiln/master/docs/SQS-send-test-message.png)

### Review A Job

#### Logs

As a Job runs, its application logs are flushed to 
[AWS CloudWatch Logs](http://docs.aws.amazon.com/AmazonCloudWatch/latest/DeveloperGuide/WhatIsCloudWatchLogs.html) 
so you can watch that output in near real-time and/or have automated triggers on key phrases in the logs.

![Overall Architecture](https://raw.githubusercontent.com/samkeen/kiln/master/docs/cloudwatch-logs-output.png)

#### Audit Trail

A completed job will have an audit trail entry in S3 similar to this:

```yaml
executionUuid: 55dc9fd9a3c65ab0d
startTimestamp: '1440522201.6707'
startDateTime: Tue, 25 Aug 2015 17:03:21 +0000
amiBuildQueueUrl: >
  https://sqs.us-west-2.amazonaws.com/182026393062/kiln-KilnBuildRequestQueue-10R0TG7QZGGUI
jobMessage:
  Body: |
    {
      "templatename": "testing/just-prove-it-works.json",
      "sha": "647d8d320bd0459f49b87cdfc5f6aa8c2c481a5b"
    }
  MD5OfBody: 5ba9f78d6150868e33d8d480685eb533
  MessageId: 84ecceff-a87d-4cd6-b76a-274b2dc8a658
jobBuildTemplate: testing/just-prove-it-works.json
jobBuildTemplateSha: 647d8d320bd0459f49b87cdfc5f6aa8c2c481a5b
createdAmiId: ami-09998f39
createdAmiRegion: us-west-2
endedInError: false
endTimestamp: '1440522378.4591'
endDateTime: Tue, 25 Aug 2015 17:06:18 +0000
processingDuration: '176.78837418556'
```

These are located in the specified bucket who's path is constructed thus:

```
                                                                    [sha of template repo in work queue entry]           
                                                                                                       |
                                                                     [time bucket was written (UTC)]   |
                                                                                      |                |
                                        ['templateName' from work queue entry]        |                |
                                                           |                          |                |
[--bucket built by cloud formation-----]       |-----------^------------------| |-----^-----------| |--^--|
kiln-kilnaudittrailbucket-1vltg2p89mlz5/builds/testing/just-prove-it-works.json/2015-08-25T17-03-21/647d8d3.yml                                                                                 
```

### Private Templates Repo

If your Packer templates repo is private, there is currently no automated solution to set up authentication 
to your Repo from the Kiln machine. You'll need to set that up yourself.  I'd suggest a 
[Deploy Key](https://developer.github.com/guides/managing-deploy-keys/#deploy-keys)

## Optionally install locally

You would normally only do this if you were developing kiln.

```bash
$ git clone https://github.com/samkeen/kiln.git
$ cd kiln/
$ cp config/config.dist.yml config/config.yml
$ vi config/config.yml

## if you do not have Composer installed
$ curl -sS https://getcomposer.org/installer | php

$ php composer.phar install

# Optionally, test system connectivity
$ php test.php

$ php run.php --awsRegion us-west-2

```

### AWS CLI Credentials

The script will utilize you local AWS Cli configuration (~/.aws)

You can override AWS region and/or AWS profile with command line args

Example

```
php run.php --awsRegion us-west-2 --awsProfile testing-account
```

### App Config

Default path for config is './config/config.yml'

If the cli arg --config is given, that path is used instead.  It supports s3 bucket paths

Example

```
php run.php --awsRegion us-west-2 --config s3://kiln-config/testing/config.yml`
```

### Private Templates Repo

If your Packer templates repo is private, there is currently no automated solution to set up authentication 
to your Repo from the Kiln machine. You'll need to set that up yourself.  I'd suggest a 
[Deploy Key](https://developer.github.com/guides/managing-deploy-keys/#deploy-keys)



===================== THE NEW +===========================





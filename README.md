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

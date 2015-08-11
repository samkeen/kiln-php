# kiln

This is a [Packer](https://www.packer.io/) based AMI building system.  Its goal is to simplify and help organize the creation of AMIs.

It's current incarnation is in PHP, I plan to port that to Python, or possibly Golang in the near future.

[![Build Status](https://travis-ci.org/samkeen/kiln.svg?branch=master)](https://travis-ci.org/samkeen/kiln)

[![Coverage Status](https://coveralls.io/repos/samkeen/kiln/badge.svg?branch=master&service=github)](https://coveralls.io/github/samkeen/kiln?branch=master)

## Architecture

This is the planned architecture, not there yet but making progress.

![Overall Architecture](https://raw.githubusercontent.com/samkeen/kiln/master/docs/SeederArchitecture.png)


## Install

```
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

### AWS Credentials

The script will utilize you local AWS Cli configuration (~/.aws)

You can override AWS region and/or AWS profile with command line args

Example

```
php run.php --awsRegion us-west-2 --awsProfile sam-new-admin
```

### App Config

Default path for config is './config/config.yml'

If the cli arg --config is given, that path is used instead.  It supports s3 bucket paths

Example

```
php run.php --awsRegion us-west-2 --config s3://kiln-config/testing/config.yml`
```

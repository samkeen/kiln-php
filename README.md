# seedling

[![Build Status](https://travis-ci.org/samkeen/seedling.svg?branch=master)](https://travis-ci.org/samkeen/seedling)

[![Coverage Status](https://coveralls.io/repos/samkeen/seedling/badge.svg)](https://coveralls.io/r/samkeen/seedling)

## Architecture

This is the planned architecture, not there yet but making progress.

![Overall Architecture](https://raw.githubusercontent.com/samkeen/seeder/master/docs/SeederArchitecture.png)


## Install

```
$ git clone https://github.com/samkeen/seedling.git
$ cd seedling/
$ cp config/config.dist.yml config/config.yml
$ vi config/config.yml

## if you do not have Composer installed
$ curl -sS https://getcomposer.org/installer | php

$ php composer.phar install

# Optionally, test system connectivity
$ php test.php

$ php run.php

```

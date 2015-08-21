#!/bin/bash

rm -rf ./vendor/phpunit

tar cfz kiln.tar.gz `cat ./dist.includes.txt`

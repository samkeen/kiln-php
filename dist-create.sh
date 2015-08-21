#!/bin/bash

rm -rf ./vendor

composer install --no-dev

tar cfz kiln.tar.gz `cat ./dist.includes.txt`

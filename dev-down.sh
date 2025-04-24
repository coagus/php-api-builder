#!/bin/bash

docker compose down
rm -rf vendor
rm -rf .env
rm -rf log
rm -rf composer.lock

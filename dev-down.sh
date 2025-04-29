#!/bin/bash
docker compose down
docker rmi mysql-server:pab
docker rmi php-server:pab
rm -rf vendor
rm -rf .env
rm -rf log
rm -rf composer.lock

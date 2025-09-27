#!/bin/bash

# Copy .env file
cp example.env .env
source .env

echo "> Stopping services ______________________________________________________"
echo "docker compose down"
docker compose down
echo ""

echo "> Removing images ________________________________________________________"
echo "docker rmi php-server:${DOCKER_PROJECT:-php}"
docker rmi php-server:${DOCKER_PROJECT:-php}
echo "docker rmi mysql-server:${DOCKER_PROJECT:-mysql}"
docker rmi mysql-server:${DOCKER_PROJECT:-mysql}
echo ""

echo "> Removing files _________________________________________________________"
echo "rm -rf vendor .env log composer.lock"
rm -rf vendor .env log composer.lock

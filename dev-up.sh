#!/bin/bash

# Copy .env file
cp example.env .env
source .env

echo "> Starting services ______________________________________________________"
echo "docker compose up -d"
docker compose up -d
echo ""

echo "> Installing dependencies _________________________________________________"
echo "docker exec api-${DOCKER_PROJECT:-php} composer install --verbose"
docker exec api-${DOCKER_PROJECT:-php} composer install --verbose
echo ""

echo "> Regenerating autoloader _________________________________________________"
echo "docker exec api-${DOCKER_PROJECT:-php} composer dump-autoload -o"
docker exec api-${DOCKER_PROJECT:-php} composer dump-autoload -o
echo ""

echo "> Running the health service ______________________________________________"
echo "curl http://localhost:${PHP_PORT:-80}/api/v1/health"
curl http://localhost:${PHP_PORT:-80}/api/v1/health
echo ""
echo ""

echo "> Waiting for the database service to be available __________________________"
echo "curl http://localhost:${PHP_PORT:-80}/api/v1/health/database"

max_attempts=30
attempt=1
while [ $attempt -le $max_attempts ]; do
    response=$(curl -s http://localhost:${PHP_PORT:-80}/api/v1/health/database)
    successful=$(echo $response | grep -o '"successful":[^,}]*' | grep -o '[^:]*$' | tr -d '[:space:]"')
    
    if [ "$successful" = "true" ]; then
        echo "Servicio disponible y conectado a la base de datos"
        break
    fi
    
    echo "Esperando que el servicio esté disponible... intento $attempt de $max_attempts"
    sleep 1
    attempt=$((attempt + 1))
done

if [ $attempt -gt $max_attempts ]; then
    echo "Error: No se pudo establecer conexión después de $max_attempts intentos"
    exit 1
fi
echo ""

echo "> Database is ready _________________________________________________________"
curl http://localhost:${PHP_PORT:-80}/api/v1/health/database

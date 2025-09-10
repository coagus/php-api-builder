#!/bin/bash

# Copy .env file
cp example.env .env

# Start services
docker compose up -d

# Install dependencies
docker exec pab-api composer install --verbose

# Regenerate autoloader
docker exec pab-api composer dump-autoload -o

# Containers
docker ps

# Run the demo
echo "Running the demo service..."
echo "curl http://localhost:${PHP_PORT:-80}/api/v1/demos"
curl http://localhost:${PHP_PORT:-80}/api/v1/demos
echo ""

# Wait for database service to be available
max_attempts=30
attempt=1
while [ $attempt -le $max_attempts ]; do
    echo "curl -s http://localhost:${PHP_PORT:-80}/api/v1/roles"
    response=$(curl -s http://localhost:${PHP_PORT:-80}/api/v1/roles)
    successful=$(echo $response | grep -o '"successful":[^,}]*' | grep -o '[^:]*$' | tr -d '[:space:]"')
    
    if [ "$successful" = "true" ]; then
        echo "Service available and connected to database"
        break
    fi
    
    echo "Waiting for service to be available... attempt $attempt of $max_attempts"
    sleep 1
    attempt=$((attempt + 1))
done

if [ $attempt -gt $max_attempts ]; then
    echo "Error: Could not establish connection after $max_attempts attempts"
    exit 1
fi

# Run the database
echo "Running role service..."
curl http://localhost:${PHP_PORT:-80}/api/v1/roles

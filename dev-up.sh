#!/bin/bash

# Start services
docker compose up -d

# Install dependencies
docker exec pab-api composer install --verbose

# Regenerate autoloader
docker exec pab-api composer dump-autoload -o

# Copy .env file
cp example.env .env

# Run the demo
echo "Running the demo service..."
curl http://localhost:80/api/v1/demo
echo ""

# Wait for database service to be available
max_attempts=30
attempt=1
while [ $attempt -le $max_attempts ]; do
    response=$(curl -s http://localhost:80/api/v1/role)
    successful=$(echo $response | grep -o '"successful":[^,}]*' | grep -o '[^:]*$' | tr -d '[:space:]"')
    
    if [ "$successful" = "true" ]; then
        echo "Service available and connected to database"
        break
    fi
    
    echo "Waiting for service to be available... attempt $attempt of $max_attempts"
    sleep 1
    attempt=$((attempt + 1))
done

#if [ $attempt -gt $max_attempts ]; then
#    echo "Error: Could not establish connection after $max_attempts attempts"
#    exit 1
#fi

# Run the database
#echo "Running role service..."
#curl http://localhost:80/api/v1/role

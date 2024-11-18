#!/bin/bash

# Navigate to the parent directory
cd "$(dirname "$0")/.."

# Start the application using Docker Compose
echo "🚀 Starting the application with Docker Compose..."
docker-compose up --build -d && echo "✅ Application is now running." || { echo "❌ Failed to start the application."; exit 1; }

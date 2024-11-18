#!/bin/bash

# Navigate to the parent directory
cd "$(dirname "$0")/.."

# Stop and remove running containers
echo "🛑 Stopping and removing Docker containers..."
docker-compose down && echo "✅ Docker containers stopped and removed." || { echo "❌ Failed to stop Docker containers."; exit 1; }

# Clean the psdata directory (keeping the folder and .gitkeep files)
echo "🧹 Cleaning contents of psdata directory, preserving .gitkeep files..."
find psdata -mindepth 1 ! -name '.gitkeep' -exec rm -rf {} + && echo "✅ Cleaned psdata directory." || { echo "❌ Failed to clean psdata directory."; exit 1; }

#!/bin/bash

# Navigate to the parent directory
cd "$(dirname "$0")/.."

echo "🚀 Starting Satispay Module Packaging..."

echo "📦 Installing composer dependencies (excluding dev)..."
composer install --no-dev && echo "✅ Dependencies installed." || { echo "❌ Installation failed."; exit 1; }

# Read .gitattributes and gather paths marked as export-ignore
echo "🔍 Reading .gitattributes to exclude export-ignore paths..."
EXCLUDES=()
while IFS= read -r line; do
    if [[ $line == *"export-ignore"* ]]; then
        # Extract the path before "export-ignore"
        PATH_TO_EXCLUDE=$(echo "$line" | awk '{print $1}')
        EXCLUDES+=("$PATH_TO_EXCLUDE")
    fi
done < .gitattributes

# Create a temporary directory for the flat structure
TEMP_DIR="tmp"
rm -rf "$TEMP_DIR"
mkdir "$TEMP_DIR"

# Copy files to the temporary directory except those excluded
echo "📂 Copying files to a flat structure..."
for file in *; do
    # Check if the file is in the exclude list
    if [[ ! " ${EXCLUDES[@]} " =~ " ${file} " ]]; then
        cp -- "$file" "$TEMP_DIR/"  # Use -- to indicate end of options
    fi
done

# Create the zip archive from the temporary directory
echo "🗜️  Creating Satispay archive..."
cd "$TEMP_DIR"
zip -r ../satispay.zip ./* && echo "✅ Archive created: satispay.zip" || { echo "❌ Failed to create archive."; exit 1; }
cd ..

# Clean up
rm -rf "$TEMP_DIR"

echo "🎉 Packaging complete!"

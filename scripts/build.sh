#!/bin/bash

# Navigate to the script's directory
cd "$(dirname "$0")/.." || { echo "❌ Failed to navigate to script directory."; exit 1; }

echo "🚀 Starting Satispay Module Packaging..."

# Install composer dependencies (excluding dev)
echo "📦 Installing composer dependencies (excluding dev)..."
composer install --no-dev && echo "✅ Dependencies installed." || { echo "❌ Installation failed."; exit 1; }

# Read .gitattributes and gather paths marked as export-ignore
echo "🔍 Reading .gitattributes to gather export-ignore paths..."
EXCLUDES=()
while IFS= read -r line; do
    if [[ $line == *"export-ignore"* ]]; then
        PATH_TO_EXCLUDE=$(echo "$line" | awk '{print $1}')
        EXCLUDES+=("$PATH_TO_EXCLUDE")
    fi
done < .gitattributes

if [ ${#EXCLUDES[@]} -eq 0 ]; then
    echo "⚠️  No export-ignore paths found in .gitattributes."
else
    echo "📝 Paths marked as export-ignore: ${EXCLUDES[*]}"
fi

# Create a temporary directory for the flat structure
TEMP_DIR="tmp/"
MODULE_DIR="$TEMP_DIR/satispay"
echo "📂 Preparing temporary directory: $TEMP_DIR"
rm -rf "$TEMP_DIR satispay.zip"
mkdir -p "$MODULE_DIR" || { echo "❌ Failed to create temporary directory."; exit 1; }

# Create the rsync exclude arguments
EXCLUDE_ARGS=()
for pattern in "${EXCLUDES[@]}"; do
    EXCLUDE_ARGS+=(--exclude="$pattern")
done

# Copy files while excluding specified paths
echo "🚀 Copying files (excluding: ${EXCLUDES[*]})..."
rsync -av "${EXCLUDE_ARGS[@]}" "./" "$MODULE_DIR" && echo "✅ Files copied successfully." || { echo "❌ Failed to copy files."; exit 1; }

# Create the zip archive from the satispay folder
echo "🗜️  Creating Satispay archive..."
cd "$TEMP_DIR" || { echo "❌ Failed to navigate to temporary directory."; exit 1; }
zip -r ../satispay.zip satispay -x "**/.DS_Store" && echo "✅ Archive created: satispay.zip" || { echo "❌ Failed to create archive."; exit 1; }
cd ..

# Clean up
echo "🧹 Cleaning up temporary directory: $TEMP_DIR"
rm -rf "$TEMP_DIR" && echo "✅ Cleanup complete."

echo "🎉 Packaging complete!"

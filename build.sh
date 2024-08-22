#!/bin/bash

# Define the target directory and items to copy
TARGET_DIR="altapay-master"
DIRECTORIES=("src" "ci")  # List of directories to copy
FILES=("composer.json" ".gitlab-ci.yml" ".gitignore")  # List of files to copy

# Create the target directory
mkdir -p "$TARGET_DIR"

# Copy directories
for DIR in "${DIRECTORIES[@]}"; do
    if [ -d "$DIR" ]; then
        cp -r "$DIR" "$TARGET_DIR/"
    else
        echo "Directory $DIR does not exist. Skipping."
    fi
done

# Copy files
for FILE in "${FILES[@]}"; do
    if [ -f "$FILE" ]; then
        cp "$FILE" "$TARGET_DIR/"
    else
        echo "File $FILE does not exist. Skipping."
    fi
done

# Zip the target directory
zip -r "${TARGET_DIR}.zip" "$TARGET_DIR"

# Remove the target directory after zipping
rm -r "$TARGET_DIR"

echo "Created and zipped directory: ${TARGET_DIR}.zip"

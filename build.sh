#!/bin/bash

# Define the plugin slug and the output zip file name.
PLUGIN_SLUG="learnpress-woo-integration"
ZIP_FILE="${PLUGIN_SLUG}.zip"
SOURCE_DIR="$PLUGIN_SLUG"
BUILD_DIR="build" # A temporary directory

# --- Clean up old files ---
echo "Cleaning up old files..."
rm -f "$ZIP_FILE"
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

# --- Copy plugin files to a temporary build directory ---
# This is a safer way to ensure only necessary files are included.
echo "Copying plugin files..."
cp -r "$SOURCE_DIR/"* "$BUILD_DIR/"

# --- Create the new zip file from the build directory ---
# We change into the build directory so the files are at the root of the zip.
echo "Creating new ZIP file..."
cd "$BUILD_DIR"
zip -r "../$ZIP_FILE" ./*

# --- Final cleanup ---
cd ..
rm -rf "$BUILD_DIR"

echo "Successfully created $ZIP_FILE"

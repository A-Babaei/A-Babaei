#!/bin/bash

# Define the plugin slug and the output zip file name.
PLUGIN_SLUG="jules-lp-woo-integration"
ZIP_FILE="${PLUGIN_SLUG}.zip"
SOURCE_DIR="$PLUGIN_SLUG"
BUILD_DIR="build" # A temporary directory

# --- Clean up old files ---
echo "Cleaning up old files..."
rm -f "$ZIP_FILE"
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

# --- Copy plugin files to a temporary build directory ---
echo "Copying plugin files..."
cp -r "$SOURCE_DIR/"* "$BUILD_DIR/"

# --- Create the new zip file from the build directory ---
echo "Creating new ZIP file..."
cd "$BUILD_DIR"
zip -r "../$ZIP_FILE" ./*

# --- Final cleanup ---
cd ..
rm -rf "$BUILD_DIR"

echo "Successfully created $ZIP_FILE"

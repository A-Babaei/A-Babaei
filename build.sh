#!/bin/bash

# Define the plugin slug and the output zip file name.
PLUGIN_SLUG="learnpress-woo-integration"
ZIP_FILE="${PLUGIN_SLUG}.zip"

# Remove the old zip file if it exists.
if [ -f "$ZIP_FILE" ]; then
    rm "$ZIP_FILE"
    echo "Removed old $ZIP_FILE"
fi

# Create the new zip file, including the plugin's directory.
zip -r "$ZIP_FILE" "$PLUGIN_SLUG/"

echo "Successfully created $ZIP_FILE"

#!/bin/bash
# 
# Quick sync script for Pinecone color data
# Usage: ./quick-sync-pinecone.sh
#

set -e

echo "=== PhotoLibrary Pinecone Sync ==="
echo ""

# Check if we're in the right directory
if [ ! -f "sync-colors-to-pinecone.php" ]; then
    echo "Error: Please run this script from the plugin directory"
    echo "cd wp-content/plugins/photo_library_wp_api_rest_plugin/"
    exit 1
fi

# Check if PINECONE_API_KEY is set
if [ -z "$PINECONE_API_KEY" ]; then
    echo "Error: PINECONE_API_KEY environment variable not set"
    echo ""
    echo "Set it with:"
    echo "  export PINECONE_API_KEY='your-api-key'"
    echo ""
    exit 1
fi

# Check if running in DDEV
if command -v ddev &> /dev/null; then
    echo "Using DDEV environment..."
    echo ""
    
    # Option 1: Sync existing palettes
    echo "1. Sync existing palettes to Pinecone"
    echo "2. Extract missing palettes first, then sync"
    echo "3. Extract missing palettes only"
    echo ""
    read -p "Choose option (1-3): " OPTION
    
    export SYNC_OPTION=$OPTION
    ddev exec "wp eval-file wp-content/plugins/photo_library_wp_api_rest_plugin/sync-colors-to-pinecone.php"
else
    echo "Using local WP-CLI..."
    echo ""
    
    read -p "Choose option (1-3): " OPTION
    
    export SYNC_OPTION=$OPTION
    wp eval-file sync-colors-to-pinecone.php
fi

echo ""
echo "=== Sync Complete ==="

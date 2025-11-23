<?php
/**
 * Pinecone Color Sync Script
 * 
 * Synchronizes photo color data from WordPress to Pinecone vector database.
 * Extracts RGB palette from photos and uploads to Pinecone for color-based search.
 * 
 * Usage: 
 *   cd phototheque-wp/
 *   export PINECONE_API_KEY="your-api-key"
 *   ddev exec "wp eval-file wp-content/plugins/photo_library_wp_api_rest_plugin/src/pinecone/sync-colors-to-pinecone.php"
 * 
 * @package PhotoLibrary
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../../../../');
}

// Load WordPress
require_once ABSPATH . 'wp-load.php';

// Load required classes
require_once __DIR__ . '/../class.photo-library-pinecone.php';
require_once __DIR__ . '/../class.photo-library-db.php';
require_once __DIR__ . '/../class.photo-library-schema.php';
require_once __DIR__ . '/../color/class.photo-library-color.php';

/**
 * Sync all photos with color palettes to Pinecone
 */
function sync_colors_to_pinecone() {
    global $wpdb;
    
    try {
        // Initialize Pinecone
        $color_search = new PL_Color_Search_Index();
        
        WP_CLI::line('=== PhotoLibrary Color Sync to Pinecone ===');
        WP_CLI::line('');
        
        // Get all photos with palette data
        $photos = $wpdb->get_results(
            "SELECT 
                p.ID as id,
                p.post_title as title,
                palette.meta_value as palette
            FROM {$wpdb->prefix}posts AS p
            LEFT JOIN {$wpdb->prefix}postmeta AS palette 
                ON p.ID = palette.post_id 
                AND palette.meta_key = '_pl_palette'
            WHERE p.post_type = 'attachment'
                AND p.post_mime_type LIKE 'image%'
                AND palette.meta_value IS NOT NULL
            ORDER BY p.ID"
        );
        
        if (empty($photos)) {
            WP_CLI::error('No photos with color palette found. Run color extraction first.');
            return;
        }
        
        WP_CLI::line('Found ' . count($photos) . ' photos with color palettes');
        WP_CLI::line('');
        
        // Prepare data for Pinecone
        $photos_to_sync = array();
        $skipped = 0;
        
        foreach ($photos as $photo) {
            $palette = maybe_unserialize($photo->palette);
            
            if (!is_array($palette) || empty($palette)) {
                WP_CLI::warning("Photo ID {$photo->id}: Invalid palette data");
                $skipped++;
                continue;
            }
            
            // Get first color from palette (dominant color)
            $dominant_color = $palette[0];
            
            if (!is_array($dominant_color) || count($dominant_color) !== 3) {
                WP_CLI::warning("Photo ID {$photo->id}: Invalid RGB values");
                $skipped++;
                continue;
            }
            
            $photos_to_sync[] = array(
                'id' => $photo->id,
                'rgb' => array(
                    (int) $dominant_color[0],
                    (int) $dominant_color[1],
                    (int) $dominant_color[2]
                )
            );
            
            WP_CLI::line("✓ Prepared photo ID {$photo->id}: RGB(" . implode(', ', $dominant_color) . ")");
        }
        
        WP_CLI::line('');
        WP_CLI::line("Prepared {count($photos_to_sync)} photos for sync");
        
        if ($skipped > 0) {
            WP_CLI::warning("Skipped {$skipped} photos with invalid data");
        }
        
        if (empty($photos_to_sync)) {
            WP_CLI::error('No valid photos to sync');
            return;
        }
        
        // Upload to Pinecone in batches
        WP_CLI::line('');
        WP_CLI::line('Uploading to Pinecone...');
        
        $success = $color_search->batch_upsert_photos($photos_to_sync);
        
        if ($success) {
            WP_CLI::success("Successfully synced " . count($photos_to_sync) . " photos to Pinecone");
            
            // Get index stats
            $stats = $color_search->get_index_stats();
            $total_vectors = $stats['totalVectorCount'] ?? 0;
            
            WP_CLI::line('');
            WP_CLI::line("Index stats:");
            WP_CLI::line("  Total vectors in Pinecone: {$total_vectors}");
            WP_CLI::line("  Namespace: photos");
            
        } else {
            WP_CLI::error('Failed to sync photos to Pinecone. Check error logs.');
        }
        
    } catch (Exception $e) {
        WP_CLI::error('Sync failed: ' . $e->getMessage());
    }
}

/**
 * Extract and save color palette for photos that don't have one
 */
function extract_missing_palettes() {
    global $wpdb;
    
    try {
        WP_CLI::line('=== Extracting Color Palettes ===');
        WP_CLI::line('');
        
        // Get photos without palette
        $photos = $wpdb->get_results(
            "SELECT 
                p.ID as id,
                p.post_title as title,
                p.guid as img_url,
                metadata.meta_value as metadata
            FROM {$wpdb->prefix}posts AS p
            LEFT JOIN {$wpdb->prefix}postmeta AS metadata 
                ON p.ID = metadata.post_id 
                AND metadata.meta_key = '_wp_attachment_metadata'
            LEFT JOIN {$wpdb->prefix}postmeta AS palette 
                ON p.ID = palette.post_id 
                AND palette.meta_key = '_pl_palette'
            WHERE p.post_type = 'attachment'
                AND p.post_mime_type LIKE 'image%'
                AND palette.meta_value IS NULL
            ORDER BY p.ID
            LIMIT 100"
        );
        
        if (empty($photos)) {
            WP_CLI::success('All photos already have color palettes');
            return;
        }
        
        WP_CLI::line('Found ' . count($photos) . ' photos without palettes');
        WP_CLI::line('');
        
        $color_handler = new PL_COLOR_HANDLER();
        $db = new PL_REST_DB($wpdb);
        $extracted = 0;
        
        foreach ($photos as $photo) {
            try {
                WP_CLI::line("Processing photo ID {$photo->id}...");
                
                // Extract palette
                $palette = $color_handler->extractPalette($photo);
                
                if (!empty($palette)) {
                    // Save to database
                    $db->savePaletteMeta($photo->id, $palette);
                    $extracted++;
                    
                    $dominant = $palette[0];
                    WP_CLI::line("  ✓ Extracted palette: RGB(" . implode(', ', $dominant) . ")");
                } else {
                    WP_CLI::warning("  ✗ Failed to extract palette");
                }
                
            } catch (Exception $e) {
                WP_CLI::warning("  ✗ Error: " . $e->getMessage());
            }
        }
        
        WP_CLI::line('');
        WP_CLI::success("Extracted palettes for {$extracted} photos");
        
    } catch (Exception $e) {
        WP_CLI::error('Extraction failed: ' . $e->getMessage());
    }
}

// Main execution
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::line('');
    WP_CLI::line('PhotoLibrary Pinecone Sync Tool');
    WP_CLI::line('================================');
    WP_CLI::line('');
    
    // Check if PINECONE_API_KEY is set
    if (!getenv('PINECONE_API_KEY')) {
        WP_CLI::error('PINECONE_API_KEY environment variable not set');
        exit(1);
    }
    
    // Ask user what to do
    WP_CLI::line('Options:');
    WP_CLI::line('  1. Sync existing palettes to Pinecone');
    WP_CLI::line('  2. Extract missing palettes first, then sync');
    WP_CLI::line('  3. Extract missing palettes only (no sync)');
    WP_CLI::line('');
    
    // For automated execution, sync existing palettes
    $choice = getenv('SYNC_OPTION') ?: '1';
    
    switch ($choice) {
        case '1':
            sync_colors_to_pinecone();
            break;
        case '2':
            extract_missing_palettes();
            WP_CLI::line('');
            sync_colors_to_pinecone();
            break;
        case '3':
            extract_missing_palettes();
            break;
        default:
            WP_CLI::error('Invalid option');
    }
    
} else {
    echo "This script must be run via WP-CLI\n";
    echo "Usage: ddev exec \"wp eval-file wp-content/plugins/photo_library_wp_api_rest_plugin/src/pinecone/sync-colors-to-pinecone.php\"\n";
    exit(1);
}

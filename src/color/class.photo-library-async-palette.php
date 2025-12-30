<?php
/**
 * Async Palette Generator
 *
 * Handles asynchronous palette extraction for images without palettes.
 * Uses WordPress cron system to process palette generation in background.
 *
 * @package PhotoLibrary
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PL_Async_Palette_Generator
 */
class PL_Async_Palette_Generator
{
    /**
     * Hook name for async palette generation
     */
    const HOOK_NAME = 'pl_generate_palette_async';

    /**
     * Initialize the async palette generator
     */
    public static function init()
    {
        // Register the async hook
        add_action(self::HOOK_NAME, [__CLASS__, 'generate_palette'], 10, 1);
    }

    /**
     * Schedule palette generation for an attachment
     *
     * @param int $attachment_id WordPress attachment ID
     * @return bool True if scheduled successfully
     */
    public static function schedule_palette_generation(int $attachment_id): bool
    {
        // Check if palette already exists
        $existing_palette = get_post_meta($attachment_id, '_pl_palette', true);
        if (!empty($existing_palette)) {
            return false; // Palette already exists
        }

        // Check if already scheduled
        $scheduled = wp_next_scheduled(self::HOOK_NAME, [$attachment_id]);
        if ($scheduled) {
            return false; // Already scheduled
        }

        // Schedule single event to run ASAP
        $result = wp_schedule_single_event(time(), self::HOOK_NAME, [$attachment_id]);

        error_log("PL_Async_Palette: Scheduled palette generation for attachment {$attachment_id}");
        
        return $result !== false;
    }

    /**
     * Generate palette for an attachment (runs asynchronously)
     *
     * @param int $attachment_id WordPress attachment ID
     */
    public static function generate_palette(int $attachment_id)
    {
        error_log("PL_Async_Palette: Starting palette generation for attachment {$attachment_id}");

        try {
            // Check if palette already exists (race condition protection)
            $existing_palette = get_post_meta($attachment_id, '_pl_palette', true);
            if (!empty($existing_palette)) {
                error_log("PL_Async_Palette: Palette already exists for {$attachment_id}, skipping");
                return;
            }

            // Get attachment post
            $attachment = get_post($attachment_id);
            if (!$attachment || $attachment->post_type !== 'attachment') {
                error_log("PL_Async_Palette: Invalid attachment {$attachment_id}");
                return;
            }

            // Check if it's an image
            if (!wp_attachment_is_image($attachment_id)) {
                error_log("PL_Async_Palette: Attachment {$attachment_id} is not an image");
                return;
            }

            // Get image metadata
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (!$metadata) {
                error_log("PL_Async_Palette: No metadata for attachment {$attachment_id}");
                return;
            }

            // Create picture object for color handler
            $picture = (object)[
                'id' => $attachment_id,
                'post_title' => $attachment->post_title,
                'guid' => $attachment->guid,
                'metadata' => $metadata
            ];

            // Calculate area for palette extraction
            $width = $metadata['width'] ?? 0;
            $height = $metadata['height'] ?? 0;
            
            $area = null;
            if ($width > 0 && $height > 0) {
                $area = [
                    (($width / 3) * 2) + 20,
                    0,
                    $width - 20,
                    $height
                ];
            }

            // Extract palette using ColorThief
            $color_handler = new PL_Color_Handler();
            $palette = $color_handler->extractPalette($picture, $area);

            if (!empty($palette)) {
                // Save palette to meta
                update_post_meta($attachment_id, '_pl_palette', $palette);
                
                error_log("PL_Async_Palette: Successfully generated palette for {$attachment_id}: " . json_encode($palette));
            } else {
                error_log("PL_Async_Palette: Failed to extract palette for {$attachment_id}");
            }

        } catch (Exception $e) {
            error_log("PL_Async_Palette: Error generating palette for {$attachment_id}: " . $e->getMessage());
        }
    }

    /**
     * Check and schedule palette generation for multiple attachments
     *
     * @param array $attachment_ids Array of attachment IDs
     * @return array Array with scheduled and skipped counts
     */
    public static function schedule_batch(array $attachment_ids): array
    {
        $scheduled = 0;
        $skipped = 0;

        foreach ($attachment_ids as $attachment_id) {
            if (self::schedule_palette_generation($attachment_id)) {
                $scheduled++;
            } else {
                $skipped++;
            }
        }

        return [
            'scheduled' => $scheduled,
            'skipped' => $skipped
        ];
    }
}

// Initialize on plugin load
PL_Async_Palette_Generator::init();

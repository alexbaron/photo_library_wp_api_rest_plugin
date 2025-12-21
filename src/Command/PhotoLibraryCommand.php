<?php

namespace Alex\PhotoLibraryRestApi\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;

require_once dirname(__DIR__) . '/class.photo-library.php';
require_once dirname(__DIR__) . '/database/class.photo-library-db.php';
require_once dirname(__DIR__) . '/database/class.photo-library-schema.php';
require_once dirname(__DIR__) . '/cache/class.photo-library-cache.php';

class PhotoLibraryCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('pl:photolibrary')
            ->setDescription('PhotoLibrary main operations (palettes, cache, stats, pinecone)')
            ->setHelp('Execute various PhotoLibrary operations like palette sync, cache management, statistics and Pinecone operations.')
            ->addArgument('operation', InputArgument::REQUIRED, 'Operation: clear-palettes, sync-palettes, clear-cache, stats, rebuild-palettes, rebuild-pinecone-index')
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Batch size for processing operations', 20)
            ->addOption('max-images', 'm', InputOption::VALUE_REQUIRED, 'Maximum number of images to process (0 = all)', 0)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force execution even if data already exists')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without executing')
            ->addOption('confirm', 'c', InputOption::VALUE_NONE, 'Auto-confirm destructive operations')
            ->addOption('clear-first', null, InputOption::VALUE_NONE, 'Clear Pinecone index before rebuilding');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('PhotoLibrary Operations');

        $operation = $input->getArgument('operation');
        $batchSize = (int) $input->getOption('batch-size');
        $maxImages = (int) $input->getOption('max-images');
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');
        $confirm = $input->getOption('confirm');
        $clearFirst = $input->getOption('clear-first');

        // Validate operation
        $validOperations = [
            'clear-palettes', 'sync-palettes', 'clear-cache',
            'stats', 'rebuild-palettes', 'rebuild-pinecone-index'
        ];

        if (!in_array($operation, $validOperations)) {
            $io->error("Invalid operation. Valid operations: " . implode(', ', $validOperations));
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->note('DRY RUN MODE - No actual changes will be made');
        }

        try {
            switch ($operation) {
                case 'clear-palettes':
                    return $this->clearPalettes($io, $confirm);
                case 'sync-palettes':
                    return $this->syncPalettes($io, $batchSize, $maxImages, $force, $dryRun);
                case 'clear-cache':
                    return $this->clearCache($io, $confirm);
                case 'stats':
                    return $this->showStats($io);
                case 'rebuild-palettes':
                    return $this->rebuildPalettes($io, $batchSize, $maxImages, $confirm);
                case 'rebuild-pinecone-index':
                    return $this->rebuildPineconeIndex($io, $clearFirst, $batchSize, $dryRun);
                default:
                    $io->error("Unknown operation: $operation");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error during operation: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function clearPalettes(SymfonyStyle $io, bool $confirm): int
    {
        if (!$confirm && !$io->confirm('Are you sure you want to delete all palette data?')) {
            $io->note('Operation cancelled');
            return Command::SUCCESS;
        }

        $io->section('🗑️  Clearing Palettes');

        global $wpdb;

        // Delete palettes from meta table
        $deletedMeta = $wpdb->query(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_pl_palette'"
        );

        // Delete palettes from cache table if it exists
        $cacheTable = $wpdb->prefix . 'pl_color_cache';
        $deletedCache = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '{$cacheTable}'") == $cacheTable) {
            $deletedCache = $wpdb->query("TRUNCATE TABLE {$cacheTable}");
        }

        // Clear cache if available
        if (class_exists('PL_Cache_Manager')) {
            \PL_Cache_Manager::flush_all_cache();
        }

        $io->success('✅ Palettes cleared successfully!');
        $io->table(['Type', 'Count'], [
            ['Metadata deleted', $deletedMeta],
            ['Cache entries cleared', $deletedCache],
        ]);

        return Command::SUCCESS;
    }

    private function syncPalettes(SymfonyStyle $io, int $batchSize, int $maxImages, bool $force, bool $dryRun): int
    {
        $io->section('🎨 Synchronizing Color Palettes');

        $io->table(['Parameter', 'Value'], [
            ['Batch Size', $batchSize],
            ['Max Images', $maxImages > 0 ? $maxImages : 'All'],
            ['Force', $force ? 'Yes' : 'No'],
            ['Dry Run', $dryRun ? 'Yes' : 'No'],
        ]);

        global $wpdb;
        $db = new \PL_REST_DB($wpdb);
        $schema = new \PhotoLibrarySchema($db);

        // Count total available images
        $totalQuery = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'";
        $totalAvailable = $wpdb->get_var($totalQuery);
        $totalToProcess = $maxImages > 0 ? min($maxImages, $totalAvailable) : $totalAvailable;

        $io->writeln("📊 Images available: {$totalAvailable}");
        $io->writeln("📊 Images to process: {$totalToProcess}");

        // Progress tracking
        $progressBar = new ProgressBar($output ?? $io, $totalToProcess);
        $progressBar->setFormat('verbose');
        $progressBar->start();

        $processed = 0;
        $errors = 0;
        $skipped = 0;
        $offset = 0;

        // Process in batches
        while ($processed + $skipped + $errors < $totalToProcess) {
            $currentBatchSize = min($batchSize, $totalToProcess - ($processed + $skipped + $errors));

            // Get batch of images
            $pictures = $db->getPicturesForPaletteSync($currentBatchSize, $offset, !$force);
            if (empty($pictures)) {
                break;
            }

            foreach ($pictures as $picture) {
                try {
                    // Check if palette already exists
                    if (!$force && !empty($picture->palette)) {
                        $skipped++;
                        $progressBar->advance();
                        continue;
                    }

                    if ($dryRun) {
                        $processed++;
                    } else {
                        // Process the image
                        $palette = $schema->getPalette($picture);

                        if ($palette && count($palette) > 0) {
                            $processed++;
                        } else {
                            $errors++;
                        }
                    }

                } catch (\Exception $e) {
                    $errors++;
                    $io->writeln("\n❌ Error for image {$picture->id}: " . $e->getMessage());
                }

                $progressBar->advance();
            }

            $offset += count($pictures);

            // Pause between batches to avoid overload
            if (!$dryRun && count($pictures) == $batchSize) {
                sleep(1);
            }
        }

        $progressBar->finish();
        $io->writeln('');

        $io->success($dryRun ? '✅ Simulation completed!' : '✅ Synchronization completed!');
        $io->table(['Metric', 'Count'], [
            ['Images processed', $processed],
            ['Images skipped', $skipped],
            ['Errors', $errors],
            ['Total', $processed + $skipped + $errors],
        ]);

        return Command::SUCCESS;
    }

    private function clearCache(SymfonyStyle $io, bool $confirm): int
    {
        if (!$confirm && !$io->confirm('Are you sure you want to clear the color cache?')) {
            $io->note('Operation cancelled');
            return Command::SUCCESS;
        }

        $io->section('💾 Clearing Color Cache');

        if (class_exists('PL_Cache_Manager')) {
            \PL_Cache_Manager::flush_all_cache();
            $io->success('✅ Cache cleared successfully!');
        } else {
            $io->warning('⚠️  Cache manager not available');
        }

        return Command::SUCCESS;
    }

    private function showStats(SymfonyStyle $io): int
    {
        $io->section('📊 PhotoLibrary Statistics');

        global $wpdb;

        // General statistics
        $totalPictures = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
        );

        $picturesWithPalette = $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'
             AND pm.meta_key = '_pl_palette' AND pm.meta_value != ''"
        );

        $stats = [
            ['📷 Total Images', $totalPictures],
            ['🎨 Images with Palette', $picturesWithPalette],
        ];

        if ($totalPictures > 0) {
            $percentage = round(($picturesWithPalette / $totalPictures) * 100, 2);
            $stats[] = ['📈 Palette Coverage', "{$percentage}%"];
        }

        // Cache stats
        if (class_exists('PL_Cache_Manager')) {
            $stats[] = ['💾 Cache System', 'Active'];
        } else {
            $stats[] = ['💾 Cache System', 'Inactive'];
        }

        $io->table(['Metric', 'Value'], $stats);

        return Command::SUCCESS;
    }

    private function rebuildPalettes(SymfonyStyle $io, int $batchSize, int $maxImages, bool $confirm): int
    {
        if (!$confirm && !$io->confirm('Are you sure you want to rebuild all palettes? (This will delete existing palettes first)')) {
            $io->note('Operation cancelled');
            return Command::SUCCESS;
        }

        $io->section('🔄 Rebuilding All Palettes');

        // Step 1: Clear existing palettes
        $io->writeln('🗑️  Step 1/2: Clearing existing palettes');
        $this->clearPalettes($io, true);

        $io->writeln('');

        // Step 2: Sync all palettes with force
        $io->writeln('🎨 Step 2/2: Rebuilding palettes');
        $this->syncPalettes($io, $batchSize, $maxImages, true, false);

        $io->success('✅ Complete rebuild finished!');

        return Command::SUCCESS;
    }

    private function rebuildPineconeIndex(SymfonyStyle $io, bool $clearFirst, int $batchSize, bool $dryRun): int
    {
        $io->section('🔄 Rebuilding Pinecone Index');

        if ($dryRun) {
            $io->note('🔬 Simulation mode - No modifications will be made');
        }

        // Initialize Pinecone index
        if (!class_exists('PL_Color_Search_Index')) {
            $io->error('❌ PL_Color_Search_Index class not found');
            return Command::FAILURE;
        }

        $colorIndex = new \PL_Color_Search_Index();

        // Test connection
        $io->writeln('🔗 Testing Pinecone connection...');
        $connectionTest = $colorIndex->test_connection();

        if ($connectionTest['status'] === 'error') {
            if (strpos($connectionTest['message'], 'PINECONE_API_KEY') !== false) {
                $io->error('❌ Pinecone API key not configured. Check PINECONE_API_KEY in .env or wp-config.php');
            } else {
                $io->error('❌ Connection failed: ' . $connectionTest['message']);
            }
            return Command::FAILURE;
        }

        $io->success('✅ Pinecone connection OK');

        // Current stats
        $statsBefore = $colorIndex->get_index_stats();
        $io->writeln("📊 Current index statistics:");
        $io->writeln("   Total vectors: " . $statsBefore['total_vectors']);

        // Clear index if requested
        if ($clearFirst) {
            $io->writeln('🗑️  Clearing Pinecone index...');
            if (!$dryRun) {
                $clearSuccess = $colorIndex->clear_index();
                if ($clearSuccess) {
                    $io->success('✅ Index cleared successfully');
                } else {
                    $io->error('❌ Failed to clear index');
                    return Command::FAILURE;
                }
            } else {
                $io->writeln('   [SIMULATION] Index would be cleared');
            }
        }

        // Get all photos with palettes
        global $wpdb;
        $io->writeln('🔍 Finding photos with palettes...');

        $query = "
            SELECT p.ID, pm.meta_value as palette_data, p.post_title
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND pm.meta_key = '_pl_palette'
            AND pm.meta_value != ''
            AND pm.meta_value IS NOT NULL
            ORDER BY p.ID ASC
        ";

        $photosWithPalettes = $wpdb->get_results($query);

        if (empty($photosWithPalettes)) {
            $io->error('❌ No photos with palettes found. Run sync-palettes first');
            return Command::FAILURE;
        }

        $io->success('✅ ' . count($photosWithPalettes) . ' photos with palettes found');

        // Prepare data for Pinecone
        $io->writeln('🎨 Preparing color data...');
        $photosToSync = [];
        $processed = 0;
        $skipped = 0;

        $progressBar = new ProgressBar($output ?? $io, count($photosWithPalettes));
        $progressBar->setFormat('verbose');
        $progressBar->start();

        foreach ($photosWithPalettes as $photo) {
            $progressBar->advance();

            $palette = unserialize($photo->palette_data);

            if (!is_array($palette) || empty($palette)) {
                $skipped++;
                continue;
            }

            // Extract dominant color
            $dominantColor = null;
            if (isset($palette[0]) && is_array($palette[0]) && count($palette[0]) >= 3) {
                $dominantColor = $palette[0];
            } elseif (isset($palette['dominant']) && is_array($palette['dominant'])) {
                $dominantColor = $palette['dominant'];
            } elseif (is_array($palette) && count($palette) >= 3 && is_numeric($palette[0])) {
                $dominantColor = $palette;
            }

            if ($dominantColor === null || count($dominantColor) < 3) {
                $skipped++;
                continue;
            }

            // Validate RGB values
            $rgb = array_map('intval', array_slice($dominantColor, 0, 3));
            if ($rgb[0] < 0 || $rgb[0] > 255 || $rgb[1] < 0 || $rgb[1] > 255 || $rgb[2] < 0 || $rgb[2] > 255) {
                $skipped++;
                continue;
            }

            $photosToSync[] = [
                'id' => 'img_' . $photo->ID,
                'rgb' => $rgb,
                'metadata' => [
                    'photo_id' => $photo->ID,
                    'title' => $photo->post_title,
                    'source' => 'photolibrary_rebuild'
                ]
            ];

            $processed++;
        }

        $progressBar->finish();
        $io->writeln('');

        $io->table(['Metric', 'Count'], [
            ['Photos processed', $processed],
            ['Photos skipped', $skipped],
        ]);

        if (empty($photosToSync)) {
            $io->error('❌ No valid photos to sync');
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->note('🔬 Simulation - Here is what would be synchronized:');
            $io->writeln("   Number of photos: " . count($photosToSync));
            $io->writeln("   Batch size: " . $batchSize);
            $io->writeln("   Number of batches: " . ceil(count($photosToSync) / $batchSize));

            // Show some examples
            $examples = array_slice($photosToSync, 0, 3);
            $io->writeln('');
            $io->writeln('📋 Examples (first 3):');
            foreach ($examples as $example) {
                $io->writeln("   Photo {$example['metadata']['photo_id']}: RGB(" . implode(',', $example['rgb']) . ")");
            }

            return Command::SUCCESS;
        }

        // Upload to Pinecone in batches
        $io->writeln('📤 Uploading to Pinecone...');
        $batches = array_chunk($photosToSync, $batchSize);
        $uploaded = 0;
        $errors = 0;

        foreach ($batches as $batchIndex => $batch) {
            $io->writeln("📦 Processing batch " . ($batchIndex + 1) . "/" . count($batches));
            $uploadResult = $colorIndex->batch_upsert_photos($batch);

            if (isset($uploadResult['success_count']) && $uploadResult['success_count'] > 0) {
                $uploaded += $uploadResult['success_count'];
                $io->writeln("✅ Batch uploaded: " . $uploadResult['success_count'] . " vectors");
            } else {
                $errors++;
                $errorMsg = isset($uploadResult['error_count']) ? "Errors: {$uploadResult['error_count']}" : "Unknown error";
                $io->writeln("❌ Batch failed: " . $errorMsg);
						}
            // Small pause between batches
            sleep(1);
        }

        // Final statistics
        $statsAfter = $colorIndex->get_index_stats();
        $io->success('✅ Pinecone index rebuild completed!');
        $io->table(['Metric', 'Value'], [
            ['Vectors uploaded', $uploaded],
            ['Batch errors', $errors],
            ['Final index size', $statsAfter['total_vectors']],
        ]);

        return Command::SUCCESS;
    }
}

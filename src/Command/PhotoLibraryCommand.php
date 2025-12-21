<?php

namespace Alex\PhotoLibraryRestApi\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Alex\PhotoLibraryRestApi\Pinecone\PhotoLibraryPinecone;

class PhotoLibraryCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('pl:photolibrary')
            ->setDescription('PhotoLibrary main operations (palettes, cache, stats, pinecone)')
            ->setHelp('Execute various PhotoLibrary operations like palette sync, cache management, statistics and Pinecone operations.')
            ->addArgument('operation', InputArgument::REQUIRED, 'Operation to execute (clear-palettes, sync-palettes, clear-cache, stats, rebuild-palettes, rebuild-pinecone-index)')
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
        $operation = $input->getArgument('operation');
        $batchSize = (int) $input->getOption('batch-size');
        $maxImages = (int) $input->getOption('max-images');
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');
        $confirm = $input->getOption('confirm');
        $clearFirst = $input->getOption('clear-first');

        $io->title("PhotoLibrary: {$operation}");

        try {
            switch ($operation) {
                case 'clear-palettes':
                    return $this->clearPalettes($io, $dryRun, $confirm);

                case 'sync-palettes':
                    return $this->syncPalettes($io, $batchSize, $maxImages, $force, $dryRun);

                case 'clear-cache':
                    return $this->clearCache($io, $dryRun, $confirm);

                case 'stats':
                    return $this->showStats($io);

                case 'rebuild-palettes':
                    return $this->rebuildPalettes($io, $batchSize, $maxImages, $force, $dryRun);

                case 'rebuild-pinecone-index':
                    return $this->rebuildPineconeIndex($io, $batchSize, $maxImages, $clearFirst, $dryRun);

                default:
                    $io->error("Unknown operation: {$operation}");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error("Error executing {$operation}: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function clearPalettes(SymfonyStyle $io, bool $dryRun, bool $confirm): int
    {
        global $wpdb;

        $io->section('Clear Color Palettes');

        // Count existing palettes
        $count = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->postmeta}
            WHERE meta_key LIKE 'palette_%'
        ");

        if ($count == 0) {
            $io->info('No color palettes found to delete.');
            return Command::SUCCESS;
        }

        $io->text("Found {$count} palette entries to delete.");

        if ($dryRun) {
            $io->note('DRY RUN - No data will be deleted');
            return Command::SUCCESS;
        }

        if (!$confirm && !$io->confirm("Delete all {$count} palette entries?", false)) {
            $io->warning('Operation cancelled.');
            return Command::SUCCESS;
        }

        $deleted = $wpdb->query("
            DELETE FROM {$wpdb->postmeta}
            WHERE meta_key LIKE 'palette_%'
        ");

        $io->success("Deleted {$deleted} palette entries.");
        return Command::SUCCESS;
    }

    private function syncPalettes(SymfonyStyle $io, int $batchSize, int $maxImages, bool $force, bool $dryRun): int
    {
        global $wpdb;

        $io->section('Sync Color Palettes');

        // Safety: if maxImages is 0, set a reasonable default to prevent infinite loops
        if ($maxImages == 0) {
            $maxImages = 100; // Default limit to prevent accidental mass processing
            $io->note("Setting default limit to {$maxImages} photos (use --max-images=X for different limit)");
        }

        // Get photos without palettes or force all
        $where = $force ? '' : "AND m.post_id IS NULL";

        $query = "
            SELECT p.ID, p.post_title
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = 'palette_dominant_color'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            {$where}
            ORDER BY p.ID ASC
            LIMIT {$maxImages}
        ";

        $photos = $wpdb->get_results($query);
        $total = count($photos);

        if ($total == 0) {
            $io->info('No photos found for palette sync.');
            return Command::SUCCESS;
        }

        $io->text("Processing {$total} photos with batch size {$batchSize}");

        if ($dryRun) {
            $io->note('DRY RUN - No palettes will be generated');
            foreach ($photos as $index => $photo) {
                if ($index < 5) { // Show first 5 as example
                    $io->text("Would process: Photo ID {$photo->ID} - {$photo->post_title}");
                }
            }
            if ($total > 5) {
                $io->text("... and " . ($total - 5) . " more photos");
            }
            return Command::SUCCESS;
        }

        $processed = 0;
        $batches = array_chunk($photos, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            $io->text("Processing batch " . ($batchIndex + 1) . "/" . count($batches));

            foreach ($batch as $photo) {
                // TODO: Implement actual palette generation logic here
                // For now, this is just a placeholder to avoid infinite loops

                $io->text("Processing photo ID: {$photo->ID}");
                $processed++;

                // Simulate some processing time to make it visible
                usleep(100000); // 0.1 second delay

                if ($processed % 10 == 0) {
                    $io->text("Processed {$processed}/{$total} photos");
                }
            }
        }

        $io->success("Successfully processed {$processed} photos.");
        $io->note("Note: This is currently a simulation. Actual palette generation logic needs to be implemented.");
        return Command::SUCCESS;
    }

    private function clearCache(SymfonyStyle $io, bool $dryRun, bool $confirm): int
    {
        $io->section('Clear PhotoLibrary Cache');

        if ($dryRun) {
            $io->note('DRY RUN - No cache will be cleared');
            return Command::SUCCESS;
        }

        if (!$confirm && !$io->confirm('Clear all PhotoLibrary cache?', false)) {
            $io->warning('Operation cancelled.');
            return Command::SUCCESS;
        }

        // Clear WordPress cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        $io->success('Cache cleared successfully.');
        return Command::SUCCESS;
    }

    private function showStats(SymfonyStyle $io): int
    {
        global $wpdb;

        $io->section('PhotoLibrary Statistics');

        // Total photos
        $totalPhotos = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts}
            WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'
        ");

        // Photos with palettes
        $photosWithPalettes = $wpdb->get_var("
            SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
            WHERE meta_key = 'palette_dominant_color'
        ");

        // Palette entries
        $paletteEntries = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->postmeta}
            WHERE meta_key LIKE 'palette_%'
        ");

        $io->definitionList(
            ['Total Photos', $totalPhotos],
            ['Photos with Palettes', $photosWithPalettes],
            ['Total Palette Entries', $paletteEntries],
            ['Coverage', sprintf('%.1f%%', ($totalPhotos > 0) ? ($photosWithPalettes / $totalPhotos * 100) : 0)]
        );

        return Command::SUCCESS;
    }

    private function rebuildPalettes(SymfonyStyle $io, int $batchSize, int $maxImages, bool $force, bool $dryRun): int
    {
        $io->section('Rebuild Color Palettes');
        $io->note('This will force regenerate palettes for all photos');

        return $this->syncPalettes($io, $batchSize, $maxImages, true, $dryRun);
    }

    private function rebuildPineconeIndex(SymfonyStyle $io, int $batchSize, int $maxImages, bool $clearFirst, bool $dryRun): int
    {
        global $wpdb;

        $io->section('Rebuild Pinecone Index');

        try {
            $pinecone = new PhotoLibraryPinecone();

            if ($clearFirst) {
                $io->text('Clearing Pinecone index...');
                if (!$dryRun) {
                    $pinecone->clear_index();
                }
                $io->info('Index cleared.');
            }

            // Get photos with dominant color
            $query = "
                SELECT p.ID, pm.meta_value as dominant_color
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'attachment'
                AND p.post_mime_type LIKE 'image/%'
                AND pm.meta_key = 'palette_dominant_color'
                AND pm.meta_value IS NOT NULL
                ORDER BY p.ID ASC
            ";

            if ($maxImages > 0) {
                $query .= " LIMIT {$maxImages}";
            }

            $photos = $wpdb->get_results($query);
            $total = count($photos);

            if ($total == 0) {
                $io->warning('No photos with dominant colors found for Pinecone indexing.');
                return Command::SUCCESS;
            }

            $io->text("Found {$total} photos to index with batch size {$batchSize}");

            if ($dryRun) {
                $io->note('DRY RUN - No vectors will be uploaded to Pinecone');
                return Command::SUCCESS;
            }

            $processed = 0;
            $errors = [];
            $batches = array_chunk($photos, $batchSize);

            foreach ($batches as $batchIndex => $batch) {
                $io->text("Processing batch " . ($batchIndex + 1) . "/" . count($batches));

                $vectors = [];
                foreach ($batch as $photo) {
                    if (empty($photo->dominant_color)) continue;

                    $rgb = explode(',', $photo->dominant_color);
                    if (count($rgb) >= 3) {
                        $vectors[] = [
                            'id' => (string)$photo->ID,
                            'values' => [(float)$rgb[0], (float)$rgb[1], (float)$rgb[2]],
                            'metadata' => ['dominant_color' => $photo->dominant_color]
                        ];
                    }
                }

                if (!empty($vectors)) {
                    try {
                        $result = $pinecone->batch_upsert_photos($vectors);
                        if ($result) {
                            $processed += count($vectors);
                        } else {
                            $errors[] = "Batch " . ($batchIndex + 1) . " failed";
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Batch " . ($batchIndex + 1) . ": " . $e->getMessage();
                    }
                }

                if ($processed % 50 == 0 || $batchIndex == count($batches) - 1) {
                    $io->text("Processed {$processed}/{$total} photos");
                }
            }

            if (!empty($errors)) {
                $io->warning('Some errors occurred:');
                foreach ($errors as $error) {
                    $io->text("❌ {$error}");
                }
            }

            $io->success("Successfully indexed {$processed} photos in Pinecone.");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("Failed to rebuild Pinecone index: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

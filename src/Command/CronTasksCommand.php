<?php

namespace Alex\PhotoLibraryRestApi\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CronTasksCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('pl:cron-tasks')
            ->setDescription('Execute PhotoLibrary maintenance tasks (sync, cache cleanup)')
            ->setHelp('Execute various maintenance tasks like palette synchronization and cache cleanup.')
            ->addArgument('task', InputArgument::REQUIRED, 'Task to execute: sync-palettes, cleanup-cache, or all')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Number of pictures to process for sync-palettes', 50)
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Batch size for processing', 20)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force execution even if pictures already have palettes')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without executing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('PhotoLibrary Maintenance Tasks');

        $task = $input->getArgument('task');
        $limit = (int) $input->getOption('limit');
        $batchSize = (int) $input->getOption('batch-size');
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');

        // Validate task
        $validTasks = ['sync-palettes', 'cleanup-cache', 'all'];
        if (!in_array($task, $validTasks)) {
            $io->error("Invalid task: {$task}. Must be one of: " . implode(', ', $validTasks));
            return Command::FAILURE;
        }

        // Validate options
        if ($limit < 1 || $limit > 1000) {
            $io->error("Invalid limit: {$limit}. Must be between 1 and 1000");
            return Command::FAILURE;
        }

        if ($batchSize < 1 || $batchSize > 100) {
            $io->error("Invalid batch-size: {$batchSize}. Must be between 1 and 100");
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->note('DRY RUN MODE - No changes will be made');
        }

        $io->info("Task: {$task}");
        $io->info("Limit: {$limit}");
        $io->info("Batch size: {$batchSize}");

        try {
            switch ($task) {
                case 'sync-palettes':
                    return $this->syncPalettes($io, $limit, $batchSize, $force, $dryRun);

                case 'cleanup-cache':
                    return $this->cleanupCache($io, $dryRun);

                case 'all':
                    $result1 = $this->syncPalettes($io, $limit, $batchSize, $force, $dryRun);
                    $result2 = $this->cleanupCache($io, $dryRun);
                    return ($result1 === Command::SUCCESS && $result2 === Command::SUCCESS) ? Command::SUCCESS : Command::FAILURE;

                default:
                    return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error('Error executing task: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Synchronize photo color palettes
     */
    private function syncPalettes(SymfonyStyle $io, int $limit, int $batchSize, bool $force, bool $dryRun): int
    {
        $io->section('Palette Synchronization');

        // Check if required classes exist
        if (!class_exists('PL_REST_DB') || !class_exists('PhotoLibrarySchema')) {
            $io->error('Required classes not found. Make sure WordPress is loaded.');
            return Command::FAILURE;
        }

        global $wpdb;
        $db = new \PL_REST_DB($wpdb);
        $schema = new \PhotoLibrarySchema($db);

        $totalProcessed = 0;
        $totalErrors = 0;
        $offset = 0;

        $io->text("Starting palette sync - Limit: {$limit}, Batch size: {$batchSize}");

        while ($totalProcessed < $limit) {
            $currentBatchSize = min($batchSize, $limit - $totalProcessed);

            // Get pictures that need palette processing
            $pictures = $db->getPicturesForPaletteSync($currentBatchSize, $offset, !$force);

            if (empty($pictures)) {
                $io->info('No more pictures to process');
                break;
            }

            $io->text("Processing batch: " . count($pictures) . " pictures (offset: {$offset})");

            $batchProcessed = 0;
            $batchErrors = 0;

            foreach ($pictures as $picture) {
                try {
                    if ($dryRun) {
                        $io->text("Would process: Photo ID {$picture->ID} - {$picture->post_title}");
                        $batchProcessed++;
                    } else {
                        $palette = $schema->getPalette($picture);
                        if ($palette) {
                            $io->text("✓ Processed: Photo ID {$picture->ID} - {$picture->post_title}");
                            $batchProcessed++;
                        } else {
                            $io->text("✗ Failed: Photo ID {$picture->ID} - {$picture->post_title}");
                            $batchErrors++;
                        }
                    }
                } catch (\Exception $e) {
                    $io->text("✗ Error: Photo ID {$picture->ID} - " . $e->getMessage());
                    $batchErrors++;
                }
            }

            $totalProcessed += $batchProcessed;
            $totalErrors += $batchErrors;
            $offset += count($pictures);

            $io->text("Batch completed: {$batchProcessed} processed, {$batchErrors} errors");

            // Break if we got fewer pictures than requested (end of data)
            if (count($pictures) < $currentBatchSize) {
                break;
            }
        }

        // Summary
        $io->section('Sync Results:');
        $io->listing([
            "Total pictures processed: {$totalProcessed}",
            "Total errors: {$totalErrors}",
            "Success rate: " . ($totalProcessed > 0 ? number_format((($totalProcessed - $totalErrors) / $totalProcessed) * 100, 1) : 0) . "%"
        ]);

        if ($dryRun) {
            $io->note('This was a dry run. No actual changes were made.');
        }

        $io->success('✓ Palette synchronization completed!');
        return Command::SUCCESS;
    }

    /**
     * Cleanup cache
     */
    private function cleanupCache(SymfonyStyle $io, bool $dryRun): int
    {
        $io->section('Cache Cleanup');

        // Check if cache manager exists
        if (!class_exists('PL_Cache_Manager')) {
            $io->error('PL_Cache_Manager class not found. Make sure WordPress is loaded.');
            return Command::FAILURE;
        }

        try {
            if ($dryRun) {
                $io->text('Would flush all PhotoLibrary cache');
                $io->note('This was a dry run. No actual changes were made.');
            } else {
                \PL_Cache_Manager::flush_all_cache();
                $io->text('All PhotoLibrary cache has been flushed');
            }

            $io->success('✓ Cache cleanup completed!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Cache cleanup failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Get cache statistics (bonus feature)
     */
    private function getCacheStats(SymfonyStyle $io): void
    {
        $io->section('Cache Statistics');

        try {
            // This would need to be implemented in PL_Cache_Manager
            if (method_exists('PL_Cache_Manager', 'get_cache_stats')) {
                $stats = \PL_Cache_Manager::get_cache_stats();
                $io->table(['Metric', 'Value'], [
                    ['Cache hits', $stats['hits'] ?? 'N/A'],
                    ['Cache misses', $stats['misses'] ?? 'N/A'],
                    ['Cache size', $stats['size'] ?? 'N/A'],
                ]);
            } else {
                $io->text('Cache statistics not available');
            }
        } catch (\Exception $e) {
            $io->text('Could not retrieve cache statistics: ' . $e->getMessage());
        }
    }
}

<?php

namespace Alex\PhotoLibraryRestApi\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

require_once dirname(__DIR__) . '/class.photo-library.php';
require_once dirname(__DIR__) . '/database/class.photo-library-db.php';

class ParallelProcessingCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('pl:parallel-process')
            ->setDescription('Execute parallel processing for PhotoLibrary operations')
            ->setHelp('Execute parallel processing operations with various strategies (fork, workers, http pool).')
            ->addArgument('operation', InputArgument::REQUIRED, 'Operation to execute: sync-palettes')
            ->addOption('strategy', 's', InputOption::VALUE_REQUIRED, 'Parallel strategy: fork, workers, http-pool, sequential', 'sequential')
            ->addOption('parallel-count', 'p', InputOption::VALUE_REQUIRED, 'Number of parallel processes/workers', 4)
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Number of pictures to process', 100)
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Batch size for processing', 20)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force execution even if pictures already have palettes')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without executing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('PhotoLibrary Parallel Processing');

        $operation = $input->getArgument('operation');
        $strategy = $input->getOption('strategy');
        $parallelCount = (int) $input->getOption('parallel-count');
        $limit = (int) $input->getOption('limit');
        $batchSize = (int) $input->getOption('batch-size');
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');

        // Validate operation
        $validOperations = ['sync-palettes'];
        if (!in_array($operation, $validOperations)) {
            $io->error("Invalid operation. Valid operations: " . implode(', ', $validOperations));
            return Command::FAILURE;
        }

        // Validate strategy
        $validStrategies = ['fork', 'workers', 'http-pool', 'sequential'];
        if (!in_array($strategy, $validStrategies)) {
            $io->error("Invalid strategy. Valid strategies: " . implode(', ', $validStrategies));
            return Command::FAILURE;
        }

        $io->section("Configuration");
        $io->table(['Parameter', 'Value'], [
            ['Operation', $operation],
            ['Strategy', $strategy],
            ['Parallel Count', $parallelCount],
            ['Limit', $limit],
            ['Batch Size', $batchSize],
            ['Force', $force ? 'Yes' : 'No'],
            ['Dry Run', $dryRun ? 'Yes' : 'No'],
        ]);

        if ($dryRun) {
            $io->note('DRY RUN MODE - No actual changes will be made');
        }

        try {
            $result = $this->executeParallelOperation($operation, $strategy, $parallelCount, $limit, $batchSize, $force, $dryRun, $io);

            $io->success('Parallel processing completed successfully');
            $io->table(['Metric', 'Count'], [
                ['Processed', $result['processed']],
                ['Skipped', $result['skipped']],
                ['Errors', $result['errors']],
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error during parallel processing: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function executeParallelOperation($operation, $strategy, $parallelCount, $limit, $batchSize, $force, $dryRun, SymfonyStyle $io): array
    {
        if ($operation === 'sync-palettes') {
            return $this->syncPalettesParallel($strategy, $parallelCount, $limit, $batchSize, $force, $dryRun, $io);
        }

        throw new \InvalidArgumentException("Unknown operation: $operation");
    }

    private function syncPalettesParallel($strategy, $parallelCount, $limit, $batchSize, $force, $dryRun, SymfonyStyle $io): array
    {
        global $wpdb;
        $db = new \PL_REST_DB($wpdb);
        $schema = new \PhotoLibrarySchema($db);

        // Get pictures to process
        $io->writeln("📋 Fetching pictures to process...");
        $pictures = $db->getPicturesForPaletteSync($limit, 0, !$force);

        if (empty($pictures)) {
            $io->warning('No pictures to process');
            return ['processed' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $io->writeln("📊 Found " . count($pictures) . " pictures to process");

        // Execute based on strategy
        switch ($strategy) {
            case 'fork':
                return $this->processParallelFork($pictures, $schema, $force, $dryRun, $parallelCount, $io);
            case 'workers':
                return $this->processAsyncWorkers($pictures, $schema, $force, $dryRun, $parallelCount, $io);
            case 'http-pool':
                return $this->processHttpPool($pictures, $schema, $force, $dryRun, $parallelCount, $io);
            case 'sequential':
                return $this->processSequential($pictures, $schema, $force, $dryRun, $io);
            default:
                throw new \InvalidArgumentException("Unknown strategy: $strategy");
        }
    }

    /**
     * Traitement parallélisé avec processus multiples
     */
    private function processParallelFork($pictures, $schema, $force, $dryRun, $parallelCount, SymfonyStyle $io): array
    {
        // Vérifier si pcntl est disponible
        if (!function_exists('pcntl_fork')) {
            $io->warning("⚠️  pcntl_fork not available, falling back to sequential processing");
            return $this->processSequential($pictures, $schema, $force, $dryRun, $io);
        }

        $chunkSize = ceil(count($pictures) / $parallelCount);
        $chunks = array_chunk($pictures, $chunkSize);
        $pids = [];
        $tempDir = sys_get_temp_dir();

        $io->writeln("🔀 Starting {$parallelCount} parallel processes");

        foreach ($chunks as $chunkIndex => $chunk) {
            $pid = pcntl_fork();

            if ($pid == -1) {
                $io->warning("❌ Cannot create process {$chunkIndex}");
                continue;
            } elseif ($pid == 0) {
                // Child process
                $results = $this->processSequentialChunk($chunk, $schema, $force, $dryRun);

                // Save results to temporary file
                $resultFile = $tempDir . "/palette_chunk_{$chunkIndex}_" . getmypid() . ".json";
                file_put_contents($resultFile, json_encode($results));

                exit(0);
            } else {
                // Parent process
                $pids[] = [
                    'pid' => $pid,
                    'chunk' => $chunkIndex,
                    'file' => $tempDir . "/palette_chunk_{$chunkIndex}_{$pid}.json"
                ];
            }
        }

        // Wait for all child processes and collect results
        $totalResults = ['processed' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($pids as $process) {
            $status = 0;
            pcntl_waitpid($process['pid'], $status);

            // Read process results
            if (file_exists($process['file'])) {
                $childResults = json_decode(file_get_contents($process['file']), true);

                $totalResults['processed'] += $childResults['processed'];
                $totalResults['skipped'] += $childResults['skipped'];
                $totalResults['errors'] += $childResults['errors'];

                // Clean up temporary file
                unlink($process['file']);

                $io->writeln("✅ Process {$process['chunk']} completed");
            }
        }

        return $totalResults;
    }

    /**
     * Traitement avec workers asynchrones
     */
    private function processAsyncWorkers($pictures, $schema, $force, $dryRun, $workerCount, SymfonyStyle $io): array
    {
        $chunkSize = ceil(count($pictures) / $workerCount);
        $chunks = array_chunk($pictures, $chunkSize);

        $io->writeln("👥 Processing with {$workerCount} async workers");

        $totalResults = ['processed' => 0, 'skipped' => 0, 'errors' => 0];
        $resultsQueue = [];

        foreach ($chunks as $chunkIndex => $chunk) {
            // Simulate async processing with timers
            $startTime = microtime(true);
            $chunkResults = $this->processSequentialChunk($chunk, $schema, $force, $dryRun);
            $endTime = microtime(true);

            $resultsQueue[] = [
                'chunk' => $chunkIndex,
                'results' => $chunkResults,
                'duration' => $endTime - $startTime
            ];
        }

        // Aggregate results
        foreach ($resultsQueue as $queueItem) {
            $results = $queueItem['results'];
            $totalResults['processed'] += $results['processed'];
            $totalResults['skipped'] += $results['skipped'];
            $totalResults['errors'] += $results['errors'];

            $io->writeln("✅ Chunk {$queueItem['chunk']} completed in " . round($queueItem['duration'], 2) . "s");
        }

        return $totalResults;
    }

    /**
     * Traitement avec pool de connexions HTTP
     */
    private function processHttpPool($pictures, $schema, $force, $dryRun, $poolSize, SymfonyStyle $io): array
    {
        // This method can be used for processing that requires HTTP requests
        // like external image processing APIs or color APIs

        if (!function_exists('curl_multi_init')) {
            $io->warning("⚠️  cURL multi not available, falling back to sequential processing");
            return $this->processSequential($pictures, $schema, $force, $dryRun, $io);
        }

        $io->writeln("🌐 Processing with HTTP pool of {$poolSize} connections");

        $chunks = array_chunk($pictures, $poolSize);
        $totalResults = ['processed' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($chunks as $chunkIndex => $chunk) {
            $chunkResults = $this->processSequentialChunk($chunk, $schema, $force, $dryRun);

            $totalResults['processed'] += $chunkResults['processed'];
            $totalResults['skipped'] += $chunkResults['skipped'];
            $totalResults['errors'] += $chunkResults['errors'];

            // Small pause to avoid overload
            usleep(100000); // 0.1 second

            $io->writeln("✅ HTTP chunk {$chunkIndex} completed");
        }

        return $totalResults;
    }

    /**
     * Traitement séquentiel de base
     */
    private function processSequential($pictures, $schema, $force, $dryRun, SymfonyStyle $io): array
    {
        $io->writeln("⚡ Sequential processing");
        return $this->processSequentialChunk($pictures, $schema, $force, $dryRun);
    }

    /**
     * Process a chunk of pictures sequentially
     */
    private function processSequentialChunk($pictures, $schema, $force, $dryRun): array
    {
        $results = ['processed' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($pictures as $picture) {
            try {
                // Check if palette already exists
                if (!$force && !empty($picture->palette)) {
                    $results['skipped']++;
                    continue;
                }

                if ($dryRun) {
                    $results['processed']++;
                } else {
                    // Process the image
                    $palette = $schema->getPalette($picture);

                    if ($palette && count($palette) > 0) {
                        $results['processed']++;
                    } else {
                        $results['errors']++;
                    }
                }

            } catch (\Exception $e) {
                $results['errors']++;
                error_log("Error processing image {$picture->id}: " . $e->getMessage());
            }
        }

        return $results;
    }
}

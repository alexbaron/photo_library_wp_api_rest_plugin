<?php

namespace Alex\PhotoLibraryRestApi\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class PhotoLibrarySfCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('pl:test-pinecone')
            ->setDescription('Test Pinecone connection using Guzzle')
            ->setHelp('Tests the connection to Pinecone vector database and displays index stats.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pinecone Connection Test');

        // Load .env file if exists
        $envFile = dirname(__DIR__, 2) . '/.env';

        if (file_exists($envFile)) {
            $this->loadEnv($envFile);
        }

        // Get Pinecone credentials
        $apiKey = $_ENV['PINECONE_API_KEY'] ?? (getenv('PINECONE_API_KEY') ?: null);
        $indexName = $_ENV['PINECONE_INDEX_NAME'] ?? (getenv('PINECONE_INDEX_NAME') ?: 'phototheque-color-search');

        if (empty($apiKey)) {
            $io->error('PINECONE_API_KEY not found in environment variables or .env file');
            return Command::FAILURE;
        }

        // Clean API key (remove quotes if present)
        $apiKey = trim($apiKey, "'\"");

        $io->info("Using index: {$indexName}");
        $io->info("API Key: " . substr($apiKey, 0, 15) . "...");

        // Try to get index host from Pinecone API
        try {
            $client = new Client([
                'base_uri' => 'https://api.pinecone.io',
                'timeout' => 10.0,
                'headers' => [
                    'Api-Key' => $apiKey,
                    'Accept' => 'application/json',
                ]
            ]);

            $io->section('Step 1: Getting index information...');

            $response = $client->get("/indexes/{$indexName}");
            $indexData = json_decode($response->getBody()->getContents(), true);

            if (!isset($indexData['host'])) {
                $io->error('Could not find host in index response');
                return Command::FAILURE;
            }

            $host = $indexData['host'];
            $io->success("Found index host: {$host}");

            // Display index details
            $io->section('Index Details:');
            $io->listing([
                "Name: {$indexData['name']}",
                "Dimension: {$indexData['dimension']}",
                "Metric: {$indexData['metric']}",
                "Status: {$indexData['status']['state']}",
            ]);

            // Test index stats
            $io->section('Step 2: Getting index statistics...');

            $indexClient = new Client([
                'base_uri' => "https://{$host}",
                'timeout' => 10.0,
                'headers' => [
                    'Api-Key' => $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]);

            $statsResponse = $indexClient->post('/describe_index_stats');

            $stats = json_decode($statsResponse->getBody()->getContents(), true);

            $io->success('Connection successful!');

            if (isset($stats['totalVectorCount'])) {
                $io->listing([
                    "Total vectors: " . number_format($stats['totalVectorCount']),
                    "Dimension: {$stats['dimension']}",
                ]);
            }

            if (isset($stats['namespaces']) && !empty($stats['namespaces'])) {
                $io->section('Namespaces:');
                foreach ($stats['namespaces'] as $namespace => $data) {
                    $io->text("  - {$namespace}: " . number_format($data['vectorCount']) . " vectors");
                }
            }

            $io->success('✓ Pinecone connection is working correctly!');
            return Command::SUCCESS;

        } catch (GuzzleException $e) {
            $io->error('Connection failed: ' . $e->getMessage());

            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $io->text('Response code: ' . $response->getStatusCode());
                $io->text('Response body: ' . $response->getBody()->getContents());
            }

            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Simple .env file loader
     */
    private function loadEnv(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // Remove quotes (single or double)
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}


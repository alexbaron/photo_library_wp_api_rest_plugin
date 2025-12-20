<?php

namespace Alex\PhotoLibraryRestApi\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ColorSearchTestCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('pl:test-color-search')
            ->setDescription('Test color-based search using WordPress REST API')
            ->setHelp('Searches for photos with similar dominant colors using the WordPress /pictures/by_dominant_color endpoint.')
            ->addArgument('color', InputArgument::REQUIRED, 'RGB color to search for (format: "r,g,b" - use quotes!)')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Number of results to return', 10)
            ->addOption('tolerance', 't', InputOption::VALUE_REQUIRED, 'Color tolerance (0-255)', 30)
            ->addOption('method', 'm', InputOption::VALUE_REQUIRED, 'Distance calculation method (euclidean, manhattan, weighted)', 'euclidean')
            ->addOption('wp-url', null, InputOption::VALUE_REQUIRED, 'WordPress site URL', 'https://phototheque-wp.ddev.site');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Color-Based Photo Search Test');

        // Parse color argument
        $colorArg = $input->getArgument('color');
        $rgbValues = $this->parseRgbColor($colorArg);

        if (!$rgbValues) {
            $io->error('Invalid color format. Use "r,g,b" format with quotes (e.g., "255,128,64")');
            $io->text('Example: wp pl test-color-search "255,0,0" --method=euclidean');
            $io->text('Available methods: euclidean, manhattan, weighted');
            return Command::FAILURE;
        }

        $limit = (int) $input->getOption('limit');
        $tolerance = (int) $input->getOption('tolerance');
        $method = $input->getOption('method');
        $wpUrl = rtrim($input->getOption('wp-url'), '/');

        // Validate method
        if (!in_array($method, ['euclidean', 'manhattan', 'weighted'])) {
            $io->error("Invalid method: {$method}. Must be one of: euclidean, manhattan, weighted");
            return Command::FAILURE;
        }

        // Validate tolerance
        if ($tolerance < 0 || $tolerance > 255) {
            $io->error("Invalid tolerance: {$tolerance}. Must be between 0 and 255");
            return Command::FAILURE;
        }

        // Validate limit
        if ($limit < 1 || $limit > 100) {
            $io->error("Invalid limit: {$limit}. Must be between 1 and 100");
            return Command::FAILURE;
        }

        $io->info("Searching for photos with color similar to RGB({$rgbValues[0]}, {$rgbValues[1]}, {$rgbValues[2]})");
        $io->info("Limit: {$limit}");
        $io->info("Tolerance: {$tolerance}");
        $io->info("Method: {$method}");
        $io->info("WordPress URL: {$wpUrl}");

try {
            // First, test if the API namespace is available
            $io->section('Step 1: Testing API availability...');

            $testClient = new Client([
                'timeout' => 30.0,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'verify' => false  // Disable SSL verification for DDEV
            ]);

            // Test the base API endpoint
            $testUrl = "{$wpUrl}/wp-json/photo-library/v1/test";
            $io->text("Testing API: {$testUrl}");

            try {
                $testResponse = $testClient->get($testUrl);
                $io->success("API is available (Status: " . $testResponse->getStatusCode() . ")");
            } catch (GuzzleException $e) {
                $io->error("API not available: " . $e->getMessage());
                return Command::FAILURE;
            }

            // Call WordPress REST API endpoint
            $io->section('Step 2: Calling Color Search Endpoint...');

            $client = new Client([
                'timeout' => 30.0,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'verify' => false  // Disable SSL verification for DDEV
            ]);

            $apiUrl = "{$wpUrl}/wp-json/photo-library/v1/pictures/by_dominant_color";
            $io->text("API URL: {$apiUrl}");

            $payload = [
                'rgb' => $rgbValues,
                'tolerance' => $tolerance,
                'limit' => $limit,
                'method' => $method
            ];

            $io->text("Payload: " . json_encode($payload));

            $response = $client->post($apiUrl, ['json' => $payload]);
            $responseBody = $response->getBody()->getContents();
            $statusCode = $response->getStatusCode();

            $io->text("Response Status: {$statusCode}");

            $searchResults = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $io->error('Invalid JSON response: ' . json_last_error_msg());
                return Command::FAILURE;
            }

if (!isset($searchResults['pictures'])) {
                $io->warning('No pictures key in response');
                $io->text('Available keys: ' . implode(', ', array_keys($searchResults)));
                return Command::SUCCESS;
            }

            // Display results
            $io->section('Search Results:');

            $results = [];
            $pictures = $searchResults['pictures'] ?? [];

            foreach ($pictures as $photo) {
                $results[] = [
                    'Photo ID' => $photo['id'] ?? 'N/A',
                    'Title' => $photo['title'] ?? 'N/A',
                    'URL' => $photo['url'] ?? 'N/A',
                    'Similarity Score' => isset($photo['similarity_score']) ? number_format($photo['similarity_score'], 4) : 'N/A',
                    'Dominant RGB' => isset($photo['dominant_color']) ?
                        "({$photo['dominant_color'][0]}, {$photo['dominant_color'][1]}, {$photo['dominant_color'][2]})" : 'N/A'
                ];
            }

            if (empty($results)) {
                $io->warning("No results found with tolerance of {$tolerance}");
                return Command::SUCCESS;
            }

            $io->table(['Photo ID', 'Title', 'URL', 'Similarity Score', 'Dominant RGB'], $results);

            // Summary
            $io->section('Summary:');
            $io->listing([
                "Total matches found: " . count($pictures),
                "Search source: " . ($searchResults['search_source'] ?? 'N/A'),
                "Method used: {$method}",
                "Color tolerance: {$tolerance}",
                "Query RGB: RGB({$rgbValues[0]}, {$rgbValues[1]}, {$rgbValues[2]})",
                "Query color hex: " . ($searchResults['query_color_hex'] ?? 'N/A')
            ]);

            $io->success('✓ Color search test completed successfully!');
            return Command::SUCCESS;

        } catch (GuzzleException $e) {
            $io->error('Search failed: ' . $e->getMessage());

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
     * Parse RGB color string
     */
    private function parseRgbColor(string $color): ?array
    {
        $color = trim($color);

        // Remove parentheses if present: RGB(255,128,64) -> 255,128,64
        $color = preg_replace('/^rgb\s*\(\s*|\s*\)$/i', '', $color);

        $parts = explode(',', $color);

        if (count($parts) !== 3) {
            return null;
        }

        $rgb = [];
        foreach ($parts as $part) {
            $value = (int) trim($part);
            if ($value < 0 || $value > 255) {
                return null;
            }
            $rgb[] = $value;
        }

        return $rgb;
    }

    /**
     * Display color analysis and comparison
     */
    private function displayColorAnalysis(SymfonyStyle $io, array $queryRgb, array $results): void
    {
        if (empty($results)) {
            return;
        }

        $io->text("Query color: RGB({$queryRgb[0]}, {$queryRgb[1]}, {$queryRgb[2]})");
        $io->newLine();

        foreach (array_slice($results, 0, 3) as $index => $result) { // Show top 3
            $originalRgb = $result['Original RGB'];
            if (preg_match('/\((\d+), (\d+), (\d+)\)/', $originalRgb, $matches)) {
                $r = (int)$matches[1];
                $g = (int)$matches[2];
                $b = (int)$matches[3];

                $distance = $this->calculateColorDistance($queryRgb, [$r, $g, $b]);

                $io->text(sprintf(
                    "  #%d: Photo %s - %s (Euclidean distance: %.2f)",
                    $index + 1,
                    $result['Photo ID'],
                    $originalRgb,
                    $distance
                ));
            }
        }
    }

    /**
     * Calculate Euclidean distance between two RGB colors
     */
    private function calculateColorDistance(array $color1, array $color2): float
    {
        $dr = $color1[0] - $color2[0];
        $dg = $color1[1] - $color2[1];
        $db = $color1[2] - $color2[2];

        return sqrt($dr * $dr + $dg * $dg + $db * $db);
    }

    /**
     * Simple .env file loader (not needed for WordPress REST API calls)
     */
    private function loadEnv(string $path): void
    {
        // Method kept for compatibility but not used in WordPress API calls
    }
}

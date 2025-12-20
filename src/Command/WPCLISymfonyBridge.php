<?php

namespace Alex\PhotoLibraryRestApi\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

class WPCLISymfonyBridge extends \WP_CLI_Command
{
    private Command $symfonyCommand;

    public function __construct(Command $symfonyCommand)
    {
        $this->symfonyCommand = $symfonyCommand;
    }

    public function __invoke($args, $assoc_args)
    {
        // Build argv array for Symfony Console
        $argv = ['command-name'];
        foreach ($args as $arg) {
            $argv[] = $arg;
        }
        foreach ($assoc_args as $key => $value) {
            if ($value === true) {
                $argv[] = '--' . $key;
            } else {
                $argv[] = '--' . $key . '=' . $value;
            }
        }

        $input = new ArgvInput($argv);
        $input->bind($this->symfonyCommand->getDefinition());
        
        $output = new BufferedOutput();

        try {
            $statusCode = $this->symfonyCommand->run($input, $output);
            $content = $output->fetch();

            if ($content) {
                \WP_CLI::log($content);
            }

            if ($statusCode !== Command::SUCCESS) {
                \WP_CLI::error('Command failed with status code: ' . $statusCode, false);
            }
        } catch (\Exception $e) {
            \WP_CLI::error($e->getMessage());
        }
    }
}

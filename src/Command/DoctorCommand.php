<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class DoctorCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct('doctor');
    }

    protected function configure(): void
    {
        $this->setDescription('Validate the local Privacy Evidence runtime and research prerequisites.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $checks = [];
        $checks['php>=8.4.1'] = version_compare(PHP_VERSION, '8.4.1', '>=');

        foreach (['ctype', 'dom', 'json', 'libxml', 'mbstring', 'pdo', 'pdo_sqlite'] as $extension) {
            $checks['ext-' . $extension] = extension_loaded($extension);
        }

        $checks['composer.lock'] = is_file($this->projectRoot . '/composer.lock');
        $checks['schema/research-run'] = is_file(
            $this->projectRoot . '/schema/research-run.schema.json',
        );
        $checks['research/protocol'] = is_file(
            $this->projectRoot . '/docs/research/protocol.md',
        );

        $dataDirectory = $this->projectRoot . '/data/derived';
        if (!is_dir($dataDirectory)) {
            @mkdir($dataDirectory, 0700, true);
        }
        $checks['data/derived writable'] = is_dir($dataDirectory)
            && is_writable($dataDirectory);

        $node = new Process(['node', '--version']);
        $node->setTimeout(5.0);
        $node->run();
        $checks['node available'] = $node->isSuccessful();

        $checks['playwright installed'] = is_dir(
            $this->projectRoot . '/browser/node_modules/playwright',
        );

        $failedRequired = false;
        foreach ($checks as $name => $ok) {
            $optional = in_array($name, ['node available', 'playwright installed'], true);
            $status = $ok ? 'OK' : ($optional ? 'OPTIONAL-MISSING' : 'FAIL');
            $output->writeln(sprintf('%-28s %s', $name, $status));

            if (!$ok && !$optional) {
                $failedRequired = true;
            }
        }

        return $failedRequired ? Command::FAILURE : Command::SUCCESS;
    }
}

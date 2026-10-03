<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Experiment\Rubix\RubixSignalClassifier;
use PrivacyEvidence\Experiment\Rubix\RubixSignalTrainingProvenance;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class HacktoberfestDemoBuildCommand extends Command
{
    private const DATASET_ID = 'privacy-evidence-hacktoberfest-demo';
    private const DATASET_VERSION = '1.0.0';
    private const MAPPING_VERSION = 'direct-evidence-demo-v1';
    private const TRAINED_AT = '2026-10-03T12:00:00Z';

    public function __construct()
    {
        parent::__construct('experiment:hacktoberfest:build-model');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Build the deterministic local PHP-native model used by the Hacktoberfest demo.')
            ->addArgument('artifact', InputArgument::REQUIRED, 'Output RBX artifact path.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $artifact = $input->getArgument('artifact');
        if (!is_string($artifact) || trim($artifact) === '') {
            return Command::INVALID;
        }

        try {
            $examples = self::examples();
            $canonical = json_encode(
                $examples,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
            $datasetSha256 = hash('sha256', $canonical);
            $splitSha256 = hash('sha256', 'hacktoberfest-demo-train-only-v1|' . $datasetSha256);

            $model = RubixSignalClassifier::train(
                EvidenceType::ControllerIdentity,
                $examples,
                new RubixSignalTrainingProvenance(
                    self::DATASET_ID,
                    self::DATASET_VERSION,
                    $datasetSha256,
                    $splitSha256,
                    self::MAPPING_VERSION,
                    self::TRAINED_AT,
                ),
                128,
            );
            $artifactSha256 = $model->save($artifact);

            $output->writeln(json_encode(
                [
                    'status' => 'experimental-demo-model',
                    'datasetId' => self::DATASET_ID,
                    'datasetVersion' => self::DATASET_VERSION,
                    'datasetSha256' => $datasetSha256,
                    'splitSha256' => $splitSha256,
                    'trainingSamples' => count($examples),
                    'artifact' => $artifact,
                    'artifactSha256' => $artifactSha256,
                    'model' => $model->metadata()->toArray(),
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            return Command::SUCCESS;
        } catch (\InvalidArgumentException | \RuntimeException | \JsonException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }

    /**
     * Project-authored synthetic examples used only for the challenge demonstration.
     *
     * @return list<array{text:string,present:bool}>
     */
    private static function examples(): array
    {
        return [
            [
                'text' => 'empresa exemplo responsável tratamento informações pessoais privacidade',
                'present' => true,
            ],
            [
                'text' => 'responsável pelo tratamento de informações pessoais organização',
                'present' => true,
            ],
            [
                'text' => 'quem trata informações pessoais empresa responsável privacidade',
                'present' => true,
            ],
            [
                'text' => 'organization responsible personal information processing privacy',
                'present' => true,
            ],
            ['text' => 'agenda eventos comunidade notícias encontros', 'present' => false],
            ['text' => 'produtos serviços catálogo institucional preços', 'present' => false],
            ['text' => 'contato suporte atendimento comercial vendas', 'present' => false],
            ['text' => 'história missão equipe carreiras comunidade', 'present' => false],
        ];
    }
}

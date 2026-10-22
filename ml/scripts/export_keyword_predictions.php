#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Models\OffenseCatalog;
use App\Services\ClassificationEngine;
use Illuminate\Contracts\Console\Kernel;

$projectRoot = dirname(__DIR__, 2);
require $projectRoot.'/vendor/autoload.php';

/**
 * @return never
 */
function fail(string $message): void
{
    fwrite(STDERR, "Keyword export failed: {$message}\n");
    exit(1);
}

function usage(): void
{
    $usage = <<<'TEXT'
Export the current Laravel keyword engine's ranked predictions.

Usage:
  php ml/scripts/export_keyword_predictions.php [options]

Options:
  --split=validation|test   Dataset split (default: validation)
  --input=PATH              Input JSONL (default: ml/data/processed/<split>.jsonl)
  --output=PATH             Output JSONL
                            (default: ml/artifacts/keyword-<split>/raw_predictions.jsonl)
  --help                    Show this message

The command reads the offense catalog but does not update application data.
TEXT;

    fwrite(STDOUT, $usage."\n");
}

$options = getopt('', ['split:', 'input:', 'output:', 'help']);
if ($options === false) {
    fail('Unable to parse command-line options.');
}
if (array_key_exists('help', $options)) {
    usage();
    exit(0);
}

$split = $options['split'] ?? 'validation';
if (! is_string($split) || ! in_array($split, ['validation', 'test'], true)) {
    fail('--split must be validation or test.');
}

$inputPath = $options['input']
    ?? $projectRoot."/ml/data/processed/{$split}.jsonl";
$outputPath = $options['output']
    ?? $projectRoot."/ml/artifacts/keyword-{$split}/raw_predictions.jsonl";
if (! is_string($inputPath) || ! is_string($outputPath)) {
    fail('--input and --output must each contain one path.');
}
if (! is_file($inputPath) || ! is_readable($inputPath)) {
    fail("Input file is missing or unreadable: {$inputPath}");
}

$outputDirectory = dirname($outputPath);
if (! is_dir($outputDirectory)
    && ! mkdir($outputDirectory, 0775, true)
    && ! is_dir($outputDirectory)) {
    fail("Unable to create output directory: {$outputDirectory}");
}

try {
    $application = require $projectRoot.'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    $catalogSnapshot = OffenseCatalog::query()
        ->orderBy('code')
        ->get([
            'code',
            'offense_type',
            'title',
            'description',
            'keywords',
            'special_consequence',
        ])
        ->map(static fn (OffenseCatalog $offense): array => [
            'code' => $offense->code,
            'offense_type' => $offense->offense_type,
            'title' => $offense->title,
            'description' => $offense->description,
            'keywords' => $offense->keywords,
            'special_consequence' => $offense->special_consequence,
        ])
        ->values()
        ->all();
    if ($catalogSnapshot === []) {
        fail(
            'The offense catalog is empty. Seed the approved catalog before '
            .'running the keyword baseline.'
        );
    }

    /** @var ClassificationEngine $engine */
    $engine = $application->make(ClassificationEngine::class);
    $input = fopen($inputPath, 'rb');
    $output = fopen($outputPath, 'wb');
    if ($input === false || $output === false) {
        fail('Unable to open the input or output file.');
    }

    $lineNumber = 0;
    $rowCount = 0;
    $seenIds = [];
    while (($line = fgets($input)) !== false) {
        $lineNumber++;
        if (trim($line) === '') {
            continue;
        }

        try {
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                "{$inputPath}:{$lineNumber} is invalid JSON: {$exception->getMessage()}",
                previous: $exception,
            );
        }
        if (! is_array($row)) {
            throw new RuntimeException(
                "{$inputPath}:{$lineNumber} must contain a JSON object."
            );
        }

        $id = $row['id'] ?? null;
        $text = $row['text'] ?? null;
        $expectedLabel = $row['primary_offense_code'] ?? null;
        $rowSplit = $row['split'] ?? null;
        if (! is_string($id) || $id === '') {
            throw new RuntimeException(
                "{$inputPath}:{$lineNumber} has an invalid id."
            );
        }
        if (isset($seenIds[$id])) {
            throw new RuntimeException(
                "{$inputPath}:{$lineNumber} repeats id {$id}."
            );
        }
        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException(
                "{$inputPath}:{$lineNumber} has empty text."
            );
        }
        if (! is_string($expectedLabel) || $expectedLabel === '') {
            throw new RuntimeException(
                "{$inputPath}:{$lineNumber} has an invalid expected label."
            );
        }
        if ($rowSplit !== $split) {
            throw new RuntimeException(
                "{$inputPath}:{$lineNumber} belongs to split "
                .var_export($rowSplit, true).", not {$split}."
            );
        }
        if (($row['model_eligible'] ?? null) !== true) {
            throw new RuntimeException(
                "{$inputPath}:{$lineNumber} is not model eligible."
            );
        }

        $ranked = $engine->classify($text)
            ->map(static fn (array $candidate): array => [
                'label' => $candidate['offense']->code,
                'score' => (float) $candidate['score'],
            ])
            ->values()
            ->all();

        if ($ranked === []) {
            $ranked = [[
                'label' => 'NO_MATCH',
                'score' => 0.0,
            ]];
        }

        $prediction = [
            'id' => $id,
            'expected_label' => $expectedLabel,
            'score_kind' => 'keyword_ranking_score',
            'top_predictions' => $ranked,
        ];
        $encoded = json_encode(
            $prediction,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR,
        );
        if (fwrite($output, $encoded."\n") === false) {
            throw new RuntimeException("Unable to write {$outputPath}.");
        }

        $seenIds[$id] = true;
        $rowCount++;
    }

    if (! feof($input)) {
        throw new RuntimeException("Unable to finish reading {$inputPath}.");
    }
    fclose($input);
    fclose($output);

    if ($rowCount === 0) {
        throw new RuntimeException("No prediction rows were written.");
    }

    $catalogJson = json_encode(
        $catalogSnapshot,
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR,
    );
    $sourcePaths = [
        'ml/scripts/export_keyword_predictions.php' => __FILE__,
        'app/Services/ClassificationEngine.php' => (
            $projectRoot.'/app/Services/ClassificationEngine.php'
        ),
        'database/seeders/OffenseCatalogSeeder.php' => (
            $projectRoot.'/database/seeders/OffenseCatalogSeeder.php'
        ),
        'composer.lock' => $projectRoot.'/composer.lock',
    ];
    $sourceFiles = [];
    foreach ($sourcePaths as $relativePath => $sourcePath) {
        $hash = hash_file('sha256', $sourcePath);
        if ($hash === false) {
            throw new RuntimeException("Unable to hash {$sourcePath}.");
        }
        $sourceFiles[$relativePath] = [
            'sha256' => strtoupper($hash),
            'bytes' => filesize($sourcePath),
        ];
    }
    $inputHash = hash_file('sha256', $inputPath);
    $outputHash = hash_file('sha256', $outputPath);
    if ($inputHash === false || $outputHash === false) {
        throw new RuntimeException('Unable to hash keyword benchmark files.');
    }
    $manifest = [
        'schema_version' => 1,
        'engine' => 'App\\Services\\ClassificationEngine',
        'split' => $split,
        'rows' => $rowCount,
        'input' => [
            'path' => realpath($inputPath),
            'sha256' => strtoupper($inputHash),
            'bytes' => filesize($inputPath),
        ],
        'predictions' => [
            'path' => realpath($outputPath),
            'sha256' => strtoupper($outputHash),
            'bytes' => filesize($outputPath),
        ],
        'catalog' => [
            'offenses' => count($catalogSnapshot),
            'sha256' => strtoupper(hash('sha256', $catalogJson)),
        ],
        'source_files' => $sourceFiles,
        'runtime' => [
            'php' => PHP_VERSION,
            'laravel' => $application->version(),
        ],
    ];
    $manifestPath = $outputPath.'.manifest.json';
    $manifestJson = json_encode(
        $manifest,
        JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_THROW_ON_ERROR,
    );
    if (file_put_contents($manifestPath, $manifestJson."\n") === false) {
        throw new RuntimeException("Unable to write {$manifestPath}.");
    }
} catch (Throwable $exception) {
    fail($exception->getMessage());
}

fwrite(
    STDOUT,
    "Exported {$rowCount} keyword predictions to "
    .realpath($outputPath)."\n",
);

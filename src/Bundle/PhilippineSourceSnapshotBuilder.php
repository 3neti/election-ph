<?php

namespace LBHurtado\ElectionPh\Bundle;

use PDO;
use RuntimeException;
use Throwable;

final class PhilippineSourceSnapshotBuilder
{
    /**
     * @param  iterable<array<string, mixed>>  $precincts
     * @param  iterable<array<string, mixed>>  $sources
     * @return array{precinct_count: int, source_count: int, registered_voters: int}
     */
    public function build(iterable $precincts, iterable $sources, string $path): array
    {
        if (file_exists($path)) {
            throw new RuntimeException('Source snapshot registry output already exists.');
        }

        $database = new PDO('sqlite:'.$path);
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('PRAGMA journal_mode = DELETE');
        $database->exec('CREATE TABLE precincts (id TEXT PRIMARY KEY, region TEXT NOT NULL, province TEXT NOT NULL, city_municipality TEXT NOT NULL, barangay TEXT NOT NULL, registered_voters INTEGER NOT NULL CHECK (registered_voters >= 0), source_row INTEGER NOT NULL)');
        $database->exec('CREATE TABLE clc_sources (url TEXT PRIMARY KEY, region TEXT NOT NULL, sha256 TEXT NOT NULL, bytes INTEGER NOT NULL CHECK (bytes > 0))');
        $insertPrecinct = $database->prepare('INSERT INTO precincts VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insertSource = $database->prepare('INSERT INTO clc_sources VALUES (?, ?, ?, ?)');
        $precinctCount = 0;
        $sourceCount = 0;
        $registeredVoters = 0;
        $database->beginTransaction();

        try {
            foreach ($precincts as $precinct) {
                $voters = $precinct['cluster_total'] ?? null;

                if (! is_int($voters) || $voters < 0) {
                    throw new RuntimeException('POP precinct has an invalid registered voter count.');
                }

                $insertPrecinct->execute([
                    $precinct['clustered_precinct'],
                    $precinct['region'],
                    $precinct['province'],
                    $precinct['city_municipality'],
                    $precinct['barangay'],
                    $voters,
                    $precinct['source_row'],
                ]);
                $precinctCount++;
                $registeredVoters += $voters;
            }

            foreach ($sources as $source) {
                $insertSource->execute([$source['url'], $source['region'], $source['sha256'], $source['bytes']]);
                $sourceCount++;
            }

            if ($precinctCount === 0 || $sourceCount === 0) {
                throw new RuntimeException('Source snapshot requires POP precincts and CLC sources.');
            }

            $database->commit();
        } catch (Throwable $exception) {
            $database->rollBack();
            unset($insertPrecinct, $insertSource, $database);
            @unlink($path);

            throw $exception;
        }

        return [
            'precinct_count' => $precinctCount,
            'source_count' => $sourceCount,
            'registered_voters' => $registeredVoters,
        ];
    }
}

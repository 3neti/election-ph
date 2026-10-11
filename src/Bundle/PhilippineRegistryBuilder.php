<?php

namespace LBHurtado\ElectionPh\Bundle;

use PDO;
use RuntimeException;

final class PhilippineRegistryBuilder
{
    /** @param array<string, mixed> $registries */
    public function build(array $registries, string $path): void
    {
        foreach (['election_id', 'precincts', 'ballot_styles', 'contests', 'candidates'] as $key) {
            if (! isset($registries[$key])) {
                throw new RuntimeException("Election registry requires [{$key}].");
            }
        }

        if (is_file($path)) {
            throw new RuntimeException('Election registry output already exists.');
        }

        $database = new PDO('sqlite:'.$path);
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('PRAGMA foreign_keys = ON');
        $database->exec('PRAGMA journal_mode = DELETE');
        $database->exec('CREATE TABLE elections (id TEXT PRIMARY KEY)');
        $database->exec('CREATE TABLE ballot_styles (id TEXT PRIMARY KEY, election_id TEXT NOT NULL REFERENCES elections(id))');
        $database->exec('CREATE TABLE precincts (id TEXT PRIMARY KEY, name TEXT NOT NULL, ballot_style_id TEXT NOT NULL REFERENCES ballot_styles(id))');
        $database->exec('CREATE TABLE contests (id TEXT PRIMARY KEY, title TEXT NOT NULL, max_selections INTEGER NOT NULL CHECK(max_selections > 0))');
        $database->exec('CREATE TABLE candidates (id TEXT PRIMARY KEY, name TEXT NOT NULL)');
        $database->exec('CREATE TABLE style_contests (style_id TEXT NOT NULL REFERENCES ballot_styles(id), contest_id TEXT NOT NULL REFERENCES contests(id), position INTEGER NOT NULL, PRIMARY KEY(style_id, contest_id))');
        $database->exec('CREATE TABLE contest_candidates (contest_id TEXT NOT NULL REFERENCES contests(id), candidate_id TEXT NOT NULL REFERENCES candidates(id), position INTEGER NOT NULL, PRIMARY KEY(contest_id, candidate_id))');
        $database->exec('CREATE TABLE candidate_codes (style_id TEXT NOT NULL REFERENCES ballot_styles(id), code TEXT NOT NULL, contest_id TEXT NOT NULL REFERENCES contests(id), candidate_id TEXT NOT NULL REFERENCES candidates(id), PRIMARY KEY(style_id, code), UNIQUE(style_id, candidate_id))');
        $database->beginTransaction();

        try {
            $database->prepare('INSERT INTO elections VALUES (?)')->execute([$registries['election_id']]);

            foreach ($registries['candidates'] as $candidate) {
                $database->prepare('INSERT INTO candidates VALUES (?, ?)')->execute([$candidate['id'], $candidate['name']]);
            }

            foreach ($registries['contests'] as $contest) {
                $database->prepare('INSERT INTO contests VALUES (?, ?, ?)')->execute([$contest['id'], $contest['title'], $contest['max_selections']]);

                foreach ($contest['candidate_ids'] as $position => $candidateId) {
                    $database->prepare('INSERT INTO contest_candidates VALUES (?, ?, ?)')->execute([$contest['id'], $candidateId, $position + 1]);
                }
            }

            $contestsById = array_column($registries['contests'], null, 'id');

            foreach ($registries['ballot_styles'] as $style) {
                $database->prepare('INSERT INTO ballot_styles VALUES (?, ?)')->execute([$style['id'], $registries['election_id']]);
                $sequence = 1;

                foreach ($style['contest_ids'] as $position => $contestId) {
                    $contest = $contestsById[$contestId] ?? throw new RuntimeException("Unknown contest [{$contestId}].");
                    $database->prepare('INSERT INTO style_contests VALUES (?, ?, ?)')->execute([$style['id'], $contestId, $position + 1]);

                    foreach ($contest['candidate_ids'] as $candidateId) {
                        $database->prepare('INSERT INTO candidate_codes VALUES (?, ?, ?, ?)')
                            ->execute([$style['id'], sprintf('CAND%05d', $sequence++), $contestId, $candidateId]);
                    }
                }
            }

            foreach ($registries['precincts'] as $precinct) {
                $database->prepare('INSERT INTO precincts VALUES (?, ?, ?)')
                    ->execute([$precinct['id'], $precinct['name'], $precinct['ballot_style_id']]);
            }

            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            unset($database);
            @unlink($path);
            throw $exception;
        }
    }
}

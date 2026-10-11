<?php

namespace LBHurtado\ElectionPh\Bundle;

use PDO;
use RuntimeException;

final class PhilippineRegistryReader
{
    private PDO $database;

    public function __construct(string $path)
    {
        if (! is_file($path)) {
            throw new RuntimeException('Commissioned election registry is missing.');
        }

        $this->database = new PDO('sqlite:file:'.str_replace('%2F', '/', rawurlencode($path)).'?mode=ro');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('PRAGMA query_only = ON');
    }

    /** @return array<int, array{code: string, contest_id: string, candidate_id: string, name: string}> */
    public function candidateCodesForPrecinct(string $precinctId): array
    {
        $query = $this->database->prepare('SELECT cc.code, cc.contest_id, cc.candidate_id, c.name FROM precincts p JOIN candidate_codes cc ON cc.style_id = p.ballot_style_id JOIN candidates c ON c.id = cc.candidate_id WHERE p.id = ? ORDER BY cc.code');
        $query->execute([$precinctId]);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            throw new RuntimeException("Precinct [{$precinctId}] has no candidate code map.");
        }

        return $rows;
    }
}

<?php

use LBHurtado\ElectionPh\Bundle\PhilippineRegistryBuilder;
use LBHurtado\ElectionPh\Bundle\PhilippineRegistryReader;

test('sample registries produce the expected scoped compact candidate codes', function (): void {
    $registries = [
        'election_id' => 'election-1',
        'precincts' => [['id' => '0421-A', 'name' => 'Precinct 0421-A', 'ballot_style_id' => 'style-1']],
        'ballot_styles' => [['id' => 'style-1', 'contest_ids' => ['president', 'council']]],
        'contests' => [
            ['id' => 'president', 'title' => 'President', 'max_selections' => 1, 'candidate_ids' => ['pres-ada']],
            ['id' => 'council', 'title' => 'Council', 'max_selections' => 2, 'candidate_ids' => ['council-ana', 'council-ben']],
        ],
        'candidates' => [
            ['id' => 'pres-ada', 'name' => 'Ada'],
            ['id' => 'council-ana', 'name' => 'Ana'],
            ['id' => 'council-ben', 'name' => 'Ben'],
        ],
    ];
    $path = sys_get_temp_dir().'/election-ph-'.bin2hex(random_bytes(5)).'.sqlite';

    (new PhilippineRegistryBuilder)->build($registries, $path);
    $database = new PDO('sqlite:'.$path);
    $codes = $database->query("SELECT code, candidate_id FROM candidate_codes WHERE style_id = 'style-1' ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);

    expect($codes)->toHaveCount(3)
        ->and($codes[0])->toBe(['code' => 'CAND00001', 'candidate_id' => 'pres-ada'])
        ->and($codes[2])->toBe(['code' => 'CAND00003', 'candidate_id' => 'council-ben']);

    $reader = new PhilippineRegistryReader($path);

    expect($reader->candidateCodesForPrecinct('0421-A'))->toHaveCount(3)
        ->and($reader->candidateCodesForPrecinct('0421-A')[0]['name'])->toBe('Ada')
        ->and(fn () => $reader->candidateCodesForPrecinct('unknown'))->toThrow(RuntimeException::class);
});

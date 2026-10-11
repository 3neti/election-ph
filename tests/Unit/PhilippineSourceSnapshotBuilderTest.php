<?php

use LBHurtado\ElectionPh\Bundle\PhilippineSourceSnapshotBuilder;

test('source snapshot stores precincts and CLC provenance without claiming ballot styles', function (): void {
    $path = sys_get_temp_dir().'/ph-source-snapshot-'.bin2hex(random_bytes(6)).'.sqlite';
    $counts = (new PhilippineSourceSnapshotBuilder)->build([
        ['clustered_precinct' => '1-A', 'region' => 'NCR', 'province' => 'NCR', 'city_municipality' => 'MANILA', 'barangay' => '1', 'cluster_total' => 120, 'source_row' => 2],
    ], [
        ['url' => 'https://example.test/clc.pdf', 'region' => 'NCR', 'sha256' => str_repeat('a', 64), 'bytes' => 100],
    ], $path);
    $database = new PDO('sqlite:'.$path);

    expect($counts)->toBe(['precinct_count' => 1, 'source_count' => 1, 'registered_voters' => 120])
        ->and($database->query('SELECT COUNT(*) FROM precincts')->fetchColumn())->toBe(1)
        ->and($database->query('SELECT COUNT(*) FROM clc_sources')->fetchColumn())->toBe(1);
});

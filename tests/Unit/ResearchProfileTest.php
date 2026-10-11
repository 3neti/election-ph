<?php

use Symfony\Component\Yaml\Yaml;

test('2025 research profile contains only review transitions', function (): void {
    $profile = Yaml::parseFile(dirname(__DIR__, 2).'/profiles/2025-research.yaml');

    expect($profile['run_type'])->toBe('research')
        ->and(array_keys($profile['ceremonies']))->toBe(['begin_review', 'record_decision', 'close_review'])
        ->and($profile['ceremonies']['record_decision']['from'])->toBe('reviewing')
        ->and($profile['ceremonies']['record_decision']['to'])->toBe('reviewing');
});

<?php

it('deprecated key is properly set', function () {
    $operation = new \Dedoc\Scramble\Support\Generator\Operation('get');
    $operation->deprecated(true);

    $array = $operation->toArray();

    expect($operation->deprecated)->toBeTrue()
        ->and($array)->toHaveKey('deprecated')
        ->and($array['deprecated'])->toBeTrue();
});

it('default deprecated key is false', function () {
    $operation = new \Dedoc\Scramble\Support\Generator\Operation('get');

    $array = $operation->toArray();

    expect($operation->deprecated)->toBeFalse()
        ->and($array)->not()->toHaveKey('deprecated');
});

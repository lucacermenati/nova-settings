<?php

use Illuminate\Support\Facades\Validator;
use LucaCermenati\NovaSettings\Rules\ChoicesNotEmpty;

function choicesPass(mixed $value): bool
{
    return Validator::make(['choices' => $value], ['choices' => [new ChoicesNotEmpty]])->passes();
}

it('accepts a non-empty map of choices', function () {
    expect(choicesPass('{"a":"A","b":"B"}'))->toBeTrue()
        ->and(choicesPass(['a' => 'A']))->toBeTrue();
});

it('rejects empty or malformed choices', function (mixed $value) {
    expect(choicesPass($value))->toBeFalse();
})->with([
    'empty object' => ['{}'],
    'empty array' => ['[]'],
    'empty string' => [''],
    'null' => [null],
    'not json' => ['nope'],
    'blank key' => ['{"":"x"}'],
    'blank key array' => [['' => 'x']],
]);

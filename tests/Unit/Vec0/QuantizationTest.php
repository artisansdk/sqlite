<?php

declare(strict_types=1);

use ArtisanSdk\SQLite\Vec0\Quantization;

it('maps quantizations to their SQLite quantizers', function (Quantization $quantization, ?string $quantizer): void {
    expect($quantization->quantizer())->toBe($quantizer);
})->with([
    [Quantization::Q4B, null],
    [Quantization::Q2B, 'vec_quantize_float16'],
    [Quantization::Q1B, 'vec_quantize_int8'],
    [Quantization::QBIT, 'vec_quantize_binary'],
]);

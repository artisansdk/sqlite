<?php

declare(strict_types=1);

namespace ArtisanSdk\SQLite\Vec0;

enum Quantization: string
{
    case Q4B = 'float32';
    case Q2B = 'float16';
    case Q1B = 'int8';
    case QBIT = 'bit';

    /**
     * Get the SQLite column type.
     */
    public function sqliteType(): string
    {
        return match ($this) {
            self::Q4B => 'FLOAT',
            self::Q2B => 'FLOAT',
            self::Q1B => 'INT8',
            self::QBIT => 'BIT',
        };
    }

    /**
     * Get the SQLite quantizer name.
     */
    public function quantizer(): ?string
    {
        return match ($this) {
            self::Q4B => null,
            self::Q2B => 'vec_quantize_float16',
            self::Q1B => 'vec_quantize_int8',
            self::QBIT => 'vec_quantize_binary',
        };
    }
}

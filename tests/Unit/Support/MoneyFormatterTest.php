<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\MoneyFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyFormatterTest extends TestCase
{
    public function test_formats_decimal_amounts_without_float_rounding(): void
    {
        self::assertSame('1 234 567,80', MoneyFormatter::format('1234567.8'));
        self::assertSame('0,00', MoneyFormatter::format('000.00'));
        self::assertSame('1 234 567 890 123 456,78', MoneyFormatter::format('1234567890123456.78'));
    }

    public function test_rejects_invalid_financial_decimal_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MoneyFormatter::format('12.345');
    }
}

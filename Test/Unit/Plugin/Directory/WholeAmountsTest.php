<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Plugin\Directory;

use Magento\Directory\Model\Currency;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Zero\Core\Plugin\Directory\WholeAmounts;

/**
 * "4 800 ₴", not "4 800,00 ₴"; kopecks stay when there are some, other
 * currencies and modules that list none as they are.
 */
#[AllowMockObjectsWithoutExpectations]
class WholeAmountsTest extends TestCase
{
    public function testWholeHryvniasWithoutKopecks(): void
    {
        $this->assertSame([4800, ['precision' => 0]], $this->format('UAH', 4800));
        $this->assertSame(
            ['4800.00', ['symbol' => '₴', 'precision' => 0]],
            $this->format('UAH', '4800.00', ['symbol' => '₴'])
        );
    }

    public function testKopecksStay(): void
    {
        $this->assertSame([4799.5, []], $this->format('UAH', 4799.5));
    }

    public function testOtherCurrenciesAsTheyAre(): void
    {
        $this->assertSame([4800, []], $this->format('USD', 4800));
        $this->assertSame([4800, []], $this->format('UAH', 4800, [], []));
    }

    public function testNotANumberAsItIs(): void
    {
        $this->assertSame(['', []], $this->format('UAH', ''));
    }

    private function format(string $code, mixed $price, array $options = [], array $currencies = ['UAH']): array
    {
        $currency = $this->createMock(Currency::class);
        $currency->method('getCode')->willReturn($code);

        return (new WholeAmounts($currencies))->beforeFormatTxt($currency, $price, $options);
    }
}

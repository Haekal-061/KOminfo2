<?php

namespace Tests\Unit;

use App\Services\PhoneNumberService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PhoneNumberServiceTest extends CIUnitTestCase
{
    public function testLocalAndInternationalNumbersNormalizeToSameValue(): void
    {
        $this->assertSame('628123456789', PhoneNumberService::normalize('08123456789'));
        $this->assertSame('628123456789', PhoneNumberService::normalize('+628123456789'));
        $this->assertSame('628123456789', PhoneNumberService::normalize('628123456789'));
    }

    public function testInvalidPhoneNumberIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PhoneNumberService::normalize('not-a-phone');
    }
}

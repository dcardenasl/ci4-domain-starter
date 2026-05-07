<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Exceptions\BadRequestException;
use App\Traits\ValidatesRequiredFields;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * ValidatesRequiredFields Trait Tests
 */
class ValidatesRequiredFieldsTest extends CIUnitTestCase
{
    private object $testClass;

    protected function setUp(): void
    {
        parent::setUp();

        // Create anonymous class that uses the trait
        $this->testClass = new class () {
            use ValidatesRequiredFields;

            public function testValidateId(array $data): int
            {
                return $this->validateRequiredId($data);
            }

            public function testValidateField(array $data, string $field, ?string $langKey = null): mixed
            {
                return $this->validateRequiredField($data, $field, $langKey);
            }

            public function testValidateFields(array $data, array $fieldErrors, string $message = ''): void
            {
                $this->validateRequiredFields($data, $fieldErrors, $message);
            }
        };
    }

    public function testValidateRequiredIdReturnsIntegerForValidId(): void
    {
        $data = ['id' => '42'];

        $result = $this->testClass->testValidateId($data);

        $this->assertIsInt($result);
        $this->assertEquals(42, $result);
    }

    public function testValidateRequiredIdThrowsExceptionForMissingId(): void
    {
        try {
            $this->testClass->testValidateId([]);
            $this->fail('Expected BadRequestException was not thrown');
        } catch (BadRequestException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('id', $errors);
            $this->assertEquals(lang('InputValidation.common.idRequired', ['Id']), $errors['id']);
        }
    }

    public function testValidateRequiredIdThrowsExceptionForNullId(): void
    {
        $this->expectException(BadRequestException::class);

        $this->testClass->testValidateId(['id' => null]);
    }

    public function testValidateRequiredIdThrowsExceptionForEmptyStringId(): void
    {
        $this->expectException(BadRequestException::class);

        $this->testClass->testValidateId(['id' => '']);
    }

    public function testValidateRequiredIdConvertsStringToInteger(): void
    {
        $data = ['id' => '123'];

        $result = $this->testClass->testValidateId($data);

        $this->assertIsInt($result);
        $this->assertEquals(123, $result);
    }

    public function testValidateRequiredFieldReturnsValueForValidField(): void
    {
        $data = ['email' => 'test@example.com'];

        $result = $this->testClass->testValidateField($data, 'email');

        $this->assertEquals('test@example.com', $result);
    }

    public function testValidateRequiredFieldThrowsExceptionForMissingField(): void
    {
        $this->expectException(BadRequestException::class);

        $this->testClass->testValidateField([], 'email');
    }

    public function testValidateRequiredFieldThrowsExceptionForNullField(): void
    {
        $this->expectException(BadRequestException::class);

        $this->testClass->testValidateField(['email' => null], 'email');
    }

    public function testValidateRequiredFieldThrowsExceptionForEmptyStringField(): void
    {
        $this->expectException(BadRequestException::class);

        $this->testClass->testValidateField(['email' => ''], 'email');
    }

    public function testValidateRequiredFieldUsesCustomLangKey(): void
    {
        try {
            $this->testClass->testValidateField([], 'email', 'Users.emailRequired');
            $this->fail('Expected BadRequestException was not thrown');
        } catch (BadRequestException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('email', $errors);
        }
    }

    public function testValidateRequiredFieldUsesDefaultMessageWhenNoLangKey(): void
    {
        try {
            $this->testClass->testValidateField([], 'custom_field');
            $this->fail('Expected BadRequestException was not thrown');
        } catch (BadRequestException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('custom_field', $errors);
            $this->assertEquals(lang('Api.fieldRequired', ['custom_field']), $errors['custom_field']);
        }
    }

    public function testValidateRequiredFieldsThrowsWithAllMissingFields(): void
    {
        try {
            $this->testClass->testValidateFields([], [
                'email' => 'Email is required',
                'password' => 'Password is required',
            ], 'Validation failed');
            $this->fail('Expected BadRequestException was not thrown');
        } catch (BadRequestException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('email', $errors);
            $this->assertArrayHasKey('password', $errors);
            $this->assertEquals('Validation failed', $e->getMessage());
        }
    }

    public function testValidateRequiredFieldsUsesLocalizedDefaultRequestMessage(): void
    {
        try {
            $this->testClass->testValidateFields([], [
                'email' => 'Email is required',
            ]);
            $this->fail('Expected BadRequestException was not thrown');
        } catch (BadRequestException $e) {
            $this->assertEquals(lang('Api.invalidRequest'), $e->getMessage());
        }
    }

    public function testValidateRequiredFieldsPassesWhenAllFieldsArePresent(): void
    {
        $this->testClass->testValidateFields([
            'email' => 'test@example.com',
            'password' => 'secret123',
        ], [
            'email' => 'Email is required',
            'password' => 'Password is required',
        ]);

        $this->assertTrue(true);
    }
}

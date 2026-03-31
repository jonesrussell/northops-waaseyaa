<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Pipeline;

use App\Domain\Pipeline\ContactFormValidator;
use PHPUnit\Framework\TestCase;

final class ContactFormValidatorTest extends TestCase
{
    private ContactFormValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ContactFormValidator();
    }

    public function testValidInputReturnsNoErrors(): void
    {
        $errors = $this->validator->validate('Alice', 'alice@example.com', 'Hello');
        $this->assertSame([], $errors);
    }

    public function testEmptyNameReturnsError(): void
    {
        $errors = $this->validator->validate('', 'alice@example.com', 'Hello');
        $this->assertArrayHasKey('name', $errors);
    }

    public function testNameOver255ReturnsError(): void
    {
        $errors = $this->validator->validate(str_repeat('A', 256), 'alice@example.com', 'Hello');
        $this->assertArrayHasKey('name', $errors);
    }

    public function testEmptyEmailReturnsError(): void
    {
        $errors = $this->validator->validate('Alice', '', 'Hello');
        $this->assertArrayHasKey('email', $errors);
    }

    public function testInvalidEmailReturnsError(): void
    {
        $errors = $this->validator->validate('Alice', 'not-an-email', 'Hello');
        $this->assertArrayHasKey('email', $errors);
    }

    public function testEmptyMessageReturnsError(): void
    {
        $errors = $this->validator->validate('Alice', 'alice@example.com', '');
        $this->assertArrayHasKey('message', $errors);
    }

    public function testMessageOver5000ReturnsError(): void
    {
        $errors = $this->validator->validate('Alice', 'alice@example.com', str_repeat('A', 5001));
        $this->assertArrayHasKey('message', $errors);
    }
}

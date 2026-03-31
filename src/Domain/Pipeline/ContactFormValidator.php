<?php

declare(strict_types=1);

namespace App\Domain\Pipeline;

final class ContactFormValidator
{
    /** @return array<string, string> */
    public function validate(string $name, string $email, string $message): array
    {
        $errors = [];

        if ($name === '' || strlen($name) > 255) {
            $errors['name'] = 'Name is required (max 255 characters).';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'A valid email address is required.';
        }

        if ($message === '' || strlen($message) > 5000) {
            $errors['message'] = 'Message is required (max 5000 characters).';
        }

        return $errors;
    }
}

<?php

namespace App\Validators;

class NotifValidator {
    private array $errors = [];

    public function validate(array $data, bool $isUpdate = false): array {
        $this->errors = [];

        if (!$isUpdate) {
            $this->validateCitizenId($data['citizen_id'] ?? null);
            $this->validateTitle($data['title'] ?? null);
            $this->validateBody($data['body'] ?? null);
        }

        if ($isUpdate) {
            if (isset($data['is_read'])) {
                $this->validateIsRead($data['is_read']);
            } else {
                $this->errors['is_read'] = 'is_read field is required for update';
            }
        }

        return $this->errors;
    }

    private function validateCitizenId(mixed $citizen_id): void {
        if (empty($citizen_id)) {
            $this->errors['citizen_id'] = 'Citizen ID is required';
            return;
        }

        if (!is_numeric($citizen_id) || (int) $citizen_id < 1) {
            $this->errors['citizen_id'] = 'Citizen ID must be a valid positive integer';
        }
    }

    private function validateTitle(?string $title): void {
        if (empty($title)) {
            $this->errors['title'] = 'Title is required';
            return;
        }

        if (strlen($title) < 3 || strlen($title) > 100) {
            $this->errors['title'] = 'Title must be between 3 and 100 characters';
        }
    }

    private function validateBody(?string $body): void {
        if (empty($body)) {
            $this->errors['body'] = 'Body is required';
            return;
        }

        if (strlen($body) < 10) {
            $this->errors['body'] = 'Body must be at least 10 characters';
        }

        if (strlen($body) > 500) {
            $this->errors['body'] = 'Body must not exceed 500 characters';
        }
    }

    private function validateIsRead(mixed $is_read): void {
        if (!isset($is_read)) {
            $this->errors['is_read'] = 'is_read is required';
            return;
        }

        if (!in_array($is_read, [0, 1, '0', '1', true, false], true)) {
            $this->errors['is_read'] = 'is_read must be a boolean value';
        }
    }

    public function isValid(array $data, bool $isUpdate = false): bool {
        return empty($this->validate($data, $isUpdate));
    }

    public function getErrors(): array {
        return $this->errors;
    }
}
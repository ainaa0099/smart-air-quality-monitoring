<?php

namespace App\Validators;

class CitizenValidator {
    private array $errors = [];

    public function validate(array $data, bool $isUpdate = false): array {
        $this->errors = [];

        if (!$isUpdate) {
            $this->validateNik($data['nik'] ?? null);
            $this->validateEmail($data['email'] ?? null);
        }

        if (isset($data['name'])) {
            $this->validateName($data['name']);
        } elseif (!$isUpdate) {
            $this->errors['name'] = 'Name is required';
        }

        if (isset($data['phone'])) {
            $this->validatePhone($data['phone']);
        } elseif (!$isUpdate) {
            $this->errors['phone'] = 'Phone number is required';
        }

        if (isset($data['zone_id'])) {
            $this->validateZoneId($data['zone_id']);
        } elseif (!$isUpdate) {
            $this->errors['zone_id'] = 'Zone ID is required';
        }

        return $this->errors;
    }

    private function validateNik(?string $nik): void {
        if (empty($nik)) {
            $this->errors['nik'] = 'NIK is required';
            return;
        }

        if (!preg_match('/^\d{16}$/', $nik)) {
            $this->errors['nik'] = 'NIK must be exactly 16 digits';
        }
    }

    private function validateName(?string $name): void {
        if (empty($name)) {
            $this->errors['name'] = 'Name is required';
            return;
        }

        if (strlen($name) < 3 || strlen($name) > 100) {
            $this->errors['name'] = 'Name must be between 3 and 100 characters';
        }

        if (!preg_match('/^[a-zA-Z\s]+$/', $name)) {
            $this->errors['name'] = 'Name must contain only letters and spaces';
        }
    }

    private function validateEmail(?string $email): void {
        if (empty($email)) {
            $this->errors['email'] = 'Email is required';
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = 'Invalid email format';
        }
    }

    private function validatePhone(?string $phone): void {
        if (empty($phone)) {
            $this->errors['phone'] = 'Phone number is required';
            return;
        }

        if (!preg_match('/^(\+62|62|0)[0-9]{9,12}$/', $phone)) {
            $this->errors['phone'] = 'Invalid Indonesian phone number format';
        }
    }

    private function validateZoneId(mixed $zone_id): void {
        if (empty($zone_id)) {
            $this->errors['zone_id'] = 'Zone ID is required';
            return;
        }

        if (!is_numeric($zone_id) || (int) $zone_id < 1 || (int) $zone_id > 5) {
            $this->errors['zone_id'] = 'Zone ID must be between 1 and 5';
        }
    }

    public function isValid(array $data, bool $isUpdate = false): bool {
        return empty($this->validate($data, $isUpdate));
    }

    public function getErrors(): array {
        return $this->errors;
    }
}
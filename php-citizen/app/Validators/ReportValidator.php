<?php

namespace App\Validators;

class ReportValidator {
    private array $errors = [];

    private array $allowedCategories = [
        'air_pollution',
        'dust',
        'industrial_smoke',
        'vehicle_emission',
        'burning',
        'other'
    ];

    private array $allowedStatuses = [
        'pending',
        'in_progress',
        'resolved',
        'rejected'
    ];

    public function validate(array $data, bool $isUpdate = false): array {
        $this->errors = [];

        if (!$isUpdate) {
            $this->validateCitizenId($data['citizen_id'] ?? null);
            $this->validateCategory($data['category'] ?? null);
            $this->validateDescription($data['description'] ?? null);
            $this->validateZoneId($data['zone_id'] ?? null);
        }

        if ($isUpdate) {
            if (isset($data['status'])) {
                $this->validateStatus($data['status']);
            } else {
                $this->errors['status'] = 'Status is required for update';
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

    private function validateCategory(?string $category): void {
        if (empty($category)) {
            $this->errors['category'] = 'Category is required';
            return;
        }

        if (!in_array($category, $this->allowedCategories)) {
            $this->errors['category'] = 'Invalid category. Allowed: ' . implode(', ', $this->allowedCategories);
        }
    }

    private function validateDescription(?string $description): void {
        if (empty($description)) {
            $this->errors['description'] = 'Description is required';
            return;
        }

        if (strlen($description) < 10) {
            $this->errors['description'] = 'Description must be at least 10 characters';
        }

        if (strlen($description) > 1000) {
            $this->errors['description'] = 'Description must not exceed 1000 characters';
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

    private function validateStatus(?string $status): void {
        if (empty($status)) {
            $this->errors['status'] = 'Status is required';
            return;
        }

        if (!in_array($status, $this->allowedStatuses)) {
            $this->errors['status'] = 'Invalid status. Allowed: ' . implode(', ', $this->allowedStatuses);
        }
    }

    public function isValid(array $data, bool $isUpdate = false): bool {
        return empty($this->validate($data, $isUpdate));
    }

    public function getErrors(): array {
        return $this->errors;
    }
}
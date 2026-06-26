<?php

namespace App\Controllers;

use App\Models\Citizen;
use App\Validators\CitizenValidator;

class CitizenController {
    private Citizen $model;
    private CitizenValidator $validator;

    public function __construct() {
        $this->model     = new Citizen();
        $this->validator = new CitizenValidator();
    }

    public function index(): void {
        $citizens = $this->model->findAll();
        $this->respond(200, 'success', 'Citizens retrieved successfully', $citizens);
    }

    public function show(int $id): void {
        $citizen = $this->model->findById($id);

        if (!$citizen) {
            $this->respond(404, 'error', 'Citizen not found', null, 404);
            return;
        }

        $this->respond(200, 'success', 'Citizen retrieved successfully', $citizen);
    }

    public function store(array $data): void {
        $errors = $this->validator->validate($data);

        if (!empty($errors)) {
            $this->respond(422, 'error', 'Validation failed', $errors, 422);
            return;
        }

        $existing = $this->model->findByNik($data['nik']);
        if ($existing) {
            $this->respond(409, 'error', 'NIK already registered', null, 409);
            return;
        }

        $existingEmail = $this->model->findByEmail($data['email']);
        if ($existingEmail) {
            $this->respond(409, 'error', 'Email already registered', null, 409);
            return;
        }

        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);

        $citizen = $this->model->create($data);

        if (!$citizen) {
            $this->respond(500, 'error', 'Failed to create citizen', null, 500);
            return;
        }

        $this->respond(201, 'success', 'Citizen registered successfully', $citizen, 201);
    }

    public function update(int $id, array $data): void {
        $citizen = $this->model->findById($id);

        if (!$citizen) {
            $this->respond(404, 'error', 'Citizen not found', null, 404);
            return;
        }

        $errors = $this->validator->validate($data, true);

        if (!empty($errors)) {
            $this->respond(422, 'error', 'Validation failed', $errors, 422);
            return;
        }

        $updated = $this->model->update($id, $data);

        if (!$updated) {
            $this->respond(500, 'error', 'Failed to update citizen', null, 500);
            return;
        }

        $this->respond(200, 'success', 'Citizen updated successfully', $updated);
    }

    public function destroy(int $id): void {
        $citizen = $this->model->findById($id);

        if (!$citizen) {
            $this->respond(404, 'error', 'Citizen not found', null, 404);
            return;
        }

        $deleted = $this->model->delete($id);

        if (!$deleted) {
            $this->respond(500, 'error', 'Failed to delete citizen', null, 500);
            return;
        }

        $this->respond(200, 'success', 'Citizen deleted successfully', null);
    }

    private function respond(int $code, string $status, string $message, mixed $data = null, int $httpCode = 200): void {
        http_response_code($httpCode);
        echo json_encode([
            'status'    => $status,
            'code'      => $code,
            'data'      => $data,
            'message'   => $message,
            'timestamp' => date('c'),
            'service'   => 'citizen-service',
        ]);
    }
}
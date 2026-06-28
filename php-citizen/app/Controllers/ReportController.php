<?php

namespace App\Controllers;

use App\Models\Report;
use App\Models\Notification;
use App\Services\RabbitMQPublisher;
use App\Validators\ReportValidator;

class ReportController {
    private Report $model;
    private Notification $notifModel;
    private ReportValidator $validator;

    public function __construct() {
        $this->model      = new Report();
        $this->notifModel = new Notification();
        $this->validator  = new ReportValidator();
    }

    public function index(array $filters = []): void {
        $reports = $this->model->findAll($filters);

        $this->respond(200, 'success', 'Reports retrieved successfully', $reports);
    }

    public function show(int $id): void {
        $report = $this->model->findById($id);

        if (!$report) {
            $this->respond(404, 'error', 'Report not found', null, 404);
            return;
        }

        $this->respond(200, 'success', 'Report retrieved successfully', $report);
    }

    public function store(array $data): void {
        $errors = $this->validator->validate($data);

        if (!empty($errors)) {
            $this->respond(422, 'error', 'Validation failed', $errors, 422);
            return;
        }

        $report = $this->model->create($data);

        if (!$report) {
            $this->respond(500, 'error', 'Failed to submit report', null, 500);
            return;
        }

        try {
            $publisher = new RabbitMQPublisher();
            $publisher->publish('report.submitted', [
                'report_id'   => $report['id'],
                'citizen_id'  => $report['citizen_id'],
                'category'    => $report['category'],
                'zone_id'     => $report['zone_id'],
                'description' => $report['description'],
                'status'      => $report['status'],
                'timestamp'   => $report['created_at'],
            ]);
        } catch (\Exception $e) {
            error_log('RabbitMQ publish failed: ' . $e->getMessage());
        }

        $this->respond(201, 'success', 'Report submitted successfully', $report, 201);
    }

    public function updateStatus(int $id, array $data): void {
        $report = $this->model->findById($id);

        if (!$report) {
            $this->respond(404, 'error', 'Report not found', null, 404);
            return;
        }

        $errors = $this->validator->validate($data, true);

        if (!empty($errors)) {
            $this->respond(422, 'error', 'Validation failed', $errors, 422);
            return;
        }

        $updated = $this->model->updateStatus($id, $data['status']);

        if (!$updated) {
            $this->respond(500, 'error', 'Failed to update report status', null, 500);
            return;
        }

        try {
            $this->notifModel->create([
                'citizen_id' => $report['citizen_id'],
                'title'      => 'Status Laporan Diperbarui',
                'body'       => "Laporan #{$id} kategori {$report['category']} kini berstatus: {$data['status']}",
            ]);
        } catch (\Exception $e) {
            error_log('Failed to create notification: ' . $e->getMessage());
        }

        $this->respond(200, 'success', 'Report status updated successfully', $updated);
    }

    public function destroy(int $id): void {
        $report = $this->model->findById($id);

        if (!$report) {
            $this->respond(404, 'error', 'Report not found', null, 404);
            return;
        }

        $deleted = $this->model->delete($id);

        if (!$deleted) {
            $this->respond(500, 'error', 'Failed to delete report', null, 500);
            return;
        }

        $this->respond(200, 'success', 'Report deleted successfully', null);
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

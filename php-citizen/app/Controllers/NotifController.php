<?php

namespace App\Controllers;

use App\Models\Notification;
use App\Validators\NotifValidator;

class NotifController {
    private Notification $model;
    private NotifValidator $validator;

    public function __construct() {
        $this->model     = new Notification();
        $this->validator = new NotifValidator();
    }

    public function index(int $citizen_id, bool $unread_only = false): void {
        $notifications = $this->model->findByCitizenId($citizen_id, $unread_only);

        $unreadCount = $this->model->countUnread($citizen_id);

        $this->respond(200, 'success', 'Notifications retrieved successfully', [
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
            'total'         => count($notifications),
        ]);
    }

    public function show(int $id): void {
        $notification = $this->model->findById($id);

        if (!$notification) {
            $this->respond(404, 'error', 'Notification not found', null, 404);
            return;
        }

        $this->respond(200, 'success', 'Notification retrieved successfully', $notification);
    }

    public function store(array $data): void {
        $errors = $this->validator->validate($data);

        if (!empty($errors)) {
            $this->respond(422, 'error', 'Validation failed', $errors, 422);
            return;
        }

        $notification = $this->model->create($data);

        if (!$notification) {
            $this->respond(500, 'error', 'Failed to create notification', null, 500);
            return;
        }

        $this->respond(201, 'success', 'Notification created successfully', $notification, 201);
    }

    public function markAsRead(int $id): void {
        $notification = $this->model->findById($id);

        if (!$notification) {
            $this->respond(404, 'error', 'Notification not found', null, 404);
            return;
        }

        $updated = $this->model->markAsRead($id);

        if (!$updated) {
            $this->respond(500, 'error', 'Failed to mark notification as read', null, 500);
            return;
        }

        $this->respond(200, 'success', 'Notification marked as read', $updated);
    }

    public function markAllAsRead(int $citizen_id): void {
        $result = $this->model->markAllAsRead($citizen_id);

        if (!$result) {
            $this->respond(500, 'error', 'Failed to mark all notifications as read', null, 500);
            return;
        }

        $this->respond(200, 'success', 'All notifications marked as read', null);
    }

    public function destroy(int $id): void {
        $notification = $this->model->findById($id);

        if (!$notification) {
            $this->respond(404, 'error', 'Notification not found', null, 404);
            return;
        }

        $deleted = $this->model->delete($id);

        if (!$deleted) {
            $this->respond(500, 'error', 'Failed to delete notification', null, 500);
            return;
        }

        $this->respond(200, 'success', 'Notification deleted successfully', null);
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
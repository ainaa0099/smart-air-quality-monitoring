<?php

require_once __DIR__ . '/../Models/Item.php';
require_once __DIR__ . '/../Config/RabbitMQ.php';

class ItemController
{
    private Item $model;

    public function __construct()
    {
        $this->model = new Item();
        header('Content-Type: application/json');
    }

    public function index()
    {
        echo json_encode(
            $this->model->getAll()
        );
    }

    public function show(int $id)
    {
        echo json_encode(
            $this->model->getById($id)
        );
    }

    public function store()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        // simpan ke DB tab (model kamu)
        $success = $this->model->create($data['id'], $data['item']);

        // KIRIM KE RABBITMQ
        if ($success) {
            RabbitMQ::publish("item.created", [
                "id" => $data['id'],
                "item" => $data['item']
            ]);
        }

        echo json_encode([
            "success" => $success
        ]);
    }

    public function update(int $id)
    {
        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        $success = $this->model->update(
            $id,
            $data['item']
        );

        echo json_encode([
            'success' => $success
        ]);
    }

    public function destroy(int $id)
    {
        $success = $this->model->delete($id);

        echo json_encode([
            'success' => $success
        ]);
    }
}
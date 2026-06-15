<?php

require_once __DIR__ . '/../Models/Tab2.php';

class Tab2Controller
{
    private Tab2 $model;

    public function __construct()
    {
        $this->model = new Tab2();
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
        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        $success = $this->model->create(
            $data['id'],
            $data['items']   // <-- ini berubah dari item ke items
        );

        echo json_encode([
            'success' => $success
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
            $data['items']   // <-- ini juga items
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
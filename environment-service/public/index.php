<?php

require_once __DIR__ . '/../app/Controllers/ItemController.php';
require_once __DIR__ . '/../app/Controllers/Tab2Controller.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$id = $_GET['id'] ?? null;

/* TAB 1 */
if ($path === '/items') {
    $controller = new ItemController();

    if ($method === 'GET' && $id) $controller->show($id);
    else if ($method === 'GET') $controller->index();
    else if ($method === 'POST') $controller->store();
    else if ($method === 'PUT') $controller->update($id);
    else if ($method === 'DELETE') $controller->destroy($id);
}

/* TAB 2 */
if ($path === '/tab2') {
    $controller = new Tab2Controller();

    if ($method === 'GET' && $id) $controller->show($id);
    else if ($method === 'GET') $controller->index();
    else if ($method === 'POST') $controller->store();
    else if ($method === 'PUT') $controller->update($id);
    else if ($method === 'DELETE') $controller->destroy($id);
}
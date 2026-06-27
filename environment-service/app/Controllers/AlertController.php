<?php

class AlertController
{
    public function index()
    {
        $alert = new Alert();

        echo json_encode(
            $alert->getAll()
        );
    }
}
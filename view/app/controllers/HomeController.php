<?php
// /app/controllers/HomeController.php

class HomeController extends Controller {
    public function index() {
        $data = [
            'titre' => 'Bienvenue sur votre Application Métier !'
        ];
        $this->view('home/index', $data);
    }
}
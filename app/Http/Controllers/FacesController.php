<?php

namespace App\Http\Controllers;

use App\Services\FindFaces;
use Illuminate\Support\Facades\Request;


class FacesController extends Controller
{
    //
    public $service;
    public function __construct(FindFaces $service)
    {
        $this->service = $service;
    }
    public function store()
    {
        return $this->service->store();
    }
    public function index()
    {
        return $this->service->index();
    }
    public function search()
    {
        return $this->service->search();
    }
}

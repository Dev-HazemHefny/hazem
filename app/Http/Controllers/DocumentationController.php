<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class DocumentationController extends Controller
{
    public function swagger(): View
    {
        return view('documentation.swagger');
    }

    public function openapi(): Response
    {
        $path = base_path('docs/openapi.yaml');

        abort_unless(File::exists($path), 404);

        return response(File::get($path), 200, [
            'Content-Type' => 'application/yaml',
        ]);
    }

    public function postman(): Response
    {
        $path = base_path('docs/postman/collection.json');

        abort_unless(File::exists($path), 404);

        return response(File::get($path), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'inline; filename="saas-subscriptions.postman_collection.json"',
        ]);
    }
}

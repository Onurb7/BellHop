<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Services/Index', [
            'services' => Service::orderBy('name')->get()->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'unit_price' => $service->unit_price_cents / 100,
                'pricing_type' => $service->pricing_type->value,
                'active' => $service->active,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Services/Form', [
            'service' => null,
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        Service::create($request->validatedForModel());

        return redirect()->route('admin.services.index')->with('success', 'Service created.');
    }

    public function edit(Service $service): Response
    {
        return Inertia::render('Admin/Services/Form', [
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'unit_price' => $service->unit_price_cents / 100,
                'pricing_type' => $service->pricing_type->value,
                'active' => $service->active,
            ],
        ]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->validatedForModel());

        return redirect()->route('admin.services.index')->with('success', 'Service updated.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()->route('admin.services.index')->with('success', 'Service deleted.');
    }
}

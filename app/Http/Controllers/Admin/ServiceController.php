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
                'unit_price_cents' => $service->unit_price_cents,
                'currency' => $service->currency,
                'pricing_type' => $service->pricing_type->value,
                'active' => $service->active,
                'thumb_url' => $service->getFirstMediaUrl('images', 'thumb') ?: null,
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
        $service = Service::create($request->validatedForModel());

        $this->attachImages($service, $request);

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
                'currency' => $service->currency,
                'pricing_type' => $service->pricing_type->value,
                'active' => $service->active,
                'images' => $service->getMedia('images')->map(fn ($media) => [
                    'id' => $media->id,
                    'url' => $media->getUrl(),
                    'thumb_url' => $media->getUrl('thumb'),
                ]),
            ],
        ]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->validatedForModel());

        $this->attachImages($service, $request);

        foreach ($request->validated('remove_images', []) as $mediaId) {
            $service->media()->where('id', $mediaId)->first()?->delete();
        }

        return redirect()->route('admin.services.index')->with('success', 'Service updated.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()->route('admin.services.index')->with('success', 'Service deleted.');
    }

    private function attachImages(Service $service, ServiceRequest $request): void
    {
        foreach ($request->file('images', []) as $file) {
            $service->addMedia($file)->toMediaCollection('images');
        }
    }
}

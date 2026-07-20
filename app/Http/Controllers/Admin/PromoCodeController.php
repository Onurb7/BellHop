<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromoCodeRequest;
use App\Models\PromoCode;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PromoCodeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/PromoCodes/Index', [
            'promoCodes' => PromoCode::with('services')->withCount('redemptions')->orderBy('code')->get()
                ->map(fn (PromoCode $promoCode) => [
                    'id' => $promoCode->id,
                    'code' => $promoCode->code,
                    'percentage' => $promoCode->percentage,
                    'services' => $promoCode->services->pluck('name'),
                    'max_uses' => $promoCode->max_uses,
                    'redemptions_count' => $promoCode->redemptions_count,
                    'expires_at' => $promoCode->expires_at?->toDateString(),
                    'active' => $promoCode->active,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/PromoCodes/Form', [
            'promoCode' => null,
            'services' => Service::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(PromoCodeRequest $request): RedirectResponse
    {
        $promoCode = PromoCode::create($request->validatedForModel());

        $promoCode->services()->sync($request->validated('service_ids', []));

        return redirect()->route('admin.promo-codes.index')->with('success', 'Promo code created.');
    }

    public function edit(PromoCode $promoCode): Response
    {
        $promoCode->loadMissing('services');

        return Inertia::render('Admin/PromoCodes/Form', [
            'promoCode' => [
                'id' => $promoCode->id,
                'code' => $promoCode->code,
                'description' => $promoCode->description,
                'percentage' => $promoCode->percentage,
                'service_ids' => $promoCode->services->pluck('id'),
                'max_uses' => $promoCode->max_uses,
                'expires_at' => $promoCode->expires_at?->toDateString(),
                'active' => $promoCode->active,
            ],
            'services' => Service::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(PromoCodeRequest $request, PromoCode $promoCode): RedirectResponse
    {
        $promoCode->update($request->validatedForModel());

        $promoCode->services()->sync($request->validated('service_ids', []));

        return redirect()->route('admin.promo-codes.index')->with('success', 'Promo code updated.');
    }

    public function destroy(PromoCode $promoCode): RedirectResponse
    {
        abort_if($promoCode->redemptions()->exists(), 422, 'This code has already been used — deactivate it instead of deleting it.');

        $promoCode->delete();

        return redirect()->route('admin.promo-codes.index')->with('success', 'Promo code deleted.');
    }
}

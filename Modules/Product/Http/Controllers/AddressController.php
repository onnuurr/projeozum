<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\UserAddress;

class AddressController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedPayload($request);

        $userId = $request->user()->id;

        $address = DB::transaction(function () use ($userId, $data) {
            $hasAny = UserAddress::query()->where('user_id', $userId)->exists();

            $isDefault = $data['is_default'] ?? ! $hasAny;
            if ($isDefault) {
                UserAddress::query()->where('user_id', $userId)->update(['is_default' => false]);
            }

            return UserAddress::create([
                'user_id'     => $userId,
                'label'       => $data['label'] ?: 'Diğer',
                'name'        => $data['name'],
                'phone'       => $data['phone'],
                'street'      => $data['street'],
                'district'    => $data['district'] ?? null,
                'city'        => $data['city'],
                'postal_code' => $data['postal_code'] ?? null,
                'is_default'  => (bool) $isDefault,
            ]);
        });

        return back()->with('flash', [
            'toast' => [
                'type'    => 'success',
                'title'   => 'Adres Eklendi',
                'message' => "\"{$address->label}\" adresi kaydedildi.",
            ],
            'newAddressId' => $address->id,
        ]);
    }

    public function update(Request $request, UserAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $data = $this->validatedPayload($request);

        DB::transaction(function () use ($request, $address, $data) {
            $isDefault = $data['is_default'] ?? $address->is_default;
            if ($isDefault && ! $address->is_default) {
                UserAddress::query()
                    ->where('user_id', $request->user()->id)
                    ->update(['is_default' => false]);
            }

            $address->update([
                'label'       => $data['label'] ?: 'Diğer',
                'name'        => $data['name'],
                'phone'       => $data['phone'],
                'street'      => $data['street'],
                'district'    => $data['district'] ?? null,
                'city'        => $data['city'],
                'postal_code' => $data['postal_code'] ?? null,
                'is_default'  => (bool) $isDefault,
            ]);
        });

        return back()->with('flash', [
            'toast' => [
                'type'    => 'success',
                'title'   => 'Adres Güncellendi',
                'message' => "\"{$address->label}\" adresi güncellendi.",
            ],
        ]);
    }

    public function destroy(Request $request, UserAddress $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $label = $address->label;
        $wasDefault = $address->is_default;

        DB::transaction(function () use ($request, $address, $wasDefault) {
            $address->delete();

            if ($wasDefault) {
                $next = UserAddress::query()
                    ->where('user_id', $request->user()->id)
                    ->orderBy('id')
                    ->first();
                $next?->update(['is_default' => true]);
            }
        });

        return back()->with('flash', [
            'toast' => [
                'type'    => 'info',
                'title'   => 'Adres Silindi',
                'message' => "\"{$label}\" adresi kaldırıldı.",
            ],
        ]);
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'label'       => ['nullable', 'string', 'max:32'],
            'name'        => ['required', 'string', 'max:120'],
            'phone'       => ['required', 'string', 'max:32'],
            'street'      => ['required', 'string', 'max:500'],
            'district'    => ['nullable', 'string', 'max:80'],
            'city'        => ['required', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'is_default'  => ['nullable', 'boolean'],
        ]);
    }
}

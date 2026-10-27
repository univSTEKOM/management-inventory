<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\Mutation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DirectMutationService
{
    public function create(User $user, array $data, array $items, string $token): Mutation
    {
        Gate::forUser($user)->authorize('create', Mutation::class);
        Validator::make(['token' => $token], ['token' => ['required', 'uuid']])->validate();

        return DB::transaction(function () use ($user, $data, $items, $token) {
            // Serialize retries by this operator, including requests with the same token.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = Mutation::where('request_token', $token)->first();
            if ($existing) {
                abort_unless($existing->requested_by === $user->id, 403);
                return $existing;
            }
            $data = $this->validateHeader($data);
            $this->lockLocations($data);
            $mutation = Mutation::create($data + [
                'type' => 'mixed', 'status' => 'pending',
                'requested_by' => $user->id, 'request_token' => $token,
            ]);
            $this->move($mutation, $items);

            return $mutation;
        }, 3);
    }

    public function process(User $user, Mutation $mutation): Mutation
    {
        return DB::transaction(function () use ($user, $mutation) {
            $mutation = Mutation::whereKey($mutation->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('process', $mutation);
            $data = $this->validateHeader($mutation->toArray());
            $this->lockLocations($data);
            $items = $mutation->items->map(function ($item) {
                if ((! $item->asset_id && ! $item->inventory_balance_id) || ($item->asset_id && $item->inventory_balance_id)) {
                    throw ValidationException::withMessages(['items' => 'Identitas barang lama tidak lengkap. Batalkan transaksi ini dan buat mutasi baru.']);
                }
                return ['key' => ($item->asset_id ? 'asset:'.$item->asset_id : 'supply:'.$item->inventory_balance_id), 'quantity' => $item->quantity];
            })->all();
            $mutation->items()->delete();
            $this->move($mutation, $items);

            return $mutation;
        }, 3);
    }

    public function cancel(User $user, Mutation $mutation): void
    {
        DB::transaction(function () use ($user, $mutation) {
            $mutation = Mutation::whereKey($mutation->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('cancel', $mutation);
            $mutation->update(['status' => 'cancelled']);
        });
    }

    private function validateHeader(array $data): array
    {
        $data = Validator::make($data, [
            'source_campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'source_location_id' => ['required', 'integer', 'exists:locations,id', 'different:destination_location_id'],
            'destination_campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'destination_location_id' => ['required', 'integer', 'exists:locations,id'],
            'source_pic_id' => ['nullable', 'integer', 'exists:employees,id'],
            'destination_pic_id' => ['nullable', 'integer', 'exists:employees,id'],
            'reason' => ['required', 'string', 'max:5000'],
        ])->validate();
        foreach (['source', 'destination'] as $side) {
            if (! Location::whereKey($data[$side.'_location_id'])->where('campus_id', $data[$side.'_campus_id'])->exists()) {
                throw ValidationException::withMessages([$side.'_location_id' => 'Ruangan tidak sesuai dengan gedung.']);
            }
        }
        return $data;
    }

    private function lockLocations(array $data): void
    {
        // Also serialize creation of a previously absent destination balance.
        Location::whereIn('id', [$data['source_location_id'], $data['destination_location_id']])
            ->orderBy('id')->lockForUpdate()->get();
    }

    private function move(Mutation $mutation, array $items): void
    {
        Validator::make(['items' => $items], [
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.key' => ['required', 'string', 'regex:/^(asset|supply):[1-9][0-9]*$/', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ])->validate();
        usort($items, fn ($a, $b) => strcmp($a['key'], $b['key']));
        foreach ($items as $item) {
            [$kind, $id] = explode(':', $item['key']);
            $model = $kind === 'asset' ? Asset::class : InventoryBalance::class;
            $source = $model::whereKey($id)->lockForUpdate()->first();
            if (! $source || $source->campus_id != $mutation->source_campus_id || $source->location_id != $mutation->source_location_id) {
                throw ValidationException::withMessages(['items' => 'Barang tidak lagi tersedia di lokasi asal. Periksa kembali daftar barang.']);
            }
            $quantity = (int) $item['quantity'];
            $snapshot = MutationSearchService::describe($source);
            if ($kind === 'asset') {
                if ($quantity !== 1 || ! in_array($source->status, MutationValidationService::ELIGIBLE_STATUSES)) {
                    throw ValidationException::withMessages(['items' => "{$source->name} tidak dapat dipindahkan. Barang individual harus berjumlah 1 dan berstatus stok/aktif."]);
                }
                $source->update([
                    'campus_id' => $mutation->destination_campus_id,
                    'location_id' => $mutation->destination_location_id,
                    'pic_id' => $mutation->destination_pic_id,
                ]);
            } else {
                if ($source->quantity < $quantity) {
                    throw ValidationException::withMessages(['items' => "Stok {$source->name} tidak mencukupi (tersedia {$source->quantity})."]);
                }
                $destination = InventoryBalance::where([
                    'category_id' => $source->category_id, 'name' => $source->name,
                    'brand' => $source->brand, 'location_id' => $mutation->destination_location_id,
                ])->lockForUpdate()->first();
                if (! $destination) {
                    $destination = InventoryBalance::create([
                        'category_id' => $source->category_id, 'name' => $source->name, 'brand' => $source->brand,
                        'campus_id' => $mutation->destination_campus_id, 'location_id' => $mutation->destination_location_id,
                        'quantity' => 0, 'status' => $source->status ?? 'stock', 'kondisi' => $source->kondisi ?? 'good',
                        'pic_id' => $mutation->destination_pic_id,
                    ]);
                }
                $source->decrement('quantity', $quantity);
                $destination->increment('quantity', $quantity);
            }
            $mutation->items()->create([
                'asset_id' => $kind === 'asset' ? $source->id : null,
                'inventory_balance_id' => $kind === 'supply' ? $source->id : null,
                'quantity' => $quantity, 'snapshot' => $snapshot,
            ]);
        }
        $mutation->update(['status' => 'completed', 'mutation_date' => now()]);
    }
}

<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Classification;
use App\Models\InventoryBalance;
use App\Models\InventoryBalanceUnit;
use App\Models\ItemReclassification;
use App\Models\ItemReclassificationLine;
use App\Models\Location;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reklasifikasi barang antara Aset, Inventaris, dan Barang Habis Pakai (BHP).
 *
 * - Aset <-> Inventaris : baris `assets` yang sama diubah `classification_id`-nya
 *   (identitas, barcode, SKU, foto, histori, dan harga tidak berubah).
 * - Aset/Inventaris -> BHP : barang dikelompokkan per (nama, merk, kategori, satuan),
 *   masuk ke ruangan asal atau satu ruangan tujuan pilihan; tiap batch pembelian asal dibawa sebagai
 *   `purchase_items` baru dengan harga aslinya (tidak pernah dirata-rata).
 *   Aset asal berstatus `reclassified` lalu di-soft-delete.
 * - BHP -> Aset/Inventaris : unit tersedia dialokasikan FIFO berdasarkan batch
 *   pembelian (tanggal pembelian, lalu id), tiap unit menjadi satu baris `assets`.
 *
 * Aturan yang ditegakkan:
 * - Ambang kapitalisasi (Aset >= Rp1.000.000, Inventaris < Rp1.000.000) berlaku
 *   untuk harga perolehan yang diketahui. Harga NULL boleh.
 * - Kategori harus terhubung ke klasifikasi tujuan (tidak ada pemetaan otomatis).
 *
 * `preview()` dan `execute()` memakai satu jalur perencanaan (`plan()`), sehingga
 * validasi pratinjau identik dengan validasi saat eksekusi. Eksekusi berjalan
 * dalam satu transaksi dengan row lock dan token idempoten.
 */
class ItemReclassificationService
{
    public const PERMISSION = 'items.reclassify';

    public const SLUG_ASSET = 'aset';

    public const SLUG_INVENTORY = 'inventaris';

    public const SLUG_SUPPLY = 'barang-habis-pakai';

    public const MAX_ITEMS = 500;

    public const REMOVED_STATUS = 'reclassified';

    private const KONDISI_RANK = ['good' => 0, 'minor_damage' => 1, 'major_damage' => 2];

    /** @var array<string, bool> */
    private array $categoryLinkCache = [];

    public static function userCan(?User $user): bool
    {
        return $user !== null && $user->hasPermissionTo(self::PERMISSION);
    }

    public static function slugs(): array
    {
        return [self::SLUG_ASSET, self::SLUG_INVENTORY, self::SLUG_SUPPLY];
    }

    /**
     * @return string ItemReclassification::TYPE_*
     */
    public static function resolveType(string $source, string $destination): string
    {
        if (! in_array($source, self::slugs(), true) || ! in_array($destination, self::slugs(), true)) {
            throw ValidationException::withMessages(['destination' => 'Klasifikasi asal atau tujuan tidak dikenal.']);
        }

        if ($source === $destination) {
            throw ValidationException::withMessages(['destination' => 'Klasifikasi tujuan harus berbeda dari klasifikasi asal.']);
        }

        if ($source === self::SLUG_SUPPLY) {
            return ItemReclassification::TYPE_FROM_SUPPLY;
        }

        return $destination === self::SLUG_SUPPLY
            ? ItemReclassification::TYPE_TO_SUPPLY
            : ItemReclassification::TYPE_ASSET_INVENTORY;
    }

    /**
     * Pelanggaran ambang kapitalisasi untuk harga satuan terhadap klasifikasi tujuan.
     */
    public static function thresholdViolation(?float $price, string $destinationSlug): ?string
    {
        if ($price === null) {
            return null;
        }

        $threshold = PurchaseItem::CAPITALIZATION_THRESHOLD;

        if ($destinationSlug === self::SLUG_ASSET && $price < $threshold) {
            return 'Aset harus memiliki harga perolehan >= Rp'.self::rupiah($threshold).' (harga: Rp'.self::rupiah($price).')';
        }

        if ($destinationSlug === self::SLUG_INVENTORY && $price >= $threshold) {
            return 'Inventaris harus memiliki harga perolehan < Rp'.self::rupiah($threshold).' (harga: Rp'.self::rupiah($price).')';
        }

        return null;
    }

    /**
     * Pratinjau tanpa menulis data apa pun.
     *
     * @param  list<int>|array<int,int>  $selection  daftar id aset, atau [id stok BHP => jumlah]
     * @param  array{destination_campus_id?:?int,destination_location_id?:?int,reason?:?string}  $options
     */
    public function preview(string $source, string $destination, array $selection, array $options = []): array
    {
        $plan = $this->plan($source, $destination, $selection, $options, false);
        unset($plan['models']);

        return $plan;
    }

    /**
     * @param  list<int>|array<int,int>  $selection
     * @param  array{destination_campus_id?:?int,destination_location_id?:?int,reason?:?string}  $options
     */
    public function execute(User $user, string $source, string $destination, array $selection, array $options, string $token, ?string $fingerprint = null): ItemReclassification
    {
        if (! self::userCan($user)) {
            throw new AuthorizationException('Anda tidak berwenang melakukan reklasifikasi barang.');
        }

        if (! Str::isUuid($token)) {
            throw ValidationException::withMessages(['token' => 'Token permintaan tidak valid.']);
        }

        $previousParent = AuditLogger::$currentParentId;
        $previousBatch = AuditLogger::$currentBatchUuid;

        try {
            return DB::transaction(function () use ($user, $source, $destination, $selection, $options, $token, $fingerprint, $previousParent, $previousBatch) {
                AuditLogger::$currentParentId = $previousParent;
                AuditLogger::$currentBatchUuid = $previousBatch;
                // Serialkan permintaan operator yang sama, termasuk retry dengan token sama.
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();

                $existing = ItemReclassification::where('request_token', $token)->first();
                if ($existing) {
                    abort_unless($existing->user_id === $user->id, 403);

                    return $existing;
                }

                $plan = $this->plan($source, $destination, $selection, $options, true);

                if ($plan['errors'] !== []) {
                    throw ValidationException::withMessages(['items' => $plan['errors']]);
                }

                if ($fingerprint !== null && $fingerprint !== self::fingerprint($plan)) {
                    throw ValidationException::withMessages(['items' => 'Data barang berubah. Tampilkan ulang pratinjau sebelum melanjutkan.']);
                }

                try {
                    return $this->apply($user, $plan, $options, $token);
                } catch (\InvalidArgumentException $e) {
                    // Guard Asset::saving (kategori vs klasifikasi, ruangan vs gedung).
                    throw ValidationException::withMessages(['items' => $e->getMessage()]);
                }
            }, 3);
        } finally {
            AuditLogger::$currentParentId = $previousParent;
            AuditLogger::$currentBatchUuid = $previousBatch;
        }
    }

    public static function fingerprint(array $plan): string
    {
        unset($plan['models']);

        return hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR));
    }

    // ------------------------------------------------------------------
    // Perencanaan (dipakai pratinjau dan eksekusi)
    // ------------------------------------------------------------------

    private function plan(string $source, string $destination, array $selection, array $options, bool $lock): array
    {
        $type = self::resolveType($source, $destination);
        $sourceClassification = Classification::where('slug', $source)->first();
        $destinationClassification = Classification::where('slug', $destination)->first();

        if (! $sourceClassification || ! $destinationClassification) {
            throw ValidationException::withMessages(['destination' => 'Master klasifikasi belum lengkap (Aset, Inventaris, Barang Habis Pakai).']);
        }

        $this->categoryLinkCache = [];
        $errors = [];

        $destinationLocation = $type === ItemReclassification::TYPE_ASSET_INVENTORY
            ? null
            : $this->resolveDestinationLocation($options, $lock, $errors);

        $plan = [
            'type' => $type,
            'source' => $sourceClassification->name,
            'destination' => $destinationClassification->name,
            'source_slug' => $source,
            'destination_slug' => $destination,
            'destination_location' => $destinationLocation ? $this->locationLabel($destinationLocation) : null,
            'groups' => [],
            'rows' => [],
            'errors' => [],
            'total_quantity' => 0,
            'total_value' => null,
            'unknown_price_quantity' => 0,
            'models' => [
                'source' => $sourceClassification,
                'destination' => $destinationClassification,
                'destination_location' => $destinationLocation,
            ],
        ];

        if ($type === ItemReclassification::TYPE_FROM_SUPPLY) {
            $balances = [];
            foreach ($selection as $balanceId => $quantity) {
                $balances[(int) $balanceId] = $quantity;
            }
            ksort($balances);

            if ($balances === []) {
                $errors[] = 'Pilih minimal satu stok BHP.';
            } elseif (count($balances) > self::MAX_ITEMS) {
                $errors[] = 'Maksimal '.self::MAX_ITEMS.' stok BHP per transaksi.';
            } else {
                $this->planFromSupply($plan, $balances, $destinationClassification, $destinationLocation, $lock, $errors);
            }
        } else {
            $ids = collect($selection)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->sort()->values();

            if ($ids->isEmpty()) {
                $errors[] = 'Pilih minimal satu barang.';
            } elseif ($ids->count() > self::MAX_ITEMS) {
                $errors[] = 'Maksimal '.self::MAX_ITEMS.' barang per transaksi.';
            } else {
                $this->planFromAssets($plan, $ids, $sourceClassification, $destinationClassification, $destinationLocation, $lock, $errors);
            }
        }

        $plan['errors'] = array_values(array_unique($errors));
        $this->finalizeTotals($plan);

        return $plan;
    }

    private function resolveDestinationLocation(array $options, bool $lock, array &$errors): ?Location
    {
        $campusId = $options['destination_campus_id'] ?? null;
        $locationId = $options['destination_location_id'] ?? null;

        if (blank($locationId)) {
            return null;
        }

        $location = Location::with('campus')->when($lock, fn ($query) => $query->lockForUpdate())->find($locationId);

        if (! $location || (filled($campusId) && (int) $location->campus_id !== (int) $campusId)) {
            $errors[] = 'Ruangan tujuan tidak ditemukan atau tidak sesuai dengan gedung.';

            return null;
        }

        return $location;
    }

    private function planFromAssets(array &$plan, Collection $ids, Classification $source, Classification $destination, ?Location $destinationLocation, bool $lock, array &$errors): void
    {
        $query = Asset::query()
            ->with(['purchaseItem' => fn ($query) => $query->withTrashed()->with([
                'purchase' => fn ($query) => $query->withTrashed(),
            ]), 'purchase', 'category', 'campus', 'location'])
            ->whereIn('id', $ids)
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $assets = $query->get()->keyBy('id');
        $toSupply = $plan['type'] === ItemReclassification::TYPE_TO_SUPPLY;

        foreach ($ids as $id) {
            if (! $assets->has($id)) {
                $errors[] = "Barang #{$id} tidak ditemukan atau sudah dihapus.";
            }
        }

        $groups = [];

        foreach ($assets as $asset) {
            $label = $this->assetLabel($asset);
            $price = $this->assetPrice($asset);
            $rawUnit = $asset->unit ?? $asset->purchaseItem?->unit;
            $unit = InventoryBalance::normalizeUnit($rawUnit);
            if ($toSupply && mb_strlen(trim((string) $rawUnit)) > InventoryBalance::UNIT_MAX_LENGTH) {
                $errors[] = "{$label}: satuan melebihi batas karakter stok BHP.";
            }
            $brand = filled(trim((string) $asset->brand)) ? trim((string) $asset->brand) : null;
            $name = trim((string) $asset->name);

            if ((int) $asset->classification_id !== (int) $source->id) {
                $errors[] = "{$label}: bukan barang berklasifikasi {$source->name}.";
            }

            if (! in_array($asset->status, MutationValidationService::ELIGIBLE_STATUSES, true)) {
                $errors[] = "{$label}: status \"{$asset->status}\" tidak dapat direklasifikasi (hanya stok/aktif).";
            }

            if ($this->hasOpenMutation('asset_id', $asset->id)) {
                $errors[] = "{$label}: masih dalam mutasi yang belum selesai.";
            }

            if (! $this->categoryLinked($asset->category_id, $destination->id)) {
                $errors[] = "{$label}: kategori ".($asset->category?->name ?? '-')." tidak terhubung ke klasifikasi {$destination->name}.";
            }

            if (! $toSupply && ($violation = self::thresholdViolation($price, $destination->slug))) {
                $errors[] = "{$label}: {$violation}.";
            }

            if ($toSupply && $name === '') {
                $errors[] = "{$label}: nama barang kosong, tidak dapat dikonversi ke BHP.";
            }

            $sourceLocation = $this->locationLabel($asset->location, $asset->campus);
            $groupLocation = $toSupply ? ($destinationLocation ?? $asset->location) : null;
            if ($toSupply && ! $groupLocation) {
                $errors[] = "{$label}: pilih ruangan tujuan karena ruangan asal belum tersedia.";
            }

            $purchaseItem = $asset->purchaseItem;

            $row = [
                'kind' => 'asset',
                'id' => $asset->id,
                'label' => $label,
                'name' => $name,
                'brand' => $brand,
                'category_id' => $asset->category_id,
                'category' => $asset->category?->name,
                'unit' => $unit,
                'quantity' => 1,
                'unit_price' => $price,
                'purchase_item_id' => $purchaseItem?->id,
                'purchase_date' => $purchaseItem?->purchase?->purchase_date?->toDateString() ?? $asset->purchase?->purchase_date,
                'source_location' => $sourceLocation,
                'source_location_id' => $asset->location_id,
            ];
            $plan['rows'][] = $row;
            $plan['models']['assets'][$asset->id] = $asset;

            $key = json_encode([mb_strtolower($name), mb_strtolower((string) $brand), $asset->category_id, mb_strtolower((string) $unit), $groupLocation?->id], JSON_THROW_ON_ERROR);

            $groups[$key] ??= [
                'key' => $key,
                'name' => $name,
                'brand' => $brand,
                'category_id' => $asset->category_id,
                'category' => $asset->category?->name,
                'unit' => $unit,
                'quantity' => 0,
                'total_value' => null,
                'unknown_price_quantity' => 0,
                'source_locations' => [],
                'destination_location' => $this->locationLabel($groupLocation),
                'destination_location_id' => $groupLocation?->id,
                'merges_into_existing' => false,
                'existing_quantity' => null,
                'kondisi' => 'good',
                'batches' => [],
                'asset_ids' => [],
            ];

            $group = &$groups[$key];
            $group['quantity']++;
            $group['asset_ids'][] = $asset->id;
            $group['source_locations'][$sourceLocation ?? '—'] = ($group['source_locations'][$sourceLocation ?? '—'] ?? 0) + 1;
            $group['kondisi'] = $this->worseKondisi($group['kondisi'], $asset->kondisi);

            if ($price === null) {
                $group['unknown_price_quantity']++;
            } else {
                $group['total_value'] = ($group['total_value'] ?? 0) + $price;
            }

            $batchKey = $purchaseItem?->id ?? 'legacy:'.$asset->id;
            $group['batches'][$batchKey] ??= [
                'purchase_item_id' => $purchaseItem?->id,
                'purchase_id' => $purchaseItem?->purchase_id,
                'purchase_date' => $row['purchase_date'],
                'unit_price' => $price,
                'quantity' => 0,
                'subtotal' => $price === null ? null : 0,
                'asset_ids' => [],
                'ownership' => $purchaseItem?->purchase?->ownership ?? $asset->ownership,
                'invoice_number' => $asset->purchase?->invoice_number,
                'tax_invoice_number' => $asset->purchase?->tax_invoice_number,
            ];
            $group['batches'][$batchKey]['quantity']++;
            $group['batches'][$batchKey]['asset_ids'][] = $asset->id;
            if ($price !== null) {
                $group['batches'][$batchKey]['subtotal'] += $price;
            }
            unset($group);
        }

        if ($toSupply) {
            foreach ($groups as &$group) {
                if ($group['destination_location_id'] === null) {
                    continue;
                }

                $existing = InventoryBalance::where([
                    'category_id' => $group['category_id'],
                    'name' => $group['name'],
                    'brand' => $group['brand'],
                    'unit' => $group['unit'],
                    'location_id' => $group['destination_location_id'],
                ])->when($lock, fn ($query) => $query->lockForUpdate())->first();

                $group['merges_into_existing'] = $existing !== null;
                $group['existing_quantity'] = $existing?->quantity;
            }
            unset($group);
        }

        $plan['groups'] = array_values(array_map(function (array $group) {
            $group['batches'] = array_values($group['batches']);

            return $group;
        }, $groups));
    }

    private function planFromSupply(array &$plan, array $requested, Classification $destination, ?Location $destinationLocation, bool $lock, array &$errors): void
    {
        $query = InventoryBalance::query()
            ->with(['category', 'campus', 'location'])
            ->whereIn('id', array_keys($requested))
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $balances = $query->get()->keyBy('id');

        foreach ($requested as $balanceId => $quantity) {
            $balance = $balances->get($balanceId);

            if (! $balance) {
                $errors[] = "Stok BHP #{$balanceId} tidak ditemukan.";

                continue;
            }

            $label = $this->balanceLabel($balance);

            if (! is_numeric($quantity) || (int) $quantity != $quantity || (int) $quantity < 1) {
                $errors[] = "{$label}: jumlah konversi harus bilangan bulat >= 1.";

                continue;
            }
            $quantity = (int) $quantity;

            if ($quantity > (int) $balance->quantity) {
                $errors[] = "{$label}: jumlah konversi {$quantity} melebihi stok tersedia {$balance->quantity}.";

                continue;
            }

            if ($this->hasOpenMutation('inventory_balance_id', $balance->id)) {
                $errors[] = "{$label}: masih dalam mutasi yang belum selesai.";
            }

            if (! $this->categoryLinked($balance->category_id, $destination->id)) {
                $errors[] = "{$label}: kategori ".($balance->category?->name ?? '-')." tidak terhubung ke klasifikasi {$destination->name}.";

                continue;
            }

            if ($quantity + array_sum(array_column($plan['groups'], 'quantity')) > self::MAX_ITEMS) {
                $errors[] = 'Maksimal '.self::MAX_ITEMS.' unit per transaksi.';

                break;
            }

            [$units, $purchaseItems] = $this->allocateUnits($balance, $quantity, $lock);

            if ($units->count() !== $quantity) {
                $errors[] = "{$label}: jumlah unit tersedia tidak sesuai dengan saldo stok. Periksa riwayat stok sebelum melanjutkan.";

                continue;
            }

            $campus = $destinationLocation?->campus ?? $balance->campus;
            $location = $destinationLocation ?? $balance->location;
            $destinationLabel = $this->locationLabel($location, $campus);
            $sourceLabel = $this->locationLabel($balance->location, $balance->campus);

            $batches = [];
            $totalValue = null;
            $unknown = 0;

            foreach ($units->groupBy(fn ($unit) => $unit->purchase_item_id ?? 'none') as $batchKey => $batchUnits) {
                $purchaseItem = $batchKey === 'none' ? null : $purchaseItems->get($batchKey);
                $price = $purchaseItem && $purchaseItem->unit_price !== null ? (float) $purchaseItem->unit_price : null;
                $count = $batchUnits->count();

                if ($violation = self::thresholdViolation($price, $destination->slug)) {
                    $errors[] = "{$label}: batch pembelian #".($purchaseItem?->id ?? '-').": {$violation}.";
                }

                if ($price === null) {
                    $unknown += $count;
                } else {
                    $totalValue = ($totalValue ?? 0) + ($price * $count);
                }

                $batches[] = [
                    'purchase_item_id' => $purchaseItem?->id,
                    'purchase_id' => $purchaseItem?->purchase_id,
                    'purchase_date' => $purchaseItem?->purchase?->purchase_date?->toDateString(),
                    'unit' => $purchaseItem?->unit,
                    'unit_price' => $price,
                    'quantity' => $count,
                    'subtotal' => $price === null ? null : $price * $count,
                    'unit_ids' => $batchUnits->pluck('id')->all(),
                    'sub_barcodes' => $batchUnits->pluck('sub_barcode')->all(),
                    'ownership' => $purchaseItem?->purchase?->ownership,
                ];
            }

            $plan['groups'][] = [
                'key' => 'balance:'.$balance->id,
                'name' => $balance->name,
                'brand' => $balance->brand,
                'category_id' => $balance->category_id,
                'category' => $balance->category?->name,
                'unit' => $balance->unit,
                'quantity' => $quantity,
                'total_value' => $totalValue,
                'unknown_price_quantity' => $unknown,
                'source_locations' => [$sourceLabel ?? '-' => $quantity],
                'destination_location' => $destinationLabel,
                'merges_into_existing' => false,
                'existing_quantity' => $balance->quantity,
                'kondisi' => $balance->kondisi,
                'batches' => $batches,
                'balance_id' => $balance->id,
            ];

            foreach ($batches as $batch) {
                $plan['rows'][] = [
                    'kind' => 'inventory_balance',
                    'id' => $balance->id,
                    'label' => $label,
                    'name' => $balance->name,
                    'brand' => $balance->brand,
                    'category_id' => $balance->category_id,
                    'category' => $balance->category?->name,
                    'unit' => $balance->unit,
                    'quantity' => $batch['quantity'],
                    'unit_price' => $batch['unit_price'],
                    'purchase_item_id' => $batch['purchase_item_id'],
                    'purchase_date' => $batch['purchase_date'],
                    'source_location' => $sourceLabel,
                    'source_location_id' => $balance->location_id,
                ];
            }

            $plan['models']['balances'][$balance->id] = $balance;
            $plan['models']['batches'][$balance->id] = $batches;
            $plan['models']['destination_places'][$balance->id] = [
                'campus_id' => $campus?->id ?? $balance->campus_id,
                'location_id' => $location?->id ?? $balance->location_id,
            ];
        }
    }

    /**
     * Alokasi FIFO unit tersedia: tanggal pembelian (kosong terakhir), id batch, id unit.
     *
     * @return array{0: Collection, 1: Collection}
     */
    private function allocateUnits(InventoryBalance $balance, int $quantity, bool $lock): array
    {
        $query = InventoryBalanceUnit::query()
            ->where('inventory_balance_id', $balance->id)
            ->where('status', 'available');

        if ($lock) {
            $query->lockForUpdate();
        }

        $units = $query->orderBy('id')->get();
        if ($units->count() !== (int) $balance->quantity) {
            return [collect(), collect()];
        }

        $purchaseItems = PurchaseItem::withTrashed()
            ->with(['purchase' => fn ($query) => $query->withTrashed()])
            ->whereIn('id', $units->pluck('purchase_item_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $sorted = $units->sort(function ($a, $b) use ($purchaseItems) {
            $pa = $purchaseItems->get($a->purchase_item_id);
            $pb = $purchaseItems->get($b->purchase_item_id);
            $da = $pa?->purchase?->purchase_date?->toDateString();
            $db = $pb?->purchase?->purchase_date?->toDateString();

            return [$a->purchase_item_id === null, $da === null, (string) $da, (int) $a->purchase_item_id, $a->id]
                <=> [$b->purchase_item_id === null, $db === null, (string) $db, (int) $b->purchase_item_id, $b->id];
        })->values();

        return [$sorted->take($quantity)->values(), $purchaseItems];
    }

    private function finalizeTotals(array &$plan): void
    {
        $quantity = 0;
        $value = null;
        $unknown = 0;

        foreach ($plan['groups'] as $group) {
            $quantity += $group['quantity'];
            $unknown += $group['unknown_price_quantity'];
            if ($group['total_value'] !== null) {
                $value = ($value ?? 0) + $group['total_value'];
            }
        }

        $plan['total_quantity'] = $quantity;
        $plan['total_value'] = $value;
        $plan['unknown_price_quantity'] = $unknown;
    }

    // ------------------------------------------------------------------
    // Eksekusi
    // ------------------------------------------------------------------

    private function apply(User $user, array $plan, array $options, string $token): ItemReclassification
    {
        /** @var Classification $source */
        $source = $plan['models']['source'];
        /** @var Classification $destination */
        $destination = $plan['models']['destination'];
        /** @var Location|null $destinationLocation */
        $destinationLocation = $plan['models']['destination_location'];

        $reclassification = ItemReclassification::create([
            'request_token' => $token,
            'user_id' => $user->id,
            'type' => $plan['type'],
            'source_classification_id' => $source->id,
            'destination_classification_id' => $destination->id,
            'destination_campus_id' => $destinationLocation?->campus_id,
            'destination_location_id' => $destinationLocation?->id,
            'reason' => filled($options['reason'] ?? null) ? trim((string) $options['reason']) : null,
            'total_quantity' => $plan['total_quantity'],
            'total_value' => $plan['total_value'],
            'summary' => [
                'source' => $plan['source'],
                'destination' => $plan['destination'],
                'unknown_price_quantity' => $plan['unknown_price_quantity'],
                'groups' => collect($plan['groups'])->map(fn ($g) => [
                    'name' => $g['name'],
                    'brand' => $g['brand'],
                    'category' => $g['category'],
                    'unit' => $g['unit'],
                    'quantity' => $g['quantity'],
                    'total_value' => $g['total_value'],
                    'source_locations' => $g['source_locations'],
                    'destination_location' => $g['destination_location'],
                    'batches' => collect($g['batches'])->map(fn ($b) => [
                        'purchase_item_id' => $b['purchase_item_id'],
                        'purchase_date' => $b['purchase_date'],
                        'unit_price' => $b['unit_price'],
                        'quantity' => $b['quantity'],
                    ])->all(),
                ])->all(),
            ],
        ]);

        $headerLog = AuditLogger::log(
            AuditAction::RECLASSIFIED,
            $reclassification,
            ['classification' => $plan['source']],
            ['classification' => $plan['destination'], 'total_quantity' => $plan['total_quantity'], 'total_value' => $plan['total_value']],
            $reclassification->reason,
            metadata: ['type' => $plan['type'], 'reclassification_id' => $reclassification->id],
            userId: $user->id,
        );

        // Audit otomatis dari observer (Asset dll.) ikut terhubung ke header ini.
        AuditLogger::$currentParentId = $headerLog->id;
        AuditLogger::$currentBatchUuid = (string) Str::uuid();

        match ($plan['type']) {
            ItemReclassification::TYPE_ASSET_INVENTORY => $this->applyAssetInventory($reclassification, $plan, $destination),
            ItemReclassification::TYPE_TO_SUPPLY => $this->applyToSupply($reclassification, $plan, $destination, $user),
            ItemReclassification::TYPE_FROM_SUPPLY => $this->applyFromSupply($reclassification, $plan, $destination, $user),
        };

        return $reclassification->refresh();
    }

    private function applyAssetInventory(ItemReclassification $header, array $plan, Classification $destination): void
    {
        foreach ($plan['models']['assets'] as $asset) {
            /** @var Asset $asset */
            $price = $this->assetPrice($asset);
            $snapshot = $this->assetSnapshot($asset);

            $asset->update(['classification_id' => $destination->id]);

            $header->lines()->create([
                'source_type' => ItemReclassificationLine::TYPE_ASSET,
                'source_id' => $asset->id,
                'destination_type' => ItemReclassificationLine::TYPE_ASSET,
                'destination_id' => $asset->id,
                'quantity' => 1,
                'unit_price' => $price,
                'source_purchase_item_id' => $asset->purchase_item_id,
                'destination_purchase_item_id' => $asset->purchase_item_id,
                'source_location_id' => $asset->location_id,
                'destination_location_id' => $asset->location_id,
                'snapshot' => $snapshot + ['classification_before' => $plan['source'], 'classification_after' => $plan['destination']],
            ]);
        }
    }

    private function applyToSupply(ItemReclassification $header, array $plan, Classification $destination, User $user): void
    {
        foreach ($plan['groups'] as $group) {
            $location = Location::whereKey($group['destination_location_id'])->lockForUpdate()->firstOrFail();
            $balance = InventoryBalance::where([
                'category_id' => $group['category_id'],
                'name' => $group['name'],
                'brand' => $group['brand'],
                'unit' => $group['unit'],
                'location_id' => $location->id,
            ])->lockForUpdate()->first();

            $oldQuantity = $balance?->quantity ?? 0;

            if (! $balance) {
                $balance = InventoryBalance::create([
                    'category_id' => $group['category_id'],
                    'name' => $group['name'],
                    'brand' => $group['brand'],
                    'unit' => $group['unit'],
                    'campus_id' => $location->campus_id,
                    'location_id' => $location->id,
                    'quantity' => 0,
                    'master_barcode' => SupplyBarcodeGenerator::generateMaster(),
                    'latest_sequence' => 0,
                    'has_pure_master_unit' => false,
                    'status' => 'stock',
                    'kondisi' => $group['kondisi'] ?: 'good',
                ]);
            }

            if (blank($balance->master_barcode)) {
                $balance->update(['master_barcode' => SupplyBarcodeGenerator::generateMaster()]);
            }

            foreach ($group['batches'] as $batch) {
                $count = $batch['quantity'];
                $price = $batch['unit_price'];

                $purchaseId = $batch['purchase_id'] ?: Purchase::create([
                    'purchase_date' => $batch['purchase_date'],
                    'invoice_number' => $batch['invoice_number'],
                    'tax_invoice_number' => $batch['tax_invoice_number'],
                    'ownership' => $batch['ownership'],
                    'total_amount' => null,
                ])->id;

                $purchaseItem = PurchaseItem::create([
                    'purchase_id' => $purchaseId,
                    'inventory_balance_id' => $balance->id,
                    'category_id' => $group['category_id'],
                    'classification_id' => $destination->id,
                    'name' => $group['name'],
                    'quantity' => $count,
                    'unit' => $group['unit'],
                    'unit_price' => $price,
                    'total_price' => $price === null ? null : $price * $count,
                    'is_capitalized' => false, // BHP tidak pernah dikapitalisasi
                ]);

                $barcodes = SupplyBarcodeGenerator::generateSub($balance, $count);
                $unitRows = [];
                foreach ($barcodes as $barcode) {
                    $unitRows[] = [
                        'inventory_balance_id' => $balance->id,
                        'purchase_item_id' => $purchaseItem->id,
                        'sub_barcode' => $barcode,
                        'status' => 'available',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                InventoryBalanceUnit::insert($unitRows);

                foreach ($batch['asset_ids'] as $index => $assetId) {
                    /** @var Asset $asset */
                    $asset = $plan['models']['assets'][$assetId];
                    $snapshot = $this->assetSnapshot($asset);

                    $asset->update(['status' => self::REMOVED_STATUS]);
                    $asset->delete();

                    $header->lines()->create([
                        'source_type' => ItemReclassificationLine::TYPE_ASSET,
                        'source_id' => $asset->id,
                        'destination_type' => ItemReclassificationLine::TYPE_BALANCE,
                        'destination_id' => $balance->id,
                        'quantity' => 1,
                        'unit_price' => $price,
                        'source_purchase_item_id' => $batch['purchase_item_id'],
                        'destination_purchase_item_id' => $purchaseItem->id,
                        'source_location_id' => $asset->location_id,
                        'destination_location_id' => $location->id,
                        'snapshot' => $snapshot + ['sub_barcode' => $barcodes[$index]],
                    ]);
                }
            }

            $balance->increment('quantity', $group['quantity']);

            AuditLogger::log(
                AuditAction::RECLASSIFIED,
                $balance,
                ['quantity' => $oldQuantity],
                ['quantity' => $oldQuantity + $group['quantity']],
                $header->reason,
                metadata: [
                    'transaction_type' => 'reclassification_in',
                    'reclassification_id' => $header->id,
                    'source_asset_ids' => $group['asset_ids'],
                ],
                userId: $user->id,
            );
        }
    }

    private function applyFromSupply(ItemReclassification $header, array $plan, Classification $destination, User $user): void
    {
        $total = $plan['total_quantity'];
        $inventoryNumbers = InventoryNumberGenerator::generateBulk($total);

        foreach ($plan['groups'] as $group) {
            /** @var InventoryBalance $balance */
            $balance = $plan['models']['balances'][$group['balance_id']];
            $place = $plan['models']['destination_places'][$balance->id];
            $oldQuantity = (int) $balance->quantity;

            foreach ($plan['models']['batches'][$balance->id] as $batch) {
                $count = $batch['quantity'];
                $price = $batch['unit_price'];

                $purchaseId = $batch['purchase_id'] ?: Purchase::create([
                    'purchase_date' => null,
                    'ownership' => 'company',
                    'total_amount' => null,
                ])->id;

                $ownership = Purchase::withTrashed()->whereKey($purchaseId)->value('ownership') ?: 'company';

                $purchaseItem = PurchaseItem::create([
                    'purchase_id' => $purchaseId,
                    'category_id' => $balance->category_id,
                    'classification_id' => $destination->id,
                    'name' => $balance->name,
                    'quantity' => $count,
                    'unit' => $batch['unit'] ?: $balance->unit,
                    'unit_price' => $price,
                    'total_price' => $price === null ? null : $price * $count,
                    'is_capitalized' => PurchaseItem::isCapitalizable($price, $destination),
                ]);

                foreach ($batch['unit_ids'] as $index => $unitId) {
                    $asset = Asset::create([
                        'purchase_item_id' => $purchaseItem->id,
                        'classification_id' => $destination->id,
                        'category_id' => $balance->category_id,
                        'inventory_number' => array_shift($inventoryNumbers),
                        'name' => $balance->name,
                        'brand' => $balance->brand,
                        'unit' => $batch['unit'] ?: $balance->unit,
                        'campus_id' => $place['campus_id'],
                        'location_id' => $place['location_id'],
                        'pic_id' => $balance->pic_id,
                        'status' => 'stock',
                        'kondisi' => $balance->kondisi ?: 'good',
                        'ownership' => $ownership,
                        'notes' => $balance->notes,
                    ]);

                    $header->lines()->create([
                        'source_type' => ItemReclassificationLine::TYPE_BALANCE,
                        'source_id' => $balance->id,
                        'destination_type' => ItemReclassificationLine::TYPE_ASSET,
                        'destination_id' => $asset->id,
                        'quantity' => 1,
                        'unit_price' => $price,
                        'source_purchase_item_id' => $batch['purchase_item_id'],
                        'destination_purchase_item_id' => $purchaseItem->id,
                        'source_location_id' => $balance->location_id,
                        'destination_location_id' => $place['location_id'],
                        'snapshot' => [
                            'balance_name' => $balance->name,
                            'balance_master_barcode' => $balance->master_barcode,
                            'unit_id' => $unitId,
                            'sub_barcode' => $batch['sub_barcodes'][$index] ?? null,
                            'purchase_date' => $batch['purchase_date'],
                            'asset_inventory_number' => $asset->inventory_number,
                            'asset_barcode' => $asset->barcode,
                        ],
                    ]);
                }

                InventoryBalanceUnit::whereIn('id', $batch['unit_ids'])->update(['status' => 'used']);
            }

            $balance->decrement('quantity', $group['quantity']);

            AuditLogger::log(
                AuditAction::RECLASSIFIED,
                $balance,
                ['quantity' => $oldQuantity],
                ['quantity' => $oldQuantity - $group['quantity']],
                $header->reason,
                metadata: [
                    'transaction_type' => 'reclassification_out',
                    'reclassification_id' => $header->id,
                    'converted_quantity' => $group['quantity'],
                ],
                userId: $user->id,
            );
        }
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    private function hasOpenMutation(string $column, int $id): bool
    {
        return \App\Models\MutationItem::where($column, $id)
            ->whereHas('mutation', fn ($query) => $query->whereIn('status', ['pending', 'approved']))
            ->exists();
    }

    private function categoryLinked(?int $categoryId, int $classificationId): bool
    {
        if (! $categoryId) {
            return false;
        }

        return $this->categoryLinkCache[$categoryId.':'.$classificationId] ??= Category::query()
            ->whereKey($categoryId)
            ->whereHas('classifications', fn ($q) => $q->whereKey($classificationId))
            ->exists();
    }

    private function assetPrice(Asset $asset): ?float
    {
        if ($asset->purchaseItem) {
            return $asset->purchaseItem->unit_price !== null ? (float) $asset->purchaseItem->unit_price : null;
        }

        if ($asset->purchase && $asset->purchase->unit_price !== null) {
            return (float) $asset->purchase->unit_price;
        }

        return null;
    }

    private function assetSnapshot(Asset $asset): array
    {
        return [
            'inventory_number' => $asset->inventory_number,
            'barcode' => $asset->barcode,
            'name' => $asset->name,
            'brand' => $asset->brand,
            'unit' => $asset->unit,
            'category_id' => $asset->category_id,
            'classification_id' => $asset->classification_id,
            'campus_id' => $asset->campus_id,
            'location_id' => $asset->location_id,
            'pic_id' => $asset->pic_id,
            'status' => $asset->status,
            'kondisi' => $asset->kondisi,
            'serial_number' => $asset->serial_number,
            'purchase_item_id' => $asset->purchase_item_id,
            'legacy_asset_purchase_id' => $asset->purchaseItem === null ? $asset->purchase?->id : null,
            'unit_price' => $this->assetPrice($asset),
        ];
    }

    private function assetLabel(Asset $asset): string
    {
        return trim(($asset->inventory_number ?: '#'.$asset->id).' - '.($asset->name ?: '(tanpa nama)'));
    }

    private function balanceLabel(InventoryBalance $balance): string
    {
        return trim('BHP '.($balance->master_barcode ?: '#'.$balance->id).' - '.$balance->name);
    }

    private function locationLabel(?Location $location, $campus = null): ?string
    {
        if (! $location) {
            return null;
        }

        $campus ??= $location->campus;

        return trim(($campus?->name ? $campus->name.' - ' : '').$location->name);
    }

    private function worseKondisi(?string $current, ?string $candidate): string
    {
        $current ??= 'good';
        $candidate ??= 'good';

        return (self::KONDISI_RANK[$candidate] ?? 0) > (self::KONDISI_RANK[$current] ?? 0) ? $candidate : $current;
    }

    private static function rupiah(float|int $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }
}

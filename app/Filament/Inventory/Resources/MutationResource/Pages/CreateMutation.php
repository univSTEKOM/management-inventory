<?php

namespace App\Filament\Inventory\Resources\MutationResource\Pages;

use App\Filament\Inventory\Resources\MutationResource;
use App\Models\Asset;
use App\Models\Campus;
use App\Models\Employee;
use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\Mutation;
use App\Services\DirectMutationService;
use App\Services\MutationSearchService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

class CreateMutation extends Page
{
    protected static string $resource = MutationResource::class;
    protected string $view = 'filament.inventory.mutations.create';
    protected static ?string $title = 'Mutasi Barang';

    public array $data = [
        'source_campus_id' => '', 'source_location_id' => '',
        'destination_campus_id' => '', 'destination_location_id' => '',
        'source_pic_id' => '', 'destination_pic_id' => '', 'reason' => '',
    ];
    public string $search = '';
    public array $items = [];
    public bool $confirming = false;
    #[Locked]
    public string $requestToken;

    public bool $showingNewPicModal = false;
    public string $newPicTarget = 'destination_pic_id';
    public string $newPicName = '';
    public string $newPicCode = '';
    public string $newPicDepartment = '';
    public string $newPicPhone = '';
    public string $newPicEmail = '';

    public function mount(): void
    {
        Gate::authorize('create', Mutation::class);
        $this->requestToken = (string) Str::uuid();
    }

    public function updatedData(mixed $value, string $key): void
    {
        $this->confirming = false;
        if (in_array($key, ['source_campus_id', 'source_location_id'])) {
            $this->items = [];
            $this->search = '';
        }
        if ($key === 'source_campus_id') {
            $this->data['source_location_id'] = '';
        }
        if ($key === 'destination_campus_id') {
            $this->data['destination_location_id'] = '';
        }
    }

    public function updatedItems(): void
    {
        $this->confirming = false;
    }

    public function addItem(string $key): void
    {
        Gate::authorize('create', Mutation::class);
        if (! preg_match('/^(asset|supply):([1-9][0-9]*)$/', $key, $match)) {
            return;
        }
        foreach ($this->items as $item) {
            if ($item['key'] === $key) {
                return;
            }
        }
        $model = $match[1] === 'asset' ? Asset::class : InventoryBalance::class;
        $item = $model::whereKey($match[2])->where('campus_id', $this->data['source_campus_id'])
            ->where('location_id', $this->data['source_location_id'])->firstOrFail();
        $this->items[] = MutationSearchService::describe($item) + ['quantity' => 1];
        $this->confirming = false;
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->confirming = false;
    }

    public function openNewPicModal(string $target = 'destination_pic_id'): void
    {
        $this->newPicTarget = in_array($target, ['source_pic_id', 'destination_pic_id'], true) ? $target : 'destination_pic_id';
        $this->newPicName = '';
        $this->newPicCode = '';
        $this->newPicDepartment = '';
        $this->newPicPhone = '';
        $this->newPicEmail = '';
        $this->resetErrorBag('newPicName');
        $this->resetErrorBag('newPicCode');
        $this->showingNewPicModal = true;
    }

    public function closeNewPicModal(): void
    {
        $this->showingNewPicModal = false;
    }

    public function saveNewPic(): void
    {
        $this->validate([
            'newPicName' => ['required', 'string', 'max:255'],
            'newPicCode' => ['nullable', 'string', 'max:255', 'unique:employees,employee_code'],
            'newPicDepartment' => ['nullable', 'string', 'max:255'],
            'newPicPhone' => ['nullable', 'string', 'max:255'],
            'newPicEmail' => ['nullable', 'email', 'max:255'],
        ], [
            'newPicName.required' => 'Nama lengkap PIC wajib diisi.',
            'newPicCode.unique' => 'Nomor induk / NIP sudah terdaftar.',
            'newPicEmail.email' => 'Format email tidak valid.',
        ]);

        $employee = Employee::create([
            'name' => trim($this->newPicName),
            'employee_code' => trim($this->newPicCode) ?: null,
            'department' => trim($this->newPicDepartment) ?: null,
            'phone' => trim($this->newPicPhone) ?: null,
            'email' => trim($this->newPicEmail) ?: null,
            'active' => true,
        ]);

        $this->data[$this->newPicTarget] = (string) $employee->id;
        $this->showingNewPicModal = false;

        \Filament\Notifications\Notification::make()
            ->title('PIC berhasil ditambahkan')
            ->body("Karyawan {$employee->name} telah ditambahkan dan dipilih sebagai " . ($this->newPicTarget === 'source_pic_id' ? 'PIC Asal' : 'PIC Tujuan') . '.')
            ->success()
            ->send();
    }

    public function sourceRouteSchema(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('source_campus_id')
                    ->label('Gedung Asal')
                    ->placeholder('-- Pilih Gedung Asal --')
                    ->options(fn () => Campus::orderBy('name')->pluck('name', 'id'))
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->afterStateUpdated(function (callable $set) {
                        $set('source_location_id', null);
                        $this->items = [];
                        $this->search = '';
                        $this->confirming = false;
                    }),

                Select::make('source_location_id')
                    ->label('Ruangan Asal')
                    ->placeholder(fn (callable $get) => blank($get('source_campus_id')) ? 'Pilih gedung asal terlebih dahulu' : '-- Pilih Ruangan Asal --')
                    ->options(fn (callable $get) => Location::where('campus_id', $get('source_campus_id'))->orderBy('name')->pluck('name', 'id'))
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->disabled(fn (callable $get) => blank($get('source_campus_id')))
                    ->afterStateUpdated(function () {
                        $this->items = [];
                        $this->search = '';
                        $this->confirming = false;
                    }),

                Select::make('source_pic_id')
                    ->label('PIC Asal (opsional)')
                    ->placeholder('-- Tanpa PIC Khusus --')
                    ->options(fn () => Employee::orderBy('name')->pluck('name', 'id'))
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->createOptionAction(
                        fn (Action $action) => $action
                            ->modalHeading('Tambah PIC Asal Baru')
                            ->modalWidth('md')
                    )
                    ->createOptionForm([
                        TextInput::make('name')->label('Nama Lengkap')->required(),
                        TextInput::make('employee_code')->label('NIP / Kode Karyawan')->unique('employees', 'employee_code'),
                        TextInput::make('department')->label('Departemen / Divisi'),
                        TextInput::make('phone')->label('No. Telepon / WhatsApp'),
                        TextInput::make('email')->label('Email')->email(),
                    ])
                    ->createOptionUsing(function (array $data) {
                        return Employee::create([
                            'name' => trim($data['name']),
                            'employee_code' => trim($data['employee_code'] ?? '') ?: null,
                            'department' => trim($data['department'] ?? '') ?: null,
                            'phone' => trim($data['phone'] ?? '') ?: null,
                            'email' => trim($data['email'] ?? '') ?: null,
                            'active' => true,
                        ])->id;
                    }),
            ]);
    }

    public function destinationRouteSchema(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('destination_campus_id')
                    ->label('Gedung Tujuan')
                    ->placeholder('-- Pilih Gedung Tujuan --')
                    ->options(fn () => Campus::orderBy('name')->pluck('name', 'id'))
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->afterStateUpdated(function (callable $set) {
                        $set('destination_location_id', null);
                        $this->confirming = false;
                    }),

                Select::make('destination_location_id')
                    ->label('Ruangan Tujuan')
                    ->placeholder(fn (callable $get) => blank($get('destination_campus_id')) ? 'Pilih gedung tujuan terlebih dahulu' : '-- Pilih Ruangan Tujuan --')
                    ->options(fn (callable $get) => Location::where('campus_id', $get('destination_campus_id'))->orderBy('name')->pluck('name', 'id'))
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->disabled(fn (callable $get) => blank($get('destination_campus_id')))
                    ->afterStateUpdated(function () {
                        $this->confirming = false;
                    }),

                Select::make('destination_pic_id')
                    ->label('PIC Tujuan (opsional)')
                    ->placeholder('-- Tanpa PIC Khusus --')
                    ->options(fn () => Employee::orderBy('name')->pluck('name', 'id'))
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->createOptionAction(
                        fn (Action $action) => $action
                            ->modalHeading('Tambah PIC Tujuan Baru')
                            ->modalWidth('md')
                    )
                    ->createOptionForm([
                        TextInput::make('name')->label('Nama Lengkap')->required(),
                        TextInput::make('employee_code')->label('NIP / Kode Karyawan')->unique('employees', 'employee_code'),
                        TextInput::make('department')->label('Departemen / Divisi'),
                        TextInput::make('phone')->label('No. Telepon / WhatsApp'),
                        TextInput::make('email')->label('Email')->email(),
                    ])
                    ->createOptionUsing(function (array $data) {
                        return Employee::create([
                            'name' => trim($data['name']),
                            'employee_code' => trim($data['employee_code'] ?? '') ?: null,
                            'department' => trim($data['department'] ?? '') ?: null,
                            'phone' => trim($data['phone'] ?? '') ?: null,
                            'email' => trim($data['email'] ?? '') ?: null,
                            'active' => true,
                        ])->id;
                    }),
            ]);
    }

    public function review(): void
    {
        Gate::authorize('create', Mutation::class);
        $this->validate([
            'data.source_campus_id' => ['required'], 'data.source_location_id' => ['required'],
            'data.destination_campus_id' => ['required'],
            'data.destination_location_id' => ['required', 'different:data.source_location_id'],
            'data.reason' => ['required', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
        $this->confirming = true;
    }

    public function process(): void
    {
        Gate::authorize('create', Mutation::class);
        abort_unless($this->confirming, 422);
        $data = $this->data;
        foreach (['source_pic_id', 'destination_pic_id'] as $key) {
            $data[$key] = $data[$key] ?: null;
        }
        try {
            $mutation = app(DirectMutationService::class)->create(auth()->user(), $data, $this->items, $this->requestToken);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->confirming = false;
            throw $exception;
        } catch (\Illuminate\Database\QueryException $exception) {
            report($exception);
            $this->confirming = false;
            $this->addError('items', 'Mutasi gagal diproses. Periksa kembali data dan coba lagi.');
            return;
        }
        \Filament\Notifications\Notification::make()->title('Mutasi berhasil diproses')->success()->send();
        if (auth()->user()->can('view', $mutation)) {
            $this->redirect(MutationResource::getUrl('view', ['record' => $mutation]));
        } else {
            $this->redirect(MutationResource::getUrl('create'));
        }
    }

    protected function getViewData(): array
    {
        return [
            'campuses' => Campus::orderBy('name')->get(),
            'employees' => Employee::orderBy('name')->get(),
            'sourceLocations' => Location::where('campus_id', $this->data['source_campus_id'])->orderBy('name')->get(),
            'destinationLocations' => Location::where('campus_id', $this->data['destination_campus_id'])->orderBy('name')->get(),
            'results' => MutationSearchService::search((int) $this->data['source_campus_id'], (int) $this->data['source_location_id'], $this->search),
        ];
    }
}

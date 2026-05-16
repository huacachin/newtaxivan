<?php
// app/Livewire/Owners/Edit.php
namespace App\Livewire\Owners;

use App\Models\Owner;
use App\Models\OwnerImage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use WithFileUploads;

    public Owner $owner;

    public $name;
    public $document_type = '';
    public $document_number;
    public $document_expiration_date;
    public $birthdate;
    public $address;
    public $district;
    public $email;
    public $phone;
    public $new_images = [];
    public $image_files = [];
    public array $existing_images = [];
    public array $deleted_image_ids = [];

    public function mount(int $id)
    {
        $this->owner = Owner::with('images')->find($id);

        $this->name                    = $this->owner->name;
        $this->document_type           = $this->owner->document_type;
        $this->document_number         = $this->owner->document_number;
        $this->document_expiration_date = optional($this->owner->document_expiration_date)?->format('Y-m-d');
        $this->birthdate               = optional($this->owner->birthdate)?->format('Y-m-d');
        $this->address                 = $this->owner->address;
        $this->district                = $this->owner->district;
        $this->email                   = $this->owner->email;
        $this->phone                   = $this->owner->phone;
        $this->existing_images = $this->owner->images->map(fn($img) => [
            'id'  => (int)$img->id,
            'url' => asset('storage/' . $img->image_path),
        ])->all();
    }

    protected $validationAttributes = [
        'document_type'   => 'tipo de documento',
        'document_number' => 'documento de identidad',
    ];

    public function rules()
    {
        return [
            'name'                     => 'required|string|max:255',
            'document_type'            => 'required|string|max:255',
            'document_number'          => 'required|string|max:255|unique:owners,document_number,' . $this->owner->id,
            'document_expiration_date' => 'nullable|date',
            'birthdate'                => 'nullable|date',
            'address'                  => 'nullable|string|max:255',
            'district'                 => 'nullable|string|max:255',
            'email'                    => 'nullable|string|email|max:255',
            'phone'                    => 'nullable|string|max:255',
            'new_images'               => 'nullable|array|max:10',
            'new_images.*'             => 'image|max:5120',
        ];
    }

    /**
     * Helper genérico para saber si una fecha (Y-m-d) está vencida.
     */
    protected function isExpired(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable $e) {
            return false;
        }

        return $date->lt(today());
    }

    // Propiedad computada que usaremos en el Blade
    public function getDocumentExpirationExpiredProperty(): bool
    {
        return $this->isExpired($this->document_expiration_date);
    }

    public function questionDelete(int $id): void
    {
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        if (!auth()->user()?->hasAnyRole('director','gerente','administrador')) {
            abort(403);
        }
        Owner::findOrFail($id)->update(['status' => 'inactive']);
        session()->flash('owner_success', 'Propietario eliminado correctamente.');
        $this->redirectRoute('settings.owners.index');
    }

    public function update()
    {
        try {
            $this->validate();

            // 'document_number' se omite intencionalmente: campo bloqueado en
            // edicion para preservar la identidad del propietario.
            $payload = [
                'name'                     => $this->name,
                'document_type'            => $this->document_type,
                'document_expiration_date' => $this->document_expiration_date,
                'birthdate'                => $this->birthdate,
                'address'                  => $this->address,
                'district'                 => $this->district,
                'email'                    => $this->email,
                'phone'                    => $this->phone,
            ];

            DB::transaction(function () use ($payload) {
                $this->owner->update($payload);

                if (!empty($this->deleted_image_ids)) {
                    $imagesToDelete = OwnerImage::where('owner_id', $this->owner->id)
                        ->whereIn('id', $this->deleted_image_ids)
                        ->get();
                    foreach ($imagesToDelete as $img) {
                        if ($img->image_path && Storage::disk('public')->exists($img->image_path)) {
                            Storage::disk('public')->delete($img->image_path);
                        }
                        $img->delete();
                    }
                }

                foreach ($this->image_files as $file) {
                    $path = $file->storePublicly('owners', 'public');
                    OwnerImage::create([
                        'owner_id'   => $this->owner->id,
                        'image_path' => $path,
                    ]);
                }
            });

            session()->flash('owner_success', 'Propietario actualizado correctamente.');
            $this->redirectRoute('settings.owners.index');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('owner_error', 'Error al actualizar: ' . $e->getMessage());
            $this->redirectRoute('settings.owners.index');
        }
    }

    public function updatedNewImages(): void
    {
        foreach ($this->new_images as $file) {
            $this->image_files[] = $file;
        }
        $this->new_images = [];
    }

    public function removeNewImage(int $index): void
    {
        array_splice($this->image_files, $index, 1);
    }

    public function removeExistingImage(int $imageId): void
    {
        if (!in_array($imageId, $this->deleted_image_ids, true)) {
            $this->deleted_image_ids[] = $imageId;
        }
    }

    public function restoreExistingImage(int $imageId): void
    {
        $this->deleted_image_ids = array_values(array_filter(
            $this->deleted_image_ids,
            fn($id) => $id !== $imageId
        ));
    }

    public function render()
    {
        return view('livewire.owners.edit');
    }
}

<?php

namespace App\Livewire\Admin;

use App\Enums\Peran;
use App\Models\Dokter;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class KelolaUser extends Component
{
    use WithPagination;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $peran = '';
    public ?int $dokter_id = null;

    public function simpan(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'peran' => ['required', Rule::in(Peran::semua())],
            'dokter_id' => ['nullable', 'exists:dokter,id'],
        ], [
            'name.required' => 'Nama pengguna wajib diisi.',
            'email.unique' => 'Email ini sudah dipakai pengguna lain.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'peran.required' => 'Peran wajib dipilih.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'dokter_id' => $data['dokter_id'] ?? null,
            'aktif' => true,
        ]);

        $user->syncRoles([$data['peran']]);

        $this->reset(['name', 'email', 'password', 'peran', 'dokter_id']);
        session()->flash('sukses', 'Pengguna baru tersimpan.');
    }

    public function nonaktifkan(int $id): void
    {
        User::findOrFail($id)->update(['aktif' => false]);
    }

    public function render()
    {
        return view('livewire.admin.kelola-user', [
            'daftarUser' => User::with('roles')->orderBy('name')->paginate(15),
            'daftarPeran' => Peran::cases(),
            'daftarDokter' => Dokter::orderBy('nama')->get(),
        ]);
    }
}

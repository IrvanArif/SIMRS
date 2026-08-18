<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use Livewire\Component;
use Livewire\WithPagination;

class PenampilAuditLog extends Component
{
    use WithPagination;

    public string $filterModel = '';
    public string $filterAksi = '';

    public function render()
    {
        $catatan = AuditLog::with('user')
            ->when($this->filterModel !== '', fn ($q) => $q->where('model_tipe', $this->filterModel))
            ->when($this->filterAksi !== '', fn ($q) => $q->where('aksi', $this->filterAksi))
            ->latest('id')
            ->paginate(25);

        return view('livewire.admin.penampil-audit-log', [
            'catatan' => $catatan,
            'daftarModel' => AuditLog::query()->distinct()->pluck('model_tipe'),
        ]);
    }
}

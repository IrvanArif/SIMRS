<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Support\KonteksAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PencatatAudit
{
    public function created(Model $model): void
    {
        $this->catat('create', $model, ['sesudah' => $model->getAttributes()]);
    }

    public function updated(Model $model): void
    {
        $sesudah = $model->getChanges();
        unset($sesudah['updated_at']);

        if ($sesudah === []) {
            return;
        }

        $this->catat('update', $model, [
            'sebelum' => array_intersect_key($model->getOriginal(), $sesudah),
            'sesudah' => $sesudah,
        ]);
    }

    public function deleted(Model $model): void
    {
        $this->catat('delete', $model, null);
    }

    public function restored(Model $model): void
    {
        $this->catat('restore', $model, null);
    }

    private function catat(string $aksi, Model $model, ?array $perubahan): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'aksi' => $aksi,
            'model_tipe' => $model::class,
            'model_id' => $model->getKey(),
            'perubahan' => $perubahan,
            'alasan' => KonteksAudit::alasan(),
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}

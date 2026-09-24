<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'subject', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public static function record(string $action, string $subject, array $details = []): void
    {
        static::create(['user_id' => auth()->id(), 'action' => $action, 'subject' => $subject, 'details' => $details]);
    }
}

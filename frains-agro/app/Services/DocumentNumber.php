<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumber
{
    public static function next(string $prefix): string
    {
        return DB::transaction(function () use ($prefix) {
            $key = $prefix.'-'.now()->format('Y');
            DB::table('document_sequences')->insertOrIgnore(['key' => $key, 'value' => 0]);
            $sequence = DB::table('document_sequences')->where('key', $key)->lockForUpdate()->first();
            DB::table('document_sequences')->where('key', $key)->update(['value' => $sequence->value + 1]);

            return $key.'-'.str_pad((string) ($sequence->value + 1), 6, '0', STR_PAD_LEFT);
        });
    }
}

<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\User;
use App\Notifications\PlatformNotification;

class PlatformNotice
{
    public static function staff(string $message, string $url, array $roles = ['ADMIN', 'MANAGER', 'COMMERCIAL']): void
    {
        User::where('status', 'active')->whereHas('role', fn ($q) => $q->whereIn('name', $roles))->each(fn ($u) => $u->notify(new PlatformNotification($message, $url)));
    }

    public static function stock(Stock $stock): void
    {
        $stock->refresh();
        if ($stock->available_quantity <= $stock->minimum_quantity) {
            self::staff('Stock à surveiller : '.$stock->product?->name.' ('.$stock->available_quantity.' '.$stock->product?->unit.')', route('admin.stocks.index'), ['ADMIN', 'MANAGER', 'STOCK_MANAGER']);
        }
    }
}

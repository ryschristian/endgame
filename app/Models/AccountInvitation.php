<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Hidden(['token_hash'])]
class AccountInvitation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function canAccept(): bool
    {
        return ! $this->accepted_at && ! $this->revoked_at && $this->expires_at->isFuture();
    }
}

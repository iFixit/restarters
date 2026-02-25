<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'token_hash',
        'scopes',
        'allowed_origins',
        'allowed_network_ids',
        'rate_limit_per_minute',
        'active',
        'expires_at',
        'last_used_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'allowed_origins' => 'array',
            'allowed_network_ids' => 'array',
            'active' => 'boolean',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?: [];
        return in_array($scope, $scopes, true);
    }

    public function hasExpired(): bool
    {
        return $this->expires_at && Carbon::now()->greaterThan($this->expires_at);
    }
}

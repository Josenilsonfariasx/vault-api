<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Get all users that belong to this tenant.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all accounts that belong to this tenant.
     */
    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Get all transactions that belong to this tenant.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}

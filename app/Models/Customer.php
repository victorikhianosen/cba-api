<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Hidden(['password', 'panic_password', 'pin'])]
class Customer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dob'                    => 'date',
            'is_staff'               => 'boolean',
            'pep'                    => 'boolean',
            'enable_internet_bank'   => 'boolean',
            'enable_sms'             => 'boolean',
            'enable_email'           => 'boolean',
            'enable_reset_password'  => 'boolean',
            'enable_panic_password'  => 'boolean',
            'id_verified'            => 'boolean',
            'face_verified'          => 'boolean',
            'utility_verified'       => 'boolean',
            'approved_at'            => 'datetime',
            'rejected_at'            => 'datetime',
            'closed_at'              => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function accountOfficer(): BelongsTo
    {
        return $this->belongsTo(AccountOfficer::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'guardian_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function nextOfKin(): HasMany
    {
        return $this->hasMany(NextOfKin::class);
    }

    public function business(): HasOne
    {
        return $this->hasOne(Business::class);
    }

    public function directors(): HasMany
    {
        return $this->hasMany(Director::class);
    }

    public function signatories(): HasMany
    {
        return $this->hasMany(Signatory::class);
    }
}

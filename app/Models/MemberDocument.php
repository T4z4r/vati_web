<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'member_id',
        'document_type',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'description',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the member that owns this document.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the user who uploaded this document.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get human-readable file size.
     */
    public function getHumanReadableSizeAttribute(): string
    {
        $size = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = max($size, 0);
        $pow = floor(($size ? log($size) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $size /= (1 << (10 * $pow));

        return round($size, 2) . ' ' . $units[$pow];
    }

    /**
     * Get document type label.
     */
    public function getDocumentTypeLabel(): string
    {
        return match ($this->document_type) {
            'national_id' => 'National ID',
            'voter_id' => 'Voter ID',
            'address_proof' => 'Address Proof',
            'business_license' => 'Business License',
            'passbook_scan' => 'Passbook Scan',
            'signature_card' => 'Signature Card',
            'other' => 'Other Document',
            default => ucfirst(str_replace('_', ' ', $this->document_type)),
        };
    }
}

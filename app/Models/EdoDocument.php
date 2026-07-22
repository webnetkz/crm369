<?php

namespace App\Models;

use App\Support\EdoDocumentFileManager;
use App\Support\EdoSignaturePayload;
use Database\Factories\EdoDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property string|null $external_reference
 * @property string $counterparty_name
 * @property string|null $counterparty_identifier
 * @property string|null $counterparty_email
 * @property string $content
 * @property string $document_source
 * @property int|null $source_file_entry_id
 * @property string|null $document_file_name
 * @property string|null $document_file_disk
 * @property string|null $document_file_path
 * @property string|null $document_file_mime_type
 * @property int|null $document_file_size_bytes
 * @property string|null $document_file_hash
 * @property string $status
 * @property string|null $public_token
 * @property Carbon|null $public_link_expires_at
 * @property string|null $signature_payload
 * @property string|null $signature_subject
 * @property string|null $signature_serial_number
 * @property string|null $signature_algorithm
 * @property string|null $signed_payload_hash
 * @property array<string, mixed>|null $signature_metadata
 * @property Carbon|null $signed_at
 * @property int $created_by_user_id
 * @property int $updated_by_user_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'title',
    'external_reference',
    'counterparty_name',
    'counterparty_identifier',
    'counterparty_email',
    'content',
    'document_source',
    'source_file_entry_id',
    'document_file_name',
    'document_file_disk',
    'document_file_path',
    'document_file_mime_type',
    'document_file_size_bytes',
    'document_file_hash',
    'status',
    'public_token',
    'public_link_expires_at',
    'signature_payload',
    'signature_subject',
    'signature_serial_number',
    'signature_algorithm',
    'signed_payload_hash',
    'signature_metadata',
    'signed_at',
    'created_by_user_id',
    'updated_by_user_id',
    'metadata',
])]
class EdoDocument extends Model
{
    public const string SOURCE_TEXT = 'text';

    public const string SOURCE_UPLOAD = 'upload';

    public const string SOURCE_FILE_ENTRY = 'file_entry';

    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_PENDING_SIGNATURE = 'pending_signature';

    public const string STATUS_SIGNED = 'signed';

    public const string STATUS_CANCELLED = 'cancelled';

    /** @use HasFactory<EdoDocumentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (EdoDocument $document): void {
            app(EdoDocumentFileManager::class)->deleteStoredFile($document);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'public_link_expires_at' => 'datetime',
            'signed_at' => 'datetime',
            'source_file_entry_id' => 'integer',
            'document_file_size_bytes' => 'integer',
            'signature_metadata' => 'array',
            'metadata' => 'array',
            'created_by_user_id' => 'integer',
            'updated_by_user_id' => 'integer',
        ];
    }

    /**
     * @param  Builder<EdoDocument>  $query
     * @return Builder<EdoDocument>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->canViewUsers()) {
            return $query;
        }

        return $query->where('created_by_user_id', $user->id);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return array<int, string>
     */
    public static function availableStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_SIGNATURE,
            self::STATUS_SIGNED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableSources(): array
    {
        return [
            self::SOURCE_TEXT,
            self::SOURCE_UPLOAD,
            self::SOURCE_FILE_ENTRY,
        ];
    }

    public static function newPublicToken(): string
    {
        do {
            $token = Str::random(40);
        } while (self::query()->where('public_token', $token)->exists());

        return $token;
    }

    public function issuePublicLink(): void
    {
        $this->forceFill([
            'public_token' => self::newPublicToken(),
            'public_link_expires_at' => now()->addHours(12),
            'status' => self::STATUS_PENDING_SIGNATURE,
        ])->save();
    }

    public function clearSignatureState(bool $invalidatePublicLink = true): void
    {
        $attributes = [
            'status' => self::STATUS_DRAFT,
            'signature_payload' => null,
            'signature_subject' => null,
            'signature_serial_number' => null,
            'signature_algorithm' => null,
            'signed_payload_hash' => null,
            'signature_metadata' => null,
            'signed_at' => null,
        ];

        if ($invalidatePublicLink) {
            $attributes['public_token'] = null;
            $attributes['public_link_expires_at'] = null;
        }

        $this->forceFill($attributes)->save();
    }

    public function markSigned(
        string $signaturePayload,
        ?string $signatureSubject,
        ?string $signatureSerialNumber,
        ?string $signatureAlgorithm,
        ?array $signatureMetadata = null,
    ): void {
        $this->forceFill([
            'status' => self::STATUS_SIGNED,
            'signature_payload' => $signaturePayload,
            'signature_subject' => $signatureSubject,
            'signature_serial_number' => $signatureSerialNumber,
            'signature_algorithm' => $signatureAlgorithm,
            'signed_payload_hash' => $this->signingPayloadHash(),
            'signature_metadata' => $signatureMetadata,
            'signed_at' => now(),
        ])->save();
    }

    public function isSigned(): bool
    {
        return $this->status === self::STATUS_SIGNED && $this->signed_at !== null;
    }

    public function hasActivePublicLink(): bool
    {
        return is_string($this->public_token)
            && $this->public_token !== ''
            && $this->public_link_expires_at?->isFuture() === true;
    }

    public function signingPayload(): string
    {
        return app(EdoSignaturePayload::class)->build($this);
    }

    public function signingPayloadHash(): string
    {
        return app(EdoSignaturePayload::class)->hash($this);
    }

    public function publicShowUrl(): ?string
    {
        if (! $this->hasActivePublicLink()) {
            return null;
        }

        return URL::temporarySignedRoute(
            'edo.public.show',
            $this->public_link_expires_at,
            ['edoDocument' => $this->public_token],
        );
    }

    public function publicSignUrl(): ?string
    {
        if (! $this->hasActivePublicLink()) {
            return null;
        }

        return URL::temporarySignedRoute(
            'edo.public.sign',
            $this->public_link_expires_at,
            ['edoDocument' => $this->public_token],
        );
    }

    public function publicDownloadUrl(): ?string
    {
        if (! $this->hasActivePublicLink() || ! $this->hasDocumentFile()) {
            return null;
        }

        return URL::temporarySignedRoute(
            'edo.public.download',
            $this->public_link_expires_at,
            ['edoDocument' => $this->public_token],
        );
    }

    public function hasDocumentFile(): bool
    {
        return is_string($this->document_file_disk)
            && $this->document_file_disk !== ''
            && is_string($this->document_file_path)
            && $this->document_file_path !== ''
            && is_string($this->document_file_name)
            && $this->document_file_name !== '';
    }
}

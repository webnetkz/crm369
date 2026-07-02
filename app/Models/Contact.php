<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $type
 * @property string $name
 * @property string|null $contact_person
 * @property string|null $position
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $notes
 * @property string|null $avatar_path
 * @property array<string, string|null>|null $company_requisites
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'type',
    'name',
    'contact_person',
    'position',
    'email',
    'phone',
    'notes',
    'avatar_path',
    'company_requisites',
    'created_by_user_id',
    'updated_by_user_id',
])]
class Contact extends Model
{
    public const string TYPE_PERSON = 'person';

    public const string TYPE_COMPANY = 'company';

    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Contact $contact): void {
            if ($contact->avatar_path) {
                Storage::disk('public')->delete($contact->avatar_path);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_by_user_id' => 'integer',
            'company_requisites' => 'array',
            'updated_by_user_id' => 'integer',
        ];
    }

    /**
     * @return array<string, array{label_key: string}>
     */
    public static function typeDefinitions(): array
    {
        return [
            self::TYPE_PERSON => [
                'label_key' => 'ui.contacts.type_person',
            ],
            self::TYPE_COMPANY => [
                'label_key' => 'ui.contacts.type_company',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableTypes(): array
    {
        return array_keys(self::typeDefinitions());
    }

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $types = $user->accessibleContactTypes();

        if ($types === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('type', $types);
    }

    public function typeLabel(): string
    {
        $definition = self::typeDefinitions()[$this->type] ?? null;

        return $definition !== null
            ? __($definition['label_key'])
            : $this->type;
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar_path);
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
     * @return HasMany<ContactComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ContactComment::class)
            ->latest('created_at')
            ->latest('id');
    }
}

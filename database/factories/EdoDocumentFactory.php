<?php

namespace Database\Factories;

use App\Models\EdoDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EdoDocument>
 */
class EdoDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $creator = User::factory();

        return [
            'title' => fake()->sentence(3),
            'external_reference' => fake()->optional()->bothify('EDO-####'),
            'counterparty_name' => fake()->company(),
            'counterparty_identifier' => fake()->numerify('############'),
            'counterparty_email' => fake()->optional()->safeEmail(),
            'content' => fake()->paragraphs(3, true),
            'document_source' => EdoDocument::SOURCE_TEXT,
            'source_file_entry_id' => null,
            'document_file_name' => null,
            'document_file_disk' => null,
            'document_file_path' => null,
            'document_file_mime_type' => null,
            'document_file_size_bytes' => null,
            'document_file_hash' => null,
            'status' => EdoDocument::STATUS_DRAFT,
            'public_token' => null,
            'public_link_expires_at' => null,
            'signature_payload' => null,
            'signature_subject' => null,
            'signature_serial_number' => null,
            'signature_algorithm' => null,
            'signed_payload_hash' => null,
            'signature_metadata' => null,
            'signed_at' => null,
            'created_by_user_id' => $creator,
            'updated_by_user_id' => $creator,
            'metadata' => null,
        ];
    }

    public function pendingSignature(): static
    {
        return $this->state(fn (): array => [
            'status' => EdoDocument::STATUS_PENDING_SIGNATURE,
            'public_token' => EdoDocument::newPublicToken(),
            'public_link_expires_at' => now()->addHours(12),
        ]);
    }

    public function signed(): static
    {
        $signedPayload = fake()->paragraphs(2, true);

        return $this->state(fn (): array => [
            'content' => $signedPayload,
            'status' => EdoDocument::STATUS_SIGNED,
            'signature_payload' => base64_encode('signed:'.$signedPayload),
            'signature_subject' => 'CN=Test Signer',
            'signature_serial_number' => fake()->numerify('########'),
            'signature_algorithm' => 'GOST3411',
            'signed_payload_hash' => hash('sha256', $signedPayload),
            'signature_metadata' => ['source' => 'factory'],
            'signed_at' => now(),
        ]);
    }
}

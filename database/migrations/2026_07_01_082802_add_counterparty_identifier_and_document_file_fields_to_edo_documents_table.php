<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('edo_documents', function (Blueprint $table): void {
            $table->string('counterparty_identifier', 12)->nullable()->after('counterparty_name')->index();
            $table->string('document_source')->default('text')->after('content');
            $table->foreignId('source_file_entry_id')->nullable()->after('document_source')->constrained('file_entries')->nullOnDelete();
            $table->string('document_file_name')->nullable()->after('source_file_entry_id');
            $table->string('document_file_disk')->nullable()->after('document_file_name');
            $table->string('document_file_path')->nullable()->after('document_file_disk');
            $table->string('document_file_mime_type')->nullable()->after('document_file_path');
            $table->unsignedBigInteger('document_file_size_bytes')->nullable()->after('document_file_mime_type');
            $table->string('document_file_hash', 64)->nullable()->after('document_file_size_bytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('edo_documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_file_entry_id');
            $table->dropIndex(['counterparty_identifier']);
            $table->dropColumn([
                'counterparty_identifier',
                'document_source',
                'document_file_name',
                'document_file_disk',
                'document_file_path',
                'document_file_mime_type',
                'document_file_size_bytes',
                'document_file_hash',
            ]);
        });
    }
};

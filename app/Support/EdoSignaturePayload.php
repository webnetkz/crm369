<?php

namespace App\Support;

use App\Models\EdoDocument;
use DOMDocument;
use DOMElement;

class EdoSignaturePayload
{
    public function build(EdoDocument $document): string
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $root = $xml->createElement('edo-document');
        $root->setAttribute('version', '1');
        $xml->appendChild($root);

        $this->appendNode($xml, $root, 'id', (string) $document->id);
        $this->appendNode($xml, $root, 'title', $document->title);
        $this->appendNode($xml, $root, 'external-reference', $document->external_reference);
        $this->appendNode($xml, $root, 'counterparty-name', $document->counterparty_name);
        $this->appendNode($xml, $root, 'counterparty-identifier', $document->counterparty_identifier);
        $this->appendNode($xml, $root, 'counterparty-email', $document->counterparty_email);
        $this->appendNode($xml, $root, 'document-source', $document->document_source);
        $this->appendNode(
            $xml,
            $root,
            'content',
            $document->document_source === EdoDocument::SOURCE_TEXT ? $document->content : null,
        );
        $this->appendDocumentFile($xml, $root, $document);
        $this->appendNode($xml, $root, 'updated-at', $document->updated_at?->toIso8601String());
        $this->appendNode(
            $xml,
            $root,
            'metadata',
            $document->metadata !== null ? json_encode($document->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        );

        return $xml->saveXML() ?: '';
    }

    public function hash(EdoDocument $document): string
    {
        return hash('sha256', $this->build($document));
    }

    private function appendNode(DOMDocument $xml, DOMElement $parent, string $name, ?string $value): void
    {
        $node = $xml->createElement($name);

        if ($value !== null) {
            $node->appendChild($xml->createTextNode($value));
        }

        $parent->appendChild($node);
    }

    private function appendDocumentFile(DOMDocument $xml, DOMElement $parent, EdoDocument $document): void
    {
        if (! $document->hasDocumentFile()) {
            return;
        }

        $file = $xml->createElement('document-file');
        $parent->appendChild($file);

        $this->appendNode($xml, $file, 'name', $document->document_file_name);
        $this->appendNode($xml, $file, 'mime-type', $document->document_file_mime_type);
        $this->appendNode(
            $xml,
            $file,
            'size-bytes',
            $document->document_file_size_bytes !== null ? (string) $document->document_file_size_bytes : null,
        );
        $this->appendNode($xml, $file, 'hash', $document->document_file_hash);
    }
}

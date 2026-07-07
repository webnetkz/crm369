<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait AssignsQrCode
{
    protected static function bootAssignsQrCode(): void
    {
        static::saving(function (Model $model): void {
            if (! is_string($model->qr_code) || trim($model->qr_code) === '') {
                /** @var self $model */
                $model->qr_code = $model->generateQrCode();
            }
        });
    }

    abstract protected function qrCodePrefix(): string;

    protected function generateQrCode(): string
    {
        return $this->qrCodePrefix().'-'.Str::upper((string) Str::ulid());
    }
}

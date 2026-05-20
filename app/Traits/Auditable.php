<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            static::auditCreate($model);
        });

        static::updated(function ($model) {
            static::auditUpdate($model);
        });

        static::deleted(function ($model) {
            static::auditDelete($model);
        });
    }

    private static function auditCreate($model): void
    {
        try {
            $fields = static::getAuditableFields($model);
            if (empty($fields)) {
                return;
            }

            $snapshot = collect($fields)
                ->mapWithKeys(fn($f) => [$f => $model->getAttribute($f)])
                ->all();

            ActivityLog::create([
                'user_id'       => static::resolveAuditUserId(),
                'model_type'    => class_basename($model),
                'model_id'      => $model->getKey(),
                'action'        => 'create',
                'field_changed' => null,
                'nilai_lama'    => null,
                'nilai_baru'    => json_encode($snapshot),
                'ip_address'    => static::resolveIp(),
                'user_agent'    => static::resolveUserAgent(),
            ]);
        } catch (\Throwable) {
            // Audit errors must never break the main operation
        }
    }

    private static function auditUpdate($model): void
    {
        try {
            $fields = static::getAuditableFields($model);
            if (empty($fields)) {
                return;
            }

            $dirty = $model->getDirty();
            $common = array_intersect(array_keys($dirty), $fields);

            foreach ($common as $field) {
                ActivityLog::create([
                    'user_id'       => static::resolveAuditUserId(),
                    'model_type'    => class_basename($model),
                    'model_id'      => $model->getKey(),
                    'action'        => 'update',
                    'field_changed' => $field,
                    'nilai_lama'    => (string) $model->getOriginal($field),
                    'nilai_baru'    => (string) $dirty[$field],
                    'ip_address'    => static::resolveIp(),
                    'user_agent'    => static::resolveUserAgent(),
                ]);
            }
        } catch (\Throwable) {
        }
    }

    private static function auditDelete($model): void
    {
        try {
            $fields = static::getAuditableFields($model);
            if (empty($fields)) {
                return;
            }

            $snapshot = collect($fields)
                ->mapWithKeys(fn($f) => [$f => $model->getAttribute($f)])
                ->all();

            ActivityLog::create([
                'user_id'       => static::resolveAuditUserId(),
                'model_type'    => class_basename($model),
                'model_id'      => $model->getKey(),
                'action'        => 'delete',
                'field_changed' => null,
                'nilai_lama'    => json_encode($snapshot),
                'nilai_baru'    => null,
                'ip_address'    => static::resolveIp(),
                'user_agent'    => static::resolveUserAgent(),
            ]);
        } catch (\Throwable) {
        }
    }

    private static function getAuditableFields($model): array
    {
        return property_exists($model, 'auditableFields')
            ? $model::$auditableFields
            : [];
    }

    private static function resolveAuditUserId(): ?int
    {
        if (PHP_SAPI === 'cli') {
            return null;
        }
        try {
            return auth()->id();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function resolveIp(): ?string
    {
        if (PHP_SAPI === 'cli') {
            return null;
        }
        try {
            return Request::ip();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function resolveUserAgent(): ?string
    {
        if (PHP_SAPI === 'cli') {
            return null;
        }
        try {
            return substr(Request::userAgent() ?? '', 0, 300);
        } catch (\Throwable) {
            return null;
        }
    }
}

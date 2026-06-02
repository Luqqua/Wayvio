<?php

namespace App\Services\InternalAdmin;

use InvalidArgumentException;

class InternalAdminActionRegistry
{
    /**
     * @return array<string,array<string,mixed>>
     */
    public function all(): array
    {
        return [
            'system_status' => [
                'command' => 'system:status',
                'params' => [],
            ],
            'user_list' => [
                'command' => 'user:list',
                'params' => [
                    'email' => ['artisan' => '--email', 'type' => 'string', 'max' => 191],
                    'status' => ['artisan' => '--status', 'type' => 'string', 'enum' => ['yes', 'no', 'blocked', 'active']],
                    'page' => ['artisan' => '--page', 'type' => 'int', 'min' => 1],
                    'show_email' => ['artisan' => '--show-email', 'type' => 'bool', 'default' => true],
                ],
            ],
            'user_view' => [
                'command' => 'user:view',
                'params' => [
                    'id' => ['artisan' => 'id', 'type' => 'int', 'required' => true, 'min' => 1],
                ],
            ],
            'user_links' => [
                'command' => 'user:links',
                'params' => [
                    'id' => ['artisan' => 'id', 'type' => 'int', 'required' => true, 'min' => 1],
                ],
            ],
            'hubs_list' => [
                'command' => 'hubs:list',
                'params' => [
                    'agency_user_id' => ['artisan' => '--agency_user_id', 'type' => 'int', 'min' => 1],
                    'status' => ['artisan' => '--status', 'type' => 'string', 'max' => 32],
                    'page' => ['artisan' => '--page', 'type' => 'int', 'min' => 1],
                ],
            ],
            'user_update_state' => [
                'command' => 'user:update-state',
                'params' => [
                    'user_id' => ['artisan' => 'user_id', 'type' => 'int', 'required' => true, 'min' => 1],
                    'status' => ['artisan' => '--status', 'type' => 'string', 'enum' => ['yes', 'no', 'blocked', 'active']],
                    'verification' => ['artisan' => '--verification', 'type' => 'string', 'enum' => ['verified', 'unverified', 'pending']],
                ],
            ],
            'user_delete' => [
                'command' => 'user:delete',
                'params' => [
                    'id' => ['artisan' => 'id', 'type' => 'int', 'required' => true, 'min' => 1],
                ],
            ],
            'role_set' => [
                'command' => 'role:set',
                'params' => [
                    'user_id' => ['artisan' => 'user_id', 'type' => 'int', 'required' => true, 'min' => 1],
                    'role' => ['artisan' => 'role', 'type' => 'string', 'required' => true, 'enum' => ['admin', 'vip', 'user']],
                ],
            ],
            'twofactor_disable' => [
                'command' => 'twofactor:disable',
                'params' => [
                    'user_id' => ['artisan' => 'user_id', 'type' => 'int', 'required' => true, 'min' => 1],
                    'force' => ['artisan' => '--force', 'type' => 'bool', 'default' => true],
                ],
            ],
            'link_toggle' => [
                'command' => 'link:toggle',
                'params' => [
                    'link_id' => ['artisan' => 'link_id', 'type' => 'int', 'required' => true, 'min' => 1],
                    'action' => ['artisan' => 'action', 'type' => 'string', 'required' => true, 'enum' => ['enable', 'disable']],
                ],
            ],
            'registration_set' => [
                'command' => 'registration:set',
                'params' => [
                    'state' => ['artisan' => 'state', 'type' => 'string', 'required' => true, 'enum' => ['enable', 'disable']],
                ],
            ],
            'subscription_list' => [
                'command' => 'subscription:list',
                'params' => [],
            ],
            'subscription_update' => [
                'command' => 'subscription:update',
                'params' => [
                    'id' => ['artisan' => 'id', 'type' => 'int', 'required' => true, 'min' => 1],
                    'plan' => ['artisan' => 'plan', 'type' => 'string', 'required' => true, 'max' => 64],
                    'months' => ['artisan' => '--months', 'type' => 'int', 'min' => 1],
                    'hubs' => ['artisan' => '--hubs', 'type' => 'int', 'min' => 1],
                    'delete' => ['artisan' => '--delete', 'type' => 'string', 'max' => 512],
                    'strategy' => ['artisan' => '--strategy', 'type' => 'string', 'enum' => ['least_links', 'newest', 'oldest']],
                    'dry_run' => ['artisan' => '--dry-run', 'type' => 'bool', 'default' => false],
                ],
            ],
            'tier_set' => [
                'command' => 'tier:set',
                'params' => [
                    'user_id' => ['artisan' => 'user_id', 'type' => 'int', 'required' => true, 'min' => 1],
                    'tier' => ['artisan' => 'tier', 'type' => 'string', 'required' => true, 'max' => 64],
                    'expires' => ['artisan' => '--expires', 'type' => 'string', 'max' => 32],
                    'months' => ['artisan' => '--months', 'type' => 'int', 'min' => 1],
                    'hubs' => ['artisan' => '--hubs', 'type' => 'int', 'min' => 1],
                    'delete' => ['artisan' => '--delete', 'type' => 'string', 'max' => 512],
                    'strategy' => ['artisan' => '--strategy', 'type' => 'string', 'enum' => ['least_links', 'newest', 'oldest']],
                    'dry_run' => ['artisan' => '--dry-run', 'type' => 'bool', 'default' => false],
                ],
            ],
            'partner_activate' => [
                'command' => 'partners:activate',
                'params' => [
                    'user_id' => ['artisan' => 'user_id', 'type' => 'int', 'required' => true, 'min' => 1],
                    'rate_bps' => ['artisan' => '--rate-bps', 'type' => 'int', 'min' => 1],
                    'connect' => ['artisan' => '--connect', 'type' => 'string', 'max' => 191],
                ],
            ],
            'partner_deactivate' => [
                'command' => 'partners:deactivate',
                'params' => [
                    'user_id' => ['artisan' => 'user_id', 'type' => 'int', 'required' => true, 'min' => 1],
                ],
            ],
            'partner_commissions_approve' => [
                'command' => 'partners:approve-commissions',
                'params' => [],
            ],
            'partner_payout_prepare' => [
                'command' => 'partners:payout-prepare',
                'params' => [
                    'partner_id' => ['artisan' => 'partner_id', 'type' => 'int', 'required' => true, 'min' => 1],
                    'dry_run' => ['artisan' => '--dry-run', 'type' => 'bool', 'default' => false],
                ],
            ],
            'partner_payout_execute' => [
                'command' => 'partners:payout-execute',
                'params' => [
                    'batch_id' => ['artisan' => 'batch_id', 'type' => 'int', 'required' => true, 'min' => 1],
                ],
            ],
            'partner_payout_settle' => [
                'command' => 'partners:payout-settle',
                'params' => [
                    'batch_id' => ['artisan' => 'batch_id', 'type' => 'int', 'required' => true, 'min' => 1],
                    'reference' => ['artisan' => '--reference', 'type' => 'string', 'required' => true, 'max' => 191],
                    'paid_at' => ['artisan' => '--paid-at', 'type' => 'string', 'max' => 64],
                ],
            ],
            'partner_payout_cancel' => [
                'command' => 'partners:payout-cancel',
                'params' => [
                    'batch_id' => ['artisan' => 'batch_id', 'type' => 'int', 'required' => true, 'min' => 1],
                ],
            ],
            'analytics_view' => [
                'command' => 'analytics:view',
                'params' => [
                    'user_id' => ['artisan' => 'user_id', 'type' => 'int', 'required' => true, 'min' => 1],
                ],
            ],
            'report_list' => [
                'command' => 'report:list',
                'params' => [
                    'page' => ['artisan' => '--page', 'type' => 'int', 'min' => 1],
                    'status' => ['artisan' => '--status', 'type' => 'string', 'max' => 32],
                    'user_id' => ['artisan' => '--user_id', 'type' => 'int', 'min' => 1],
                    'with_deleted' => ['artisan' => '--with-deleted', 'type' => 'bool', 'default' => false],
                ],
            ],
            'report_newest' => [
                'command' => 'report:newest',
                'params' => [
                    'page' => ['artisan' => '--page', 'type' => 'int', 'min' => 1],
                    'status' => ['artisan' => '--status', 'type' => 'string', 'max' => 32],
                    'user_id' => ['artisan' => '--user_id', 'type' => 'int', 'min' => 1],
                    'with_deleted' => ['artisan' => '--with-deleted', 'type' => 'bool', 'default' => false],
                ],
            ],
            'report_user' => [
                'command' => 'report:user',
                'params' => [
                    'user_id' => ['artisan' => 'user_id', 'type' => 'int', 'required' => true, 'min' => 1],
                    'with_deleted' => ['artisan' => '--with-deleted', 'type' => 'bool', 'default' => false],
                ],
            ],
            'report_delete' => [
                'command' => 'report:delete',
                'params' => [
                    'report_id' => ['artisan' => 'report_id', 'type' => 'int', 'required' => true, 'min' => 1],
                    'force' => ['artisan' => '--force', 'type' => 'bool', 'default' => false],
                ],
            ],
            'report_processed' => [
                'command' => 'report:processed',
                'params' => [
                    'report_id' => ['artisan' => 'report_id', 'type' => 'int', 'required' => true, 'min' => 1],
                    'undo' => ['artisan' => '--undo', 'type' => 'bool', 'default' => false],
                    'by_user' => ['artisan' => '--by-user', 'type' => 'int', 'min' => 1],
                ],
            ],
            'report_comment' => [
                'command' => 'report:comment',
                'params' => [
                    'report_id' => ['artisan' => 'report_id', 'type' => 'int', 'required' => true, 'min' => 1],
                    'comment' => ['artisan' => 'comment', 'type' => 'string', 'max' => 40],
                    'clear' => ['artisan' => '--clear', 'type' => 'bool', 'default' => false],
                ],
            ],
            'template_list' => [
                'command' => 'template:list',
                'params' => [],
            ],
            'template_assign' => [
                'command' => 'template:assign',
                'params' => [
                    'user_id' => ['artisan' => 'user_id', 'type' => 'int', 'required' => true, 'min' => 1],
                    'template_id' => ['artisan' => 'template_id', 'type' => 'string', 'required' => true, 'max' => 64],
                    'variant_id' => ['artisan' => 'variant_id', 'type' => 'string', 'max' => 64],
                ],
            ],
            'theme_list' => [
                'command' => 'theme:list',
                'params' => [],
            ],
            'theme_assign' => [
                'command' => 'theme:assign',
                'params' => [
                    'user_id' => ['artisan' => 'user_id', 'type' => 'int', 'required' => true, 'min' => 1],
                    'theme_id' => ['artisan' => 'theme_id', 'type' => 'int', 'required' => true, 'min' => 1],
                ],
            ],
            'backup_list' => [
                'command' => 'backup:list',
                'params' => [
                    'disk' => ['artisan' => '--disk', 'type' => 'string', 'max' => 64],
                ],
            ],
            'backup_status' => [
                'command' => 'backup:status',
                'params' => [
                    'json' => ['artisan' => '--json', 'type' => 'bool', 'default' => true],
                ],
            ],
            'backup_configure' => [
                'command' => 'backup:configure',
                'params' => [
                    'target_profile' => ['artisan' => '--target-profile', 'type' => 'string', 'enum' => ['local', 's3']],
                    'apply_profile_defaults' => ['artisan' => '--apply-profile-defaults', 'type' => 'bool', 'default' => false],
                    'db_auto' => ['artisan' => '--db-auto', 'type' => 'string', 'enum' => ['true', 'false']],
                    'db_interval' => ['artisan' => '--db-interval', 'type' => 'string', 'enum' => ['daily', 'weekly', 'monthly']],
                    'db_time' => ['artisan' => '--db-time', 'type' => 'string', 'max' => 5],
                    'db_disk' => ['artisan' => '--db-disk', 'type' => 'string', 'max' => 64],
                    'db_connection' => ['artisan' => '--db-connection', 'type' => 'string', 'max' => 32],
                    'media_auto' => ['artisan' => '--media-auto', 'type' => 'string', 'enum' => ['true', 'false']],
                    'media_interval' => ['artisan' => '--media-interval', 'type' => 'string', 'enum' => ['daily', 'weekly', 'monthly']],
                    'media_time' => ['artisan' => '--media-time', 'type' => 'string', 'max' => 5],
                    'media_target_disk' => ['artisan' => '--media-target-disk', 'type' => 'string', 'max' => 64],
                    'media_prefix' => ['artisan' => '--media-prefix', 'type' => 'string', 'max' => 255],
                    'media_health_max_age_hours' => ['artisan' => '--media-health-max-age-hours', 'type' => 'int', 'min' => 1],
                    'media_restore_drill' => ['artisan' => '--media-restore-drill', 'type' => 'string', 'enum' => ['true', 'false']],
                    'media_restore_drill_day' => ['artisan' => '--media-restore-drill-day', 'type' => 'int', 'min' => 1],
                    'media_restore_drill_time' => ['artisan' => '--media-restore-drill-time', 'type' => 'string', 'max' => 5],
                    'media_restore_drill_sample' => ['artisan' => '--media-restore-drill-sample', 'type' => 'int', 'min' => 1],
                    'full_auto' => ['artisan' => '--full-auto', 'type' => 'string', 'enum' => ['true', 'false']],
                    'full_interval' => ['artisan' => '--full-interval', 'type' => 'string', 'enum' => ['daily', 'weekly', 'monthly']],
                    'full_time' => ['artisan' => '--full-time', 'type' => 'string', 'max' => 5],
                    'full_db_disk' => ['artisan' => '--full-db-disk', 'type' => 'string', 'max' => 64],
                    'full_media_target_disk' => ['artisan' => '--full-media-target-disk', 'type' => 'string', 'max' => 64],
                    'full_media_prefix' => ['artisan' => '--full-media-prefix', 'type' => 'string', 'max' => 255],
                    'full_manifest_disk' => ['artisan' => '--full-manifest-disk', 'type' => 'string', 'max' => 64],
                    'full_prefix' => ['artisan' => '--full-prefix', 'type' => 'string', 'max' => 255],
                    'alert_webhook_url' => ['artisan' => '--alert-webhook-url', 'type' => 'string', 'max' => 512],
                    'json' => ['artisan' => '--json', 'type' => 'bool', 'default' => true],
                ],
            ],
            'backup_create' => [
                'command' => 'backup:create',
                'params' => [
                    'disk' => ['artisan' => '--disk', 'type' => 'string', 'max' => 64],
                    'path' => ['artisan' => '--path', 'type' => 'string', 'max' => 255],
                ],
            ],
            'backup_run_full' => [
                'command' => 'backup:run-full',
                'params' => [
                    'snapshot' => ['artisan' => '--snapshot', 'type' => 'string', 'max' => 64],
                    'db_disk' => ['artisan' => '--db-disk', 'type' => 'string', 'max' => 64],
                    'media_target_disk' => ['artisan' => '--media-target-disk', 'type' => 'string', 'max' => 64],
                    'media_prefix' => ['artisan' => '--media-prefix', 'type' => 'string', 'max' => 255],
                    'manifest_disk' => ['artisan' => '--manifest-disk', 'type' => 'string', 'max' => 64],
                    'manifest_prefix' => ['artisan' => '--manifest-prefix', 'type' => 'string', 'max' => 255],
                ],
            ],
            'media_backup_run' => [
                'command' => 'media:backup',
                'params' => [
                    'source_disk' => ['artisan' => '--source-disk', 'type' => 'string', 'max' => 64],
                    'target_disk' => ['artisan' => '--target-disk', 'type' => 'string', 'max' => 64],
                    'prefix' => ['artisan' => '--prefix', 'type' => 'string', 'max' => 255],
                    'snapshot' => ['artisan' => '--snapshot', 'type' => 'string', 'max' => 64],
                ],
            ],
            'media_backup_health' => [
                'command' => 'media:backup-health',
                'params' => [
                    'disk' => ['artisan' => '--disk', 'type' => 'string', 'max' => 64],
                    'prefix' => ['artisan' => '--prefix', 'type' => 'string', 'max' => 255],
                    'snapshot' => ['artisan' => '--snapshot', 'type' => 'string', 'max' => 64],
                    'max_age_hours' => ['artisan' => '--max-age-hours', 'type' => 'int', 'min' => 1],
                    'allow_failed_files' => ['artisan' => '--allow-failed-files', 'type' => 'bool', 'default' => false],
                ],
            ],
            'backup_restore' => [
                'command' => 'backup:restore',
                'params' => [
                    'backup_id' => ['artisan' => 'backup_id', 'type' => 'string', 'required' => true, 'max' => 128],
                    'disk' => ['artisan' => '--disk', 'type' => 'string', 'max' => 64],
                    'target' => ['artisan' => '--target', 'type' => 'string', 'max' => 255],
                ],
            ],
            'branding_set_site_asset' => [
                'command' => 'branding:set-site-asset',
                'params' => [
                    'type' => ['artisan' => 'type', 'type' => 'string', 'required' => true, 'enum' => ['logo', 'favicon']],
                    'source' => ['artisan' => 'source', 'type' => 'string', 'required' => true, 'max' => 255],
                ],
            ],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(string $actionKey): ?array
    {
        $all = $this->all();

        return $all[$actionKey] ?? null;
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $params
     *
     * @return array<string,mixed>
     */
    public function buildArtisanInput(array $definition, array $params): array
    {
        $input = [];
        $paramDefs = $definition['params'] ?? [];

        foreach ($paramDefs as $paramName => $paramDef) {
            $hasProvidedValue = array_key_exists($paramName, $params);
            $rawValue = $hasProvidedValue ? $params[$paramName] : null;

            if (!$hasProvidedValue && array_key_exists('default', $paramDef)) {
                $rawValue = $paramDef['default'];
                $hasProvidedValue = true;
            }

            $coerced = $this->coerceValue($paramName, $paramDef, $rawValue, $hasProvidedValue);

            if ($coerced === null) {
                continue;
            }

            $artisanKey = (string) ($paramDef['artisan'] ?? $paramName);
            $input[$artisanKey] = $coerced;
        }

        return $input;
    }

    /**
     * @param array<string,mixed> $paramDef
     */
    private function coerceValue(string $paramName, array $paramDef, mixed $rawValue, bool $hasProvidedValue): mixed
    {
        $required = (bool) ($paramDef['required'] ?? false);
        $type = (string) ($paramDef['type'] ?? 'string');

        if (!$hasProvidedValue) {
            if ($required) {
                throw new InvalidArgumentException("Missing required parameter '{$paramName}'");
            }

            return null;
        }

        if ($type === 'bool') {
            $boolValue = $this->toBool($rawValue);
            if (!$boolValue && !($paramDef['send_false'] ?? false)) {
                return null;
            }
            return $boolValue;
        }

        if ($rawValue === null) {
            if ($required) {
                throw new InvalidArgumentException("Missing required parameter '{$paramName}'");
            }
            return null;
        }

        if ($type === 'int') {
            if (is_int($rawValue)) {
                $value = $rawValue;
            } elseif (is_string($rawValue) && preg_match('/^-?\d+$/', trim($rawValue)) === 1) {
                $value = (int) trim($rawValue);
            } elseif (is_numeric($rawValue)) {
                $value = (int) $rawValue;
            } else {
                throw new InvalidArgumentException("Parameter '{$paramName}' must be an integer");
            }

            $min = $paramDef['min'] ?? null;
            if (is_int($min) && $value < $min) {
                throw new InvalidArgumentException("Parameter '{$paramName}' must be >= {$min}");
            }

            return $value;
        }

        $value = trim((string) $rawValue);
        if ($value === '') {
            if ($required) {
                throw new InvalidArgumentException("Parameter '{$paramName}' must not be empty");
            }
            return null;
        }

        $max = $paramDef['max'] ?? null;
        if (is_int($max) && mb_strlen($value) > $max) {
            throw new InvalidArgumentException("Parameter '{$paramName}' exceeds max length {$max}");
        }

        $enum = $paramDef['enum'] ?? null;
        if (is_array($enum) && !in_array($value, $enum, true)) {
            throw new InvalidArgumentException("Parameter '{$paramName}' must be one of: " . implode(', ', $enum));
        }

        return $value;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}

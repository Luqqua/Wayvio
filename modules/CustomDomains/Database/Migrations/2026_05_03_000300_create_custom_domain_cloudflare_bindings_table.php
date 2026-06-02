<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('custom_domain_cloudflare_bindings')) {
            Schema::create('custom_domain_cloudflare_bindings', function (Blueprint $table): void {
                $table->id();
                $table->string('hostname_norm', 253);
                $table->unsignedBigInteger('tenant_owner_user_id');
                $table->unsignedBigInteger('user_custom_domain_id')->nullable();
                $table->unsignedBigInteger('resource_user_id')->nullable();
                $table->unsignedBigInteger('page_id')->nullable();
                $table->string('cloudflare_hostname_id', 128)->nullable();
                $table->string('desired_state', 32)->default('active');
                $table->string('cloudflare_hostname_status', 64)->nullable();
                $table->string('cloudflare_ssl_status', 64)->nullable();
                $table->json('ownership_verification_json')->nullable();
                $table->json('ssl_validation_records_json')->nullable();
                $table->json('verification_errors_json')->nullable();
                $table->string('claim_token_hash', 128)->nullable();
                $table->timestamp('claim_verified_at')->nullable();
                $table->string('last_operation_id', 64)->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamp('delete_requested_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();

                $table->unique('hostname_norm', 'ucd_cf_host_uq');
                $table->unique('cloudflare_hostname_id', 'ucd_cf_id_uq');
                $table->index('tenant_owner_user_id', 'ucd_cf_tenant_idx');
                $table->index('user_custom_domain_id', 'ucd_cf_domain_idx');
                $table->index('resource_user_id', 'ucd_cf_resource_idx');
                $table->index('page_id', 'ucd_cf_page_idx');
                $table->index('desired_state', 'ucd_cf_state_idx');
                $table->index('cloudflare_hostname_status', 'ucd_cf_host_status_idx');
                $table->index('cloudflare_ssl_status', 'ucd_cf_ssl_status_idx');
                $table->index('claim_token_hash', 'ucd_cf_claim_idx');
                $table->index('last_operation_id', 'ucd_cf_op_idx');
                $table->index('last_synced_at', 'ucd_cf_synced_idx');
                $table->index('delete_requested_at', 'ucd_cf_delete_req_idx');
                $table->index('deleted_at', 'ucd_cf_deleted_idx');
                $table->index(['tenant_owner_user_id', 'desired_state'], 'ucd_cf_tenant_state_idx');
                $table->index(['tenant_owner_user_id', 'hostname_norm'], 'ucd_cf_tenant_host_idx');
            });
        }

        $this->ensureBindingColumns();
        $this->ensureBindingIndexes();

        if (!Schema::hasTable('user_custom_domains')) {
            return;
        }

        $hasTenantOwner = Schema::hasColumn('user_custom_domains', 'tenant_owner_user_id');
        $hasLifecycle = Schema::hasColumn('user_custom_domains', 'lifecycle_status');
        $hasCloudflareId = Schema::hasColumn('user_custom_domains', 'cloudflare_hostname_id');
        $hasHostnameStatus = Schema::hasColumn('user_custom_domains', 'cloudflare_hostname_status');
        $hasSslStatus = Schema::hasColumn('user_custom_domains', 'cloudflare_ssl_status');
        $hasOwnership = Schema::hasColumn('user_custom_domains', 'cloudflare_ownership_verification');
        $hasValidationRecords = Schema::hasColumn('user_custom_domains', 'cloudflare_ssl_validation_records');
        $hasVerificationErrors = Schema::hasColumn('user_custom_domains', 'cloudflare_verification_errors');
        $hasLastSynced = Schema::hasColumn('user_custom_domains', 'cloudflare_last_synced_at');

        DB::statement(sprintf(
            "INSERT IGNORE INTO custom_domain_cloudflare_bindings
                (hostname_norm, tenant_owner_user_id, user_custom_domain_id, resource_user_id, page_id,
                 cloudflare_hostname_id, desired_state, cloudflare_hostname_status, cloudflare_ssl_status,
                 ownership_verification_json, ssl_validation_records_json, verification_errors_json,
                 last_synced_at, created_at, updated_at)
             SELECT LOWER(domain),
                    %s,
                    id,
                    user_id,
                    page_id,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    NOW(),
                    NOW()
             FROM user_custom_domains
             WHERE domain IS NOT NULL AND domain <> ''",
            $hasTenantOwner ? 'COALESCE(tenant_owner_user_id, user_id)' : 'user_id',
            $hasCloudflareId ? 'cloudflare_hostname_id' : 'NULL',
            $hasLifecycle ? "COALESCE(NULLIF(lifecycle_status, ''), 'active')" : "'active'",
            $hasHostnameStatus ? 'cloudflare_hostname_status' : 'NULL',
            $hasSslStatus ? 'cloudflare_ssl_status' : 'NULL',
            $hasOwnership ? 'cloudflare_ownership_verification' : 'NULL',
            $hasValidationRecords ? 'cloudflare_ssl_validation_records' : 'NULL',
            $hasVerificationErrors ? 'cloudflare_verification_errors' : 'NULL',
            $hasLastSynced ? 'cloudflare_last_synced_at' : 'NULL'
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_domain_cloudflare_bindings');
    }

    private function ensureBindingColumns(): void
    {
        $tableName = 'custom_domain_cloudflare_bindings';

        $columns = [
            'hostname_norm' => fn (Blueprint $table) => $table->string('hostname_norm', 253),
            'tenant_owner_user_id' => fn (Blueprint $table) => $table->unsignedBigInteger('tenant_owner_user_id'),
            'user_custom_domain_id' => fn (Blueprint $table) => $table->unsignedBigInteger('user_custom_domain_id')->nullable(),
            'resource_user_id' => fn (Blueprint $table) => $table->unsignedBigInteger('resource_user_id')->nullable(),
            'page_id' => fn (Blueprint $table) => $table->unsignedBigInteger('page_id')->nullable(),
            'cloudflare_hostname_id' => fn (Blueprint $table) => $table->string('cloudflare_hostname_id', 128)->nullable(),
            'desired_state' => fn (Blueprint $table) => $table->string('desired_state', 32)->default('active'),
            'cloudflare_hostname_status' => fn (Blueprint $table) => $table->string('cloudflare_hostname_status', 64)->nullable(),
            'cloudflare_ssl_status' => fn (Blueprint $table) => $table->string('cloudflare_ssl_status', 64)->nullable(),
            'ownership_verification_json' => fn (Blueprint $table) => $table->json('ownership_verification_json')->nullable(),
            'ssl_validation_records_json' => fn (Blueprint $table) => $table->json('ssl_validation_records_json')->nullable(),
            'verification_errors_json' => fn (Blueprint $table) => $table->json('verification_errors_json')->nullable(),
            'claim_token_hash' => fn (Blueprint $table) => $table->string('claim_token_hash', 128)->nullable(),
            'claim_verified_at' => fn (Blueprint $table) => $table->timestamp('claim_verified_at')->nullable(),
            'last_operation_id' => fn (Blueprint $table) => $table->string('last_operation_id', 64)->nullable(),
            'last_synced_at' => fn (Blueprint $table) => $table->timestamp('last_synced_at')->nullable(),
            'delete_requested_at' => fn (Blueprint $table) => $table->timestamp('delete_requested_at')->nullable(),
            'deleted_at' => fn (Blueprint $table) => $table->timestamp('deleted_at')->nullable(),
            'created_at' => fn (Blueprint $table) => $table->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $table) => $table->timestamp('updated_at')->nullable(),
        ];

        foreach ($columns as $column => $addColumn) {
            if (Schema::hasColumn($tableName, $column)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($addColumn): void {
                $addColumn($table);
            });
        }
    }

    private function ensureBindingIndexes(): void
    {
        $this->ensureIndex('ucd_cf_host_uq', 'CREATE UNIQUE INDEX ucd_cf_host_uq ON custom_domain_cloudflare_bindings (hostname_norm)');
        $this->ensureIndex('ucd_cf_id_uq', 'CREATE UNIQUE INDEX ucd_cf_id_uq ON custom_domain_cloudflare_bindings (cloudflare_hostname_id)');
        $this->ensureIndex('ucd_cf_tenant_idx', 'CREATE INDEX ucd_cf_tenant_idx ON custom_domain_cloudflare_bindings (tenant_owner_user_id)');
        $this->ensureIndex('ucd_cf_domain_idx', 'CREATE INDEX ucd_cf_domain_idx ON custom_domain_cloudflare_bindings (user_custom_domain_id)');
        $this->ensureIndex('ucd_cf_resource_idx', 'CREATE INDEX ucd_cf_resource_idx ON custom_domain_cloudflare_bindings (resource_user_id)');
        $this->ensureIndex('ucd_cf_page_idx', 'CREATE INDEX ucd_cf_page_idx ON custom_domain_cloudflare_bindings (page_id)');
        $this->ensureIndex('ucd_cf_state_idx', 'CREATE INDEX ucd_cf_state_idx ON custom_domain_cloudflare_bindings (desired_state)');
        $this->ensureIndex('ucd_cf_host_status_idx', 'CREATE INDEX ucd_cf_host_status_idx ON custom_domain_cloudflare_bindings (cloudflare_hostname_status)');
        $this->ensureIndex('ucd_cf_ssl_status_idx', 'CREATE INDEX ucd_cf_ssl_status_idx ON custom_domain_cloudflare_bindings (cloudflare_ssl_status)');
        $this->ensureIndex('ucd_cf_claim_idx', 'CREATE INDEX ucd_cf_claim_idx ON custom_domain_cloudflare_bindings (claim_token_hash)');
        $this->ensureIndex('ucd_cf_op_idx', 'CREATE INDEX ucd_cf_op_idx ON custom_domain_cloudflare_bindings (last_operation_id)');
        $this->ensureIndex('ucd_cf_synced_idx', 'CREATE INDEX ucd_cf_synced_idx ON custom_domain_cloudflare_bindings (last_synced_at)');
        $this->ensureIndex('ucd_cf_delete_req_idx', 'CREATE INDEX ucd_cf_delete_req_idx ON custom_domain_cloudflare_bindings (delete_requested_at)');
        $this->ensureIndex('ucd_cf_deleted_idx', 'CREATE INDEX ucd_cf_deleted_idx ON custom_domain_cloudflare_bindings (deleted_at)');
        $this->ensureIndex('ucd_cf_tenant_state_idx', 'CREATE INDEX ucd_cf_tenant_state_idx ON custom_domain_cloudflare_bindings (tenant_owner_user_id, desired_state)');
        $this->ensureIndex('ucd_cf_tenant_host_idx', 'CREATE INDEX ucd_cf_tenant_host_idx ON custom_domain_cloudflare_bindings (tenant_owner_user_id, hostname_norm)');
    }

    private function ensureIndex(string $indexName, string $sql): void
    {
        $rows = DB::select(
            'SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            ['custom_domain_cloudflare_bindings', $indexName]
        );

        if ($rows !== []) {
            return;
        }

        DB::statement($sql);
    }
};

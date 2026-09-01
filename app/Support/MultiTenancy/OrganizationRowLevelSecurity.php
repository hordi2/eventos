<?php

declare(strict_types=1);

namespace App\Support\MultiTenancy;

use Illuminate\Support\Facades\DB;

/**
 * Active la row-level security PostgreSQL sur une table cloisonnée par organisation.
 *
 * Seconde barrière derrière le trait BelongsToOrganization : bloque même une
 * requête SQL brute mal filtrée, y compris pour l'utilisateur propriétaire de
 * la table (FORCE ROW LEVEL SECURITY). À appeler depuis la migration de toute
 * table métier possédant une colonne organization_id — jamais sur les tables
 * de résolution du tenant lui-même (organizations, memberships).
 */
final class OrganizationRowLevelSecurity
{
    public static function enable(string $table): void
    {
        DB::statement("alter table \"{$table}\" enable row level security");
        DB::statement("alter table \"{$table}\" force row level security");
        // NULLIF(...) transforme en NULL la chaîne vide que PostgreSQL renvoie
        // après un RESET (le paramètre n'a alors plus de valeur, mais n'est
        // pas NULL pour autant) : sans cela, le cast ::bigint échoue au lieu
        // de bloquer proprement l'accès.
        DB::statement(
            "create policy \"{$table}_organization_isolation\" on \"{$table}\"
                using (organization_id = nullif(current_setting('app.current_organization_id', true), '')::bigint)
                with check (organization_id = nullif(current_setting('app.current_organization_id', true), '')::bigint)"
        );
    }

    public static function disable(string $table): void
    {
        DB::statement("drop policy if exists \"{$table}_organization_isolation\" on \"{$table}\"");
        DB::statement("alter table \"{$table}\" disable row level security");
    }
}

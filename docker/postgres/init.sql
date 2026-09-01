-- Base séparée pour les tests Pest (exigence CLAUDE.md : jamais SQLite).
CREATE DATABASE eventos_testing;

-- L'utilisateur POSTGRES_USER (eventos) créé par l'image officielle est
-- TOUJOURS superutilisateur : un superutilisateur ignore la row-level
-- security même avec FORCE ROW LEVEL SECURITY. L'application ne doit
-- jamais se connecter avec ce rôle. On crée donc un rôle applicatif dédié,
-- non-superutilisateur, propriétaire des bases, pour que la RLS protège
-- réellement les données.
CREATE ROLE eventos_app WITH LOGIN PASSWORD 'eventos_app' NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS;

ALTER DATABASE eventos OWNER TO eventos_app;
GRANT ALL PRIVILEGES ON SCHEMA public TO eventos_app;

ALTER DATABASE eventos_testing OWNER TO eventos_app;
\connect eventos_testing
GRANT ALL PRIVILEGES ON SCHEMA public TO eventos_app;

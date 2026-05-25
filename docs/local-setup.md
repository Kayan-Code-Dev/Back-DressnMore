# Local Setup

## 1) Install dependencies

```bash
composer install
```

## 2) Environment

Copy environment file:

```bash
cp .env.example .env
```

Configure:

- `DB_CONNECTION=central`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `TENANT_DB_DATABASE` (template/default tenant DB name)
- `PLATFORM_ADMIN_NAME`, `PLATFORM_ADMIN_EMAIL`, `PLATFORM_ADMIN_PASSWORD`

## 3) Generate key

```bash
php artisan key:generate
```

## 4) Run central migrations

```bash
php artisan migrate --database=central
```

## 5) Seed central data

```bash
php artisan db:seed --database=central
```

## 6) Tenant migrations (when tenant DB is selected)

```bash
php artisan migrate --database=tenant --path=database/migrations/tenant --realpath
```

## 7) Tenant seeders (optional)

```bash
php artisan db:seed --database=tenant --class="Database\\Seeders\\Tenant\\TenantRolePermissionSeeder"
php artisan db:seed --database=tenant --class="Database\\Seeders\\Tenant\\TenantSettingsSeeder"
```

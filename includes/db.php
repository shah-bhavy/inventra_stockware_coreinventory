<?php
// ── Database layer ────────────────────────────────────────────────────────────
// Uses SQLite so no external database server is required.
// The .db file is stored in data/ (auto-created on first run).

define('DB_PATH', __DIR__ . '/../data/core_inventory.db');

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    _initSchema($pdo);
    return $pdo;
}

function _initSchema(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            login_id   TEXT UNIQUE NOT NULL,
            name       TEXT NOT NULL,
            email      TEXT UNIQUE NOT NULL,
            password   TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS warehouses (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT NOT NULL,
            code       TEXT UNIQUE NOT NULL,
            address    TEXT DEFAULT '',
            active     INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS locations (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            warehouse_id INTEGER NOT NULL,
            name         TEXT NOT NULL,
            code         TEXT NOT NULL,
            active       INTEGER DEFAULT 1,
            FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
        );

        CREATE TABLE IF NOT EXISTS product_categories (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS products (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            name            TEXT NOT NULL,
            sku             TEXT UNIQUE NOT NULL,
            category_id     INTEGER,
            unit_of_measure TEXT DEFAULT 'Units',
            reorder_qty     REAL DEFAULT 0,
            description     TEXT DEFAULT '',
            active          INTEGER DEFAULT 1,
            created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES product_categories(id)
        );

        CREATE TABLE IF NOT EXISTS stock_levels (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id  INTEGER NOT NULL,
            location_id INTEGER NOT NULL,
            qty         REAL DEFAULT 0,
            UNIQUE(product_id, location_id),
            FOREIGN KEY (product_id)  REFERENCES products(id),
            FOREIGN KEY (location_id) REFERENCES locations(id)
        );

        CREATE TABLE IF NOT EXISTS suppliers (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            name           TEXT NOT NULL,
            contact_person TEXT DEFAULT '',
            phone          TEXT DEFAULT '',
            email          TEXT DEFAULT '',
            address        TEXT DEFAULT '',
            active         INTEGER DEFAULT 1,
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS customers (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            name           TEXT NOT NULL,
            contact_person TEXT DEFAULT '',
            phone          TEXT DEFAULT '',
            email          TEXT DEFAULT '',
            address        TEXT DEFAULT '',
            active         INTEGER DEFAULT 1,
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS receipts (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            reference  TEXT UNIQUE NOT NULL,
            supplier_id INTEGER,
            supplier   TEXT DEFAULT '',
            purpose    TEXT DEFAULT '',
            status     TEXT DEFAULT 'Draft',
            notes      TEXT DEFAULT '',
            schedule_date TEXT,
            receive_from_location TEXT DEFAULT '',
            responsible TEXT DEFAULT '',
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS receipt_items (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            receipt_id   INTEGER NOT NULL,
            product_id   INTEGER NOT NULL,
            location_id  INTEGER,
            qty_expected REAL DEFAULT 0,
            qty_received REAL DEFAULT 0,
            FOREIGN KEY (receipt_id)  REFERENCES receipts(id)  ON DELETE CASCADE,
            FOREIGN KEY (product_id)  REFERENCES products(id),
            FOREIGN KEY (location_id) REFERENCES locations(id)
        );

        CREATE TABLE IF NOT EXISTS deliveries (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            reference  TEXT UNIQUE NOT NULL,
            customer_id INTEGER,
            customer   TEXT DEFAULT '',
            order_reference TEXT DEFAULT '',
            status     TEXT DEFAULT 'Draft',
            notes      TEXT DEFAULT '',
            delivery_address TEXT DEFAULT '',
            schedule_date TEXT,
            responsible TEXT DEFAULT '',
            operation_type TEXT DEFAULT '',
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS delivery_items (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            delivery_id   INTEGER NOT NULL,
            product_id    INTEGER NOT NULL,
            location_id   INTEGER,
            qty_ordered   REAL DEFAULT 0,
            qty_delivered REAL DEFAULT 0,
            FOREIGN KEY (delivery_id)  REFERENCES deliveries(id)  ON DELETE CASCADE,
            FOREIGN KEY (product_id)   REFERENCES products(id),
            FOREIGN KEY (location_id)  REFERENCES locations(id)
        );

        CREATE TABLE IF NOT EXISTS transfers (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            reference        TEXT UNIQUE NOT NULL,
            from_location_id INTEGER,
            to_location_id   INTEGER,
            status           TEXT DEFAULT 'Draft',
            notes            TEXT DEFAULT '',
            responsible      TEXT DEFAULT '',
            created_by       INTEGER,
            created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (from_location_id) REFERENCES locations(id),
            FOREIGN KEY (to_location_id)   REFERENCES locations(id),
            FOREIGN KEY (created_by)       REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS transfer_items (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            transfer_id INTEGER NOT NULL,
            product_id  INTEGER NOT NULL,
            qty         REAL DEFAULT 0,
            FOREIGN KEY (transfer_id) REFERENCES transfers(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id)  REFERENCES products(id)
        );

        CREATE TABLE IF NOT EXISTS adjustments (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            reference   TEXT UNIQUE NOT NULL,
            location_id INTEGER,
            status      TEXT DEFAULT 'Draft',
            notes       TEXT DEFAULT '',
            created_by  INTEGER,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (location_id) REFERENCES locations(id),
            FOREIGN KEY (created_by)  REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS adjustment_items (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            adjustment_id INTEGER NOT NULL,
            product_id    INTEGER NOT NULL,
            system_qty    REAL DEFAULT 0,
            counted_qty   REAL DEFAULT 0,
            difference    REAL DEFAULT 0,
            FOREIGN KEY (adjustment_id) REFERENCES adjustments(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id)    REFERENCES products(id)
        );

        CREATE TABLE IF NOT EXISTS stock_ledger (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id     INTEGER NOT NULL,
            location_id    INTEGER,
            operation_type TEXT NOT NULL,
            reference      TEXT DEFAULT '',
            qty_change     REAL NOT NULL,
            qty_after      REAL NOT NULL,
            notes          TEXT DEFAULT '',
            created_by     INTEGER,
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id)  REFERENCES products(id),
            FOREIGN KEY (location_id) REFERENCES locations(id)
        );

        CREATE TABLE IF NOT EXISTS settings (
            setting_key   TEXT PRIMARY KEY,
            setting_value TEXT NOT NULL
        );

        CREATE INDEX IF NOT EXISTS idx_receipts_supplier_id ON receipts(supplier_id);
        CREATE INDEX IF NOT EXISTS idx_deliveries_customer_id ON deliveries(customer_id);
    ");

    /* Seed default data on first run */
    $count = $db->query("SELECT COUNT(*) FROM warehouses")->fetchColumn();
    if ((int)$count === 0) {
        $db->exec("
            INSERT INTO warehouses (name, code, address) VALUES ('Main Warehouse', 'WH01', 'Main Building');
            INSERT INTO locations (warehouse_id, name, code) VALUES (1, 'Main Store',      'WH01-MAIN');
            INSERT INTO locations (warehouse_id, name, code) VALUES (1, 'Production Rack', 'WH01-PROD');
            INSERT INTO locations (warehouse_id, name, code) VALUES (1, 'Receiving Bay',   'WH01-RCV');
            INSERT INTO product_categories (name) VALUES ('Raw Materials');
            INSERT INTO product_categories (name) VALUES ('Finished Goods');
            INSERT INTO product_categories (name) VALUES ('Consumables');
            INSERT INTO product_categories (name) VALUES ('Spare Parts');
        ");
    }

    // Schema migration support for existing SQLite files.
    _ensureColumn($db, 'receipts', 'supplier_id', 'INTEGER');
    _ensureColumn($db, 'receipts', 'purpose', "TEXT DEFAULT ''");
    _ensureColumn($db, 'receipts', 'schedule_date', 'TEXT');
    _ensureColumn($db, 'receipts', 'receive_from_location', "TEXT DEFAULT ''");
    _ensureColumn($db, 'receipts', 'responsible', "TEXT DEFAULT ''");
    _ensureColumn($db, 'deliveries', 'customer_id', 'INTEGER');
    _ensureColumn($db, 'deliveries', 'order_reference', "TEXT DEFAULT ''");
    _ensureColumn($db, 'deliveries', 'delivery_address', "TEXT DEFAULT ''");
    _ensureColumn($db, 'deliveries', 'schedule_date', 'TEXT');
    _ensureColumn($db, 'deliveries', 'responsible', "TEXT DEFAULT ''");
    _ensureColumn($db, 'deliveries', 'operation_type', "TEXT DEFAULT ''");
    _ensureColumn($db, 'product_categories', 'active', 'INTEGER DEFAULT 1');
    _ensureColumn($db, 'users', 'login_id', 'TEXT');
    _ensureColumn($db, 'transfers', 'responsible', "TEXT DEFAULT ''");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_receipts_supplier_id ON receipts(supplier_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_deliveries_customer_id ON deliveries(customer_id)");
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_login_id ON users(login_id)");
}

function _tableHasColumn(PDO $db, string $table, string $column): bool
{
    $cols = $db->query("PRAGMA table_info($table)")->fetchAll();
    foreach ($cols as $c) {
        if (($c['name'] ?? '') === $column) return true;
    }
    return false;
}

function _ensureColumn(PDO $db, string $table, string $column, string $definition): void
{
    if (!_tableHasColumn($db, $table, $column)) {
        $db->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

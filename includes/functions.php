<?php
// ── Shared helper functions ───────────────────────────────────────────────────

/** Html-encode a value safely */
function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirect and exit */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Store a flash message */
function flash(string $key, string $msg): void
{
    $_SESSION['flash'][$key] = $msg;
}

/** Retrieve and clear a flash message */
function getFlash(string $key): ?string
{
    if (!empty($_SESSION['flash'][$key])) {
        $m = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $m;
    }
    return null;
}

/** Emit JSON and exit */
function jsonOut(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/** Generate a unique document reference (legacy fallback) */
function genRef(string $prefix): string
{
    return $prefix . '/' . date('Ymd') . '/' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

/** Sequential reference for receipts: WH/IN/0001, WH/IN/0002, ... */
function nextReceiptRef(): string
{
    try {
        $current = (int)((getSetting('seq_receipt', '0')) ?? '0');
        $current++;
        setSetting('seq_receipt', (string)$current);
        return sprintf('WH/IN/%04d', $current);
    } catch (Throwable $e) {
        // Fallback to legacy generator if settings are unavailable
        return genRef('REC');
    }
}

/** Sequential reference for deliveries: WH/OUT/0001, WH/OUT/0002, ... */
function nextDeliveryRef(): string
{
    try {
        $current = (int)((getSetting('seq_delivery', '0')) ?? '0');
        $current++;
        setSetting('seq_delivery', (string)$current);
        return sprintf('WH/OUT/%04d', $current);
    } catch (Throwable $e) {
        // Fallback to legacy generator if settings are unavailable
        return genRef('DEL');
    }
}

/** CSRF token */
function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Verify CSRF on POST; aborts on failure */
function verifyCsrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf'] ?? '';
        if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
            http_response_code(403);
            exit('Invalid security token. Please go back and try again.');
        }
    }
}

/** Render an HTML status badge */
function statusBadge(string $status): string
{
    $map = [
        'Draft'    => 'badge-gray',
        'Waiting'  => 'badge-yellow',
        'Picked'   => 'badge-yellow',
        'Ready'    => 'badge-blue',
        'Packed'   => 'badge-blue',
        'Done'     => 'badge-green',
        'Canceled' => 'badge-red',
    ];
    $cls = $map[$status] ?? 'badge-gray';
    return '<span class="badge ' . $cls . '">' . e($status) . '</span>';
}

/** Total stock for a product (optionally at a specific location) */
function stockQty(int $productId, ?int $locationId = null): float
{
    $db = getDB();
    if ($locationId !== null) {
        $st = $db->prepare("SELECT COALESCE(qty,0) FROM stock_levels WHERE product_id=? AND location_id=?");
        $st->execute([$productId, $locationId]);
    } else {
        $st = $db->prepare("SELECT COALESCE(SUM(qty),0) FROM stock_levels WHERE product_id=?");
        $st->execute([$productId]);
    }
    return (float)$st->fetchColumn();
}

/**
 * Adjust stock level and write to ledger.
 * $change can be positive (in) or negative (out).
 */
function adjustStock(
    int $productId,
    int $locationId,
    float $change,
    string $opType,
    string $reference,
    int $userId,
    string $notes = ''
): void {
    $db = getDB();
    // Ensure row exists
    $db->prepare("INSERT OR IGNORE INTO stock_levels (product_id, location_id, qty) VALUES (?,?,0)")
       ->execute([$productId, $locationId]);
    // Update qty
    $db->prepare("UPDATE stock_levels SET qty = qty + ? WHERE product_id=? AND location_id=?")
       ->execute([$change, $productId, $locationId]);
    $after = stockQty($productId, $locationId);
    // Ledger entry
    $db->prepare("INSERT INTO stock_ledger
        (product_id,location_id,operation_type,reference,qty_change,qty_after,notes,created_by)
        VALUES (?,?,?,?,?,?,?,?)")
       ->execute([$productId, $locationId, $opType, $reference, $change, $after, $notes, $userId]);
}

/** Dashboard KPI helpers */
function totalProducts(): int
{
    return (int)getDB()->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn();
}

function totalWarehouses(): int
{
    return (int)getDB()->query("SELECT COUNT(*) FROM warehouses WHERE active=1")->fetchColumn();
}

function totalCustomers(): int
{
    // Count active customers if table exists; fallback to 0 otherwise
    $db = getDB();
    try {
        return (int)$db->query("SELECT COUNT(*) FROM customers WHERE active=1")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function lowStockCount(): int
{
        $db = getDB();
        $threshold = (float)(getSetting('global_low_stock_threshold', '0') ?? '0');

        if ($threshold > 0) {
                $st = $db->prepare("
                        SELECT COUNT(DISTINCT p.id) FROM products p
                        LEFT JOIN (SELECT product_id, SUM(qty) t FROM stock_levels GROUP BY product_id) s
                            ON s.product_id = p.id
                        WHERE p.active=1 AND (s.t IS NULL OR s.t <= ?)
                ");
                $st->execute([$threshold]);
                return (int)$st->fetchColumn();
        }

        return (int)$db->query("
                SELECT COUNT(DISTINCT p.id) FROM products p
                LEFT JOIN (SELECT product_id, SUM(qty) t FROM stock_levels GROUP BY product_id) s
                    ON s.product_id = p.id
                WHERE p.active=1 AND p.reorder_qty > 0
                    AND (s.t IS NULL OR s.t <= p.reorder_qty)
        ")->fetchColumn();
}

function outOfStockCount(): int
{
    return (int)getDB()->query("
        SELECT COUNT(DISTINCT p.id) FROM products p
        LEFT JOIN (SELECT product_id, SUM(qty) t FROM stock_levels GROUP BY product_id) s
          ON s.product_id = p.id
        WHERE p.active=1 AND (s.t IS NULL OR s.t = 0)
    ")->fetchColumn();
}

function pendingCount(string $table): int
{
    $allowed = ['receipts','deliveries','transfers','adjustments'];
    if (!in_array($table, $allowed, true)) return 0;
    return (int)getDB()->query(
        "SELECT COUNT(*) FROM $table WHERE status NOT IN ('Done','Canceled')"
    )->fetchColumn();
}

/** All warehouses list */
function allWarehouses(): array
{
    return getDB()->query("SELECT * FROM warehouses WHERE active=1 ORDER BY name")->fetchAll();
}

/** Locations, optionally filtered by warehouse */
function allLocations(?int $warehouseId = null): array
{
    $db = getDB();
    if ($warehouseId) {
        $st = $db->prepare("SELECT l.*, w.name wh_name FROM locations l JOIN warehouses w ON w.id=l.warehouse_id WHERE l.warehouse_id=? AND l.active=1 ORDER BY l.name");
        $st->execute([$warehouseId]);
    } else {
        $st = $db->query("SELECT l.*, w.name wh_name FROM locations l JOIN warehouses w ON w.id=l.warehouse_id WHERE l.active=1 ORDER BY w.name, l.name");
    }
    return $st->fetchAll();
}

/** All active products */
function allProducts(): array
{
    return getDB()->query("
        SELECT p.*, c.name cat_name,
               COALESCE((SELECT SUM(qty) FROM stock_levels WHERE product_id=p.id),0) total_stock
        FROM products p LEFT JOIN product_categories c ON c.id=p.category_id
        WHERE p.active=1 ORDER BY p.name
    ")->fetchAll();
}

/** All categories; pass false to include inactive as well */
function allCategories(bool $activeOnly = true): array
{
    $db = getDB();
    if ($activeOnly) {
        return $db->query("SELECT * FROM product_categories WHERE COALESCE(active,1)=1 ORDER BY name")->fetchAll();
    }
    return $db->query("SELECT * FROM product_categories ORDER BY active DESC, name")->fetchAll();
}

/** Suppliers list; pass false to include inactive suppliers as well */
function allSuppliers(bool $activeOnly = true): array
{
    $db = getDB();
    if ($activeOnly) {
        return $db->query("SELECT * FROM suppliers WHERE active=1 ORDER BY name")->fetchAll();
    }
    return $db->query("SELECT * FROM suppliers ORDER BY active DESC, name")->fetchAll();
}

/** Customers list; pass false to include inactive customers as well */
function allCustomers(bool $activeOnly = true): array
{
    $db = getDB();
    if ($activeOnly) {
        return $db->query("SELECT * FROM customers WHERE active=1 ORDER BY name")->fetchAll();
    }
    return $db->query("SELECT * FROM customers ORDER BY active DESC, name")->fetchAll();
}

/** Simple key/value application settings */
function getSetting(string $key, ?string $default = null): ?string
{
    $db = getDB();
    $st = $db->prepare("SELECT setting_value FROM settings WHERE setting_key=?");
    $st->execute([$key]);
    $val = $st->fetchColumn();
    if ($val === false || $val === null) {
        return $default;
    }
    return (string)$val;
}

function setSetting(string $key, string $value): void
{
    $db = getDB();
    $st = $db->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES (?, ?)
        ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value
    ");
    $st->execute([$key, $value]);
}

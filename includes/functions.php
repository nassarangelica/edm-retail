<?php
/* ---------- output / routing helpers ---------- */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function money($n): string { return CURRENCY . number_format((float)$n, 2); }

function flash(?string $type = null, ?string $msg = null): array
{
    if ($type !== null) { $_SESSION['flash'][] = [$type, $msg]; return []; }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- CSRF protection ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
            http_response_code(419);
            exit('Security check failed (invalid CSRF token). Go back and refresh the page.');
        }
    }
}

/* ---------- authentication / role-based access control ---------- */
function user(): ?array { return $_SESSION['user'] ?? null; }
function has_role(string ...$roles): bool { return user() && in_array(user()['role'], $roles, true); }

function require_login(): void
{
    if (!user()) { flash('warning', 'Please log in first.'); redirect('login.php'); }
}
function require_role(string ...$roles): void
{
    require_login();
    if (!has_role(...$roles)) {
        http_response_code(403);
        $pageTitle = 'Access denied';
        require __DIR__ . '/header.php';
        echo '<div class="container py-5"><div class="alert alert-danger"><h4>403 - Access denied</h4>'
           . 'Your role (<b>' . e(user()['role']) . '</b>) is not allowed to open this page.</div></div>';
        require __DIR__ . '/footer.php';
        exit;
    }
}
function home_for_role(string $role): string
{
    return match ($role) {
        'admin', 'manager' => 'admin/index.php',
        'cashier'          => 'pos/index.php',
        'logistics'        => 'admin/shipments.php',
        default            => 'index.php',
    };
}

/* ---------- governance: audit trail ---------- */
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? 'cli'; }

function audit(string $action, ?string $table = null, $recordId = null, $old = null, $new = null, ?string $actor = null): void
{
    $u = user();
    $st = db()->prepare('INSERT INTO audit_logs(user_id,actor,action,table_name,record_id,old_value,new_value,ip_address) VALUES (?,?,?,?,?,?,?,?)');
    $st->execute([
        $u['id'] ?? null,
        $actor ?? ($u['email'] ?? 'anonymous'),
        $action, $table, $recordId,
        $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
        $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
        client_ip(),
    ]);
}

/* ---------- small UI helpers ---------- */
function stock_status(int $qty, int $reorder): array
{
    if ($qty <= 0)        return ['Out of stock', 'danger'];
    if ($qty <= $reorder) return ['Low stock', 'warning'];
    return ['In stock', 'success'];
}
function order_badge(string $s): string
{
    $c = ['processing' => 'secondary', 'shipped' => 'info', 'delivered' => 'success', 'completed' => 'success', 'cancelled' => 'danger'][$s] ?? 'secondary';
    return '<span class="badge text-bg-' . $c . '">' . e(ucfirst($s)) . '</span>';
}
function pay_label(string $m): string
{
    return ['cash' => 'Cash', 'cod' => 'Cash on delivery', 'gcash_sim' => 'E-wallet (simulated)', 'card_sim' => 'Card (simulated)'][$m] ?? $m;
}

/* ---------- text helpers (work even if the mbstring extension is disabled) ---------- */
function title_case(string $s): string
{
    if (function_exists('mb_convert_case')) return mb_convert_case(mb_strtolower($s, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    return ucwords(strtolower($s));
}
function text_len(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); }

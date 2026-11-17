<?php
/* Validation, formatting and upload helpers */

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }

function clean(string $key, string $method = 'POST'): string {
    $src = $method === 'GET' ? $_GET : $_POST;
    return trim((string)($src[$key] ?? ''));
}

function format_price($price, ?string $type = null): string {
    if ($price === null) return 'Contact for price';
    $n = 'KSh ' . number_format((float)$price, 0);
    return $type === 'service' ? 'From ' . $n : $n;
}

function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $a = mb_substr($parts[0] ?? '', 0, 1);
    $b = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($a . $b);
}

function listing_when(string $ts): string {
    return date('Y-m-d', strtotime($ts)) === date('Y-m-d') ? 'today' : date('j M', strtotime($ts));
}

function cat_icon(string $name): string {
    $map = ['Electronics' => 'laptop', 'Books & Notes' => 'book', 'Clothing' => 'handbag', 'Food' => 'egg-fried', 'Furniture & Hostel' => 'lamp',
            'Other Items' => 'box-seam', 'Tutoring' => 'mortarboard', 'Graphic & Web Design' => 'brush',
            'Computer Repair' => 'tools', 'Printing & Typing' => 'printer', 'Other Services' => 'stars'];
    return 'bi-' . ($map[$name] ?? 'grid');
}

/** Absolute URL (with scheme/host) for sharing links outside the site. */
function absolute_url(string $path = ''): string {
    if (SHARE_BASE_URL !== '') {
        return rtrim(SHARE_BASE_URL, '/') . '/' . ltrim($path, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . url($path);
}

function contact_link(string $method, string $value, string $listingTitle = '', string $listingUrl = ''): string {
    $digits = preg_replace('/\D+/', '', $value);
    $message = $listingUrl !== ''
        ? 'Hi, I\'m interested in "' . $listingTitle . '" on ' . APP_NAME . ".\n" . $listingUrl
        : '';
    if ($method === 'email') {
        if ($message === '') return 'mailto:' . $value;
        return 'mailto:' . $value
            . '?subject=' . rawurlencode(APP_NAME . ' listing: ' . $listingTitle)
            . '&body=' . rawurlencode($message);
    }
    if ($method === 'whatsapp') {
        if (strpos($digits, '0') === 0) $digits = '254' . substr($digits, 1);
        return 'https://wa.me/' . $digits . ($message !== '' ? '?text=' . rawurlencode($message) : '');
    }
    return 'tel:' . $digits;
}

/** Validate listing form input. Returns [data, errors]. */
function validate_listing(array $categories): array {
    $d = [
        'title'          => clean('title'),
        'description'    => clean('description'),
        'category_id'    => (int)clean('category_id'),
        'price'          => clean('price'),
        'item_condition' => clean('item_condition'),
        'contact_method' => clean('contact_method'),
        'contact_value'  => clean('contact_value'),
    ];
    $err = [];
    if (mb_strlen($d['title']) < 3 || mb_strlen($d['title']) > 120) $err[] = 'Title must be 3-120 characters.';
    if (mb_strlen($d['description']) < 10) $err[] = 'Description must be at least 10 characters.';
    if (mb_strlen($d['description']) > 3000) $err[] = 'Description is too long (max 3000).';

    $cat = $categories[$d['category_id']] ?? null;
    if (!$cat) $err[] = 'Choose a valid category.';

    if ($d['price'] === '') {
        $d['price'] = null;
        if ($cat && $cat['listing_type'] === 'product') $err[] = 'Products need a price.';
    } elseif (!is_numeric($d['price']) || $d['price'] < 0 || $d['price'] > 99999999) {
        $err[] = 'Price must be a positive number.';
    } else {
        $d['price'] = round((float)$d['price'], 2);
    }

    if ($cat && $cat['listing_type'] === 'product') {
        if (!in_array($d['item_condition'], ['new', 'like_new', 'good', 'fair'], true))
            $err[] = 'Choose the item condition.';
    } else {
        $d['item_condition'] = null;
    }

    if (!in_array($d['contact_method'], ['whatsapp', 'phone', 'email'], true)) {
        $err[] = 'Choose a contact method.';
    } elseif ($d['contact_method'] === 'email') {
        if (!filter_var($d['contact_value'], FILTER_VALIDATE_EMAIL)) $err[] = 'Enter a valid contact email.';
    } elseif (!preg_match('/^\+?[0-9 ]{9,15}$/', $d['contact_value'])) {
        $err[] = 'Enter a valid phone/WhatsApp number (digits, optional +).';
    }
    return [$d, $err];
}

/** Save uploaded images for a listing. Returns error list. */
function handle_uploads(int $listingId, int $alreadyHave = 0): array {
    $err = [];
    if (empty($_FILES['images']['name'][0])) return $err;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $dir = __DIR__ . '/../uploads/';
    $count = count($_FILES['images']['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($alreadyHave + $i >= MAX_IMAGES) { $err[] = 'Only ' . MAX_IMAGES . ' images per listing; extras ignored.'; break; }
        if ($_FILES['images']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $name = $_FILES['images']['name'][$i];
        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) { $err[] = "$name: upload failed."; continue; }
        if ($_FILES['images']['size'][$i] > MAX_IMAGE_BYTES) { $err[] = "$name: larger than 2MB."; continue; }
        $tmp = $_FILES['images']['tmp_name'][$i];
        $mime = $finfo->file($tmp);
        if (!isset($allowed[$mime]) || !@getimagesize($tmp)) { $err[] = "$name: only JPG, PNG or WebP images allowed."; continue; }
        $file = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($tmp, $dir . $file)) { $err[] = "$name: could not save file."; continue; }
        q('INSERT INTO listing_images (listing_id, image_path) VALUES (?, ?)', [$listingId, $file]);
    }
    return $err;
}

function delete_listing_files(int $listingId): void {
    foreach (q('SELECT image_path FROM listing_images WHERE listing_id = ?', [$listingId])->fetchAll() as $img) {
        $p = __DIR__ . '/../uploads/' . basename($img['image_path']);
        if (is_file($p)) @unlink($p);
    }
}

function categories_all(): array {
    $out = [];
    foreach (q('SELECT * FROM categories ORDER BY listing_type, name')->fetchAll() as $c) $out[$c['id']] = $c;
    return $out;
}

function thumb(?string $path): string {
    return $path ? url('uploads/' . rawurlencode($path)) : url('assets/images/placeholder.svg');
}

/* Stock mockups whose transparency checkerboard was flattened into the pixels.
   Hiding them keeps public cards clean without touching the original upload. */
const HIDDEN_IMAGE_HASHES = [
    'e2faec0a8ea32503787dd931d336408888f2fe59dc90a3b1ddd402f6a0b4212c' => true,
];

/** Photo URL safe to show publicly, or null when the image is unusable. */
function listing_photo(?string $path): ?string {
    if (!$path) return null;
    $file = __DIR__ . '/../uploads/' . basename($path);
    if (!is_file($file)) return null;
    if (isset(HIDDEN_IMAGE_HASHES[hash_file('sha256', $file)])) return null;
    return thumb($path);
}

function condition_label(?string $condition): ?string {
    return ['new' => 'Brand new', 'like_new' => 'Like new', 'good' => 'Good', 'fair' => 'Fair'][$condition ?? ''] ?? null;
}

function status_badge(string $s): string {
    $label = ['available' => 'Active', 'closed' => 'Unavailable', 'pending' => 'Pending Review', 'open' => 'Pending Review',
              'active' => 'Active', 'approved' => 'Approved', 'inspected' => 'Inspected',
              'suspended' => 'Suspended', 'dismissed' => 'Dismissed', 'disputed' => 'Disputed'][$s] ?? ucfirst($s);
    $cls = ['available' => 'ok', 'active' => 'ok', 'approved' => 'ok', 'resolved' => 'ok', 'inspected' => 'ok',
            'pending' => 'warn', 'open' => 'warn',
            'rejected' => 'bad', 'removed' => 'bad', 'deactivated' => 'bad', 'suspended' => 'bad', 'disputed' => 'bad'][$s] ?? 'muted';
    return '<span class="st st-' . $cls . '">' . e($label) . '</span>';
}

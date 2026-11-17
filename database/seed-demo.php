<?php
// Demo data for presentations. Run from the project folder:  php database/seed-demo.php
// Creates 3 demo students, 8 approved listings with generated images, and 1 pending listing.
// Demo logins: <email> / password123. Delete demo data before real deployment.
// Start over (removes demo users, their listings and images, then reseeds):  php database/seed-demo.php --reset
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Run this from the command line.'); }
require_once __DIR__ . '/../includes/db.php';

if (in_array('--reset', $argv ?? [], true)) {
    foreach (q("SELECT i.image_path FROM listing_images i JOIN listings l ON l.id = i.listing_id JOIN users u ON u.id = l.seller_id WHERE u.email LIKE '%@demo.campusfind.local'")->fetchAll() as $r) {
        $f = __DIR__ . '/../uploads/' . basename($r['image_path']); if (is_file($f)) @unlink($f);
    }
    q("DELETE FROM users WHERE email LIKE '%@demo.campusfind.local'");   // listings and images cascade
    echo "Old demo data removed.\n";
}
if (q("SELECT id FROM users WHERE email LIKE '%@demo.campusfind.local' LIMIT 1")->fetch()) { exit("Demo data already exists. Nothing to do.\n"); }

$hasGd = function_exists('imagecreatetruecolor') && function_exists('imagejpeg');
$uploads = __DIR__ . '/../uploads/';

/** Draw an abstract picture: gradient background + simple shape for the item kind. */
function make_image(string $kind, array $top, array $bot, string $path): void {
    $w = 800; $h = 600; $im = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $c = imagecolorallocate($im, (int)($top[0] + ($bot[0] - $top[0]) * $t), (int)($top[1] + ($bot[1] - $top[1]) * $t), (int)($top[2] + ($bot[2] - $top[2]) * $t));
        imageline($im, 0, $y, $w, $y, $c);
    }
    $forest = imagecolorallocate($im, 23, 61, 44); $lime = imagecolorallocate($im, 201, 242, 122);
    $white = imagecolorallocate($im, 255, 255, 255); $soft = imagecolorallocate($im, 238, 242, 234);
    imagefilledellipse($im, 640, 130, 260, 260, imagecolorallocatealpha($im, 255, 255, 255, 100));
    switch ($kind) {
        case 'laptop':
            imagefilledrectangle($im, 220, 160, 580, 380, $forest); imagefilledrectangle($im, 236, 176, 564, 364, $soft);
            imagefilledrectangle($im, 260, 200, 400, 240, $lime); imagefilledrectangle($im, 260, 260, 520, 272, $white); imagefilledrectangle($im, 260, 290, 480, 302, $white);
            imagefilledrectangle($im, 170, 380, 630, 408, $forest); break;
        case 'book':
            imagefilledrectangle($im, 230, 170, 400, 420, $forest); imagefilledrectangle($im, 400, 170, 570, 420, $lime);
            imagefilledrectangle($im, 250, 200, 380, 214, $white); imagefilledrectangle($im, 250, 230, 360, 242, $white);
            imagefilledrectangle($im, 420, 200, 550, 214, $forest); imagefilledrectangle($im, 420, 230, 530, 242, $forest); break;
        case 'person':
            imagefilledellipse($im, 400, 230, 130, 130, $forest); imagefilledellipse($im, 400, 420, 300, 240, $forest);
            imagefilledellipse($im, 400, 230, 70, 70, $lime); break;
        case 'tools':
            imagefilledrectangle($im, 190, 340, 610, 392, $forest); imagefilledrectangle($im, 260, 220, 540, 340, $soft);
            imagefilledrectangle($im, 290, 250, 420, 270, $forest); imagefilledrectangle($im, 290, 290, 380, 308, $lime); break;
        default: // box
            imagefilledrectangle($im, 250, 220, 550, 420, $forest); imagefilledrectangle($im, 250, 220, 550, 270, $lime);
            imagefilledrectangle($im, 380, 220, 420, 420, $white);
    }
    imagejpeg($im, $path, 88); imagedestroy($im);
}

$pw = password_hash('password123', PASSWORD_DEFAULT);
$users = [['Amina Wanjiku', 'amina@demo.campusfind.local', '0711000001'], ['Brian Otieno', 'brian@demo.campusfind.local', '0711000002'], ['Cynthia Mwangi', 'cynthia@demo.campusfind.local', '0711000003']];
$uid = [];
foreach ($users as $i => [$n, $em, $ph]) {
    q('INSERT INTO users (full_name, email, password_hash, phone, email_verified_at, created_at) VALUES (?,?,?,?, NOW(), NOW() - INTERVAL ? DAY)', [$n, $em, $pw, $ph, 40 - $i * 9]);
    $uid[$i] = (int)db()->lastInsertId();
}
$cat = []; foreach (q('SELECT id, name FROM categories')->fetchAll() as $c) $cat[$c['name']] = (int)$c['id'];

// seller, category, title, description, price, condition, method, contact, daysAgo, status, image kind, [top rgb], [bottom rgb]
$rows = [
 [0, 'Electronics', 'HP EliteBook Laptop', "Core i5, 8GB RAM, 256GB SSD. Battery lasts about 4 hours. Comes with charger and a padded sleeve.", 28000, 'good', 'whatsapp', '0711000001', 0, 'approved', 'laptop', [201,242,122], [238,242,234]],
 [0, 'Graphic & Web Design', 'Graphic Design Services', "Logos, posters, event flyers and social media graphics. Delivered in PNG and PDF, usually within 2 days.", 500, null, 'email', 'amina@demo.campusfind.local', 1, 'approved', 'person', [238,242,234], [201,242,122]],
 [1, 'Electronics', 'Casio fx-991 Scientific Calculator', "Barely used and works perfectly. Great for engineering and science units.", 1500, 'like_new', 'whatsapp', '0711000002', 2, 'approved', 'box', [235,240,230], [201,242,122]],
 [1, 'Books & Notes', 'Engineering Mathematics Textbook', "Stroud's style textbook with some pencil notes in the margins. Cover slightly worn.", 900, 'fair', 'phone', '0711000002', 3, 'approved', 'book', [201,242,122], [247,248,245]],
 [2, 'Tutoring', 'Calculus and Linear Algebra Tutoring', "Year 3 student offering weekly revision sessions, one-on-one or small groups, on campus.", 500, null, 'whatsapp', '0711000003', 4, 'approved', 'person', [247,248,245], [201,242,122]],
 [2, 'Computer Repair', 'Laptop Repair and Software Install', "Windows installs, virus removal, SSD upgrades and screen replacements. Price depends on the job.", null, null, 'whatsapp', '0711000003', 5, 'approved', 'tools', [238,242,234], [201,242,122]],
 [0, 'Furniture & Hostel', 'Study Desk Lamp', "Adjustable LED lamp, three brightness levels, USB powered. Perfect for hostel desks.", 700, 'good', 'phone', '0711000001', 6, 'approved', 'box', [201,242,122], [235,240,230]],
 [1, 'Printing & Typing', 'Printing and Binding Service', "Black and white or colour printing, spiral binding and assignment typing. Quick turnaround.", 10, null, 'whatsapp', '0711000002', 8, 'approved', 'tools', [247,248,245], [238,242,234]],
 [2, 'Other Items', 'Mountain Bike (awaiting review)', "Good condition 21-speed bike, recently serviced. Helmet included.", 9500, 'good', 'whatsapp', '0711000003', 0, 'pending', 'box', [238,242,234], [201,242,122]],
];
$n = 0;
foreach ($rows as [$s, $c, $title, $desc, $price, $cond, $m, $cv, $days, $mod, $kind, $top, $bot]) {
    q('INSERT INTO listings (seller_id, category_id, title, description, price, item_condition, contact_method, contact_value, moderation_status, created_at) VALUES (?,?,?,?,?,?,?,?,?, NOW() - INTERVAL ? DAY)',
      [$uid[$s], $cat[$c], $title, $desc, $price, $cond, $m, $cv, $mod, $days]);
    $lid = (int)db()->lastInsertId(); $n++;
    if ($hasGd) {
        $file = bin2hex(random_bytes(12)) . '.jpg';
        make_image($kind, $top, $bot, $uploads . $file);
        q('INSERT INTO listing_images (listing_id, image_path) VALUES (?, ?)', [$lid, $file]);
    }
}
echo "Done: 3 demo students, $n listings" . ($hasGd ? ' with generated images' : ' (no images: enable extension=gd in C:\xampp\php\php.ini, then run: php database/seed-demo.php --reset)') . ".\n";
echo "Demo logins: amina@demo.campusfind.local / brian@demo.campusfind.local / cynthia@demo.campusfind.local, password: password123\n";

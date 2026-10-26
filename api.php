<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/* ---------- SETTINGS (XAMPP defaults) ---------- */
$host = '127.0.0.1';
$port = 3306;
$db   = 'moneybior_hardware';
$user = 'root';
$pass = '';            // XAMPP's MySQL has no root password by default.
const ALLOW_PUBLIC_REGISTRATION = true; // true = the login-page user form always works.
                                        // Set to false to require a logged-in user after the first account.

function out(array $data, int $status = 200): never {
  http_response_code($status); echo json_encode($data); exit;
}
function input(): array {
  $raw = file_get_contents('php://input'); $d = json_decode($raw ?: '{}', true);
  return is_array($d) ? $d : [];
}
function needLogin(): void {
  if (empty($_SESSION['account'])) out(['success'=>false,'message'=>'Please log in first.'], 401);
}
function val(array $d, string $key): string { return trim((string)($d[$key] ?? '')); }
function need(array $values): void {
  foreach ($values as $v) if ($v === '') out(['success'=>false,'message'=>'Please fill in all required fields.'], 422);
}

/* ---------- DATABASE (created automatically if missing) ---------- */
$schema = [
 "CREATE TABLE IF NOT EXISTS accounts (
   id INT AUTO_INCREMENT PRIMARY KEY,
   full_name VARCHAR(120) NOT NULL,
   username VARCHAR(60) NOT NULL UNIQUE,
   password_hash VARCHAR(255) NOT NULL,
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
 "CREATE TABLE IF NOT EXISTS products (
   id INT AUTO_INCREMENT PRIMARY KEY,
   product_id VARCHAR(60) NOT NULL UNIQUE,
   name VARCHAR(120) NOT NULL,
   size VARCHAR(80) NOT NULL,
   quantity INT NOT NULL,
   buying_price DECIMAL(12,2) NOT NULL,
   selling_price DECIMAL(12,2) NOT NULL,
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
 "CREATE TABLE IF NOT EXISTS customers (
   id INT AUTO_INCREMENT PRIMARY KEY,
   customer_id VARCHAR(60) NOT NULL UNIQUE,
   name VARCHAR(120) NOT NULL,
   gender VARCHAR(20) NOT NULL,
   phone VARCHAR(40) NOT NULL,
   address VARCHAR(255) NOT NULL,
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
 "CREATE TABLE IF NOT EXISTS suppliers (
   id INT AUTO_INCREMENT PRIMARY KEY,
   supplier_id VARCHAR(60) NOT NULL UNIQUE,
   name VARCHAR(120) NOT NULL,
   contact_person VARCHAR(120) NOT NULL,
   phone VARCHAR(40) NOT NULL,
   email VARCHAR(120) NOT NULL DEFAULT '',
   address VARCHAR(255) NOT NULL,
   products_supplied VARCHAR(255) NOT NULL,
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
 "CREATE TABLE IF NOT EXISTS system_users (
   id INT AUTO_INCREMENT PRIMARY KEY,
   user_id VARCHAR(60) NOT NULL UNIQUE,
   full_name VARCHAR(120) NOT NULL,
   username VARCHAR(60) NOT NULL UNIQUE,
   role VARCHAR(40) NOT NULL,
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
 "CREATE TABLE IF NOT EXISTS product_requests (
   id INT AUTO_INCREMENT PRIMARY KEY,
   customer_id VARCHAR(60) NOT NULL,
   customer_name VARCHAR(120) NOT NULL,
   product_name VARCHAR(120) NOT NULL,
   quantity INT NOT NULL,
   requested_by VARCHAR(120) NOT NULL,
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
 "CREATE TABLE IF NOT EXISTS sales (
   id INT AUTO_INCREMENT PRIMARY KEY,
   transaction_id VARCHAR(60) NOT NULL UNIQUE,
   customer VARCHAR(120) NOT NULL,
   product VARCHAR(120) NOT NULL,
   quantity INT NOT NULL,
   total_amount DECIMAL(12,2) NOT NULL,
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)"
];
$opts = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false
];
try {
  $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, $opts);
  $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $pdo->exec("USE `$db`");
  foreach ($schema as $sql) $pdo->exec($sql);
} catch (Throwable $e) {
  out(['success'=>false,'message'=>'Cannot connect to MySQL. Start MySQL in the XAMPP Control Panel and check the settings at the top of api.php.'], 500);
}

$d = input(); $action = val($d, 'action');

try {
  switch ($action) {

    case 'register': {   // User form on the login page: creates the login AND the user record
      $userId = val($d,'userId'); $full = val($d,'fullName'); $username = val($d,'username');
      $role = val($d,'role'); $password = (string)($d['password'] ?? '');
      need([$userId,$full,$username,$role]);
      if (strlen($password) < 4) out(['success'=>false,'message'=>'Password must be at least 4 characters.'], 422);
      $count = (int)$pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn();
      if ($count > 0 && !ALLOW_PUBLIC_REGISTRATION && empty($_SESSION['account']))
        out(['success'=>false,'message'=>'Log in before creating another account.'], 403);
      $q = $pdo->prepare('SELECT 1 FROM accounts WHERE username=? UNION SELECT 1 FROM system_users WHERE username=? OR user_id=?');
      $q->execute([$username,$username,$userId]);
      if ($q->fetch()) out(['success'=>false,'message'=>'That User ID or username already exists.'], 409);
      $pdo->beginTransaction();
      $pdo->prepare('INSERT INTO accounts(full_name,username,password_hash) VALUES(?,?,?)')
          ->execute([$full,$username,password_hash($password,PASSWORD_DEFAULT)]);
      $pdo->prepare('INSERT INTO system_users(user_id,full_name,username,role) VALUES(?,?,?,?)')
          ->execute([$userId,$full,$username,$role]);
      $pdo->commit();
      out(['success'=>true]);
    }

    case 'login': {
      $q = $pdo->prepare('SELECT id,full_name,username,password_hash FROM accounts WHERE username=?');
      $q->execute([val($d,'username')]); $a = $q->fetch();
      if (!$a || !password_verify((string)($d['password'] ?? ''), $a['password_hash']))
        out(['success'=>false,'message'=>'Incorrect username or password.'], 401);
      session_regenerate_id(true);
      $_SESSION['account'] = ['id'=>$a['id'],'fullName'=>$a['full_name'],'username'=>$a['username']];
      out(['success'=>true,'user'=>$_SESSION['account']]);
    }

    case 'me':   // lets the page stay logged in after a refresh
      out(['success'=>true,'user'=>$_SESSION['account'] ?? null]);

    case 'reset_password': {   // works from the login page: username + User ID must match
      $username = val($d,'username'); $userId = val($d,'userId'); $password = (string)($d['password'] ?? '');
      need([$username,$userId]);
      if (strlen($password) < 4) out(['success'=>false,'message'=>'Password must be at least 4 characters.'], 422);
      $q = $pdo->prepare('SELECT 1 FROM system_users s JOIN accounts a ON a.username=s.username WHERE s.username=? AND s.user_id=?');
      $q->execute([$username,$userId]);
      if (!$q->fetch()) out(['success'=>false,'message'=>'Username and User ID do not match any account.'], 403);
      $pdo->prepare('UPDATE accounts SET password_hash=? WHERE username=?')
          ->execute([password_hash($password,PASSWORD_DEFAULT),$username]);
      out(['success'=>true]);
    }

    case 'logout':
      $_SESSION = []; session_destroy(); out(['success'=>true]);

    default:
      needLogin();
  }

  if ($action === 'create_product') {
    need([val($d,'productId'),val($d,'name'),val($d,'size')]);
    if ((int)($d['quantity']??0) < 0 || (float)($d['buyingPrice']??0) < 0 || (float)($d['sellingPrice']??0) < 0)
      out(['success'=>false,'message'=>'Quantity and prices cannot be negative.'], 422);
    $pdo->prepare('INSERT INTO products(product_id,name,size,quantity,buying_price,selling_price) VALUES(?,?,?,?,?,?)')
        ->execute([val($d,'productId'),val($d,'name'),val($d,'size'),(int)($d['quantity']??0),(float)($d['buyingPrice']??0),(float)($d['sellingPrice']??0)]);
    out(['success'=>true]);
  }
  if ($action === 'create_customer') {
    need([val($d,'customerId'),val($d,'name'),val($d,'gender'),val($d,'phone'),val($d,'address')]);
    $pdo->prepare('INSERT INTO customers(customer_id,name,gender,phone,address) VALUES(?,?,?,?,?)')
        ->execute([val($d,'customerId'),val($d,'name'),val($d,'gender'),val($d,'phone'),val($d,'address')]);
    out(['success'=>true]);
  }
  if ($action === 'create_supplier') {
    need([val($d,'supplierId'),val($d,'name'),val($d,'contactPerson'),val($d,'phone'),val($d,'address'),val($d,'productsSupplied')]);
    $pdo->prepare('INSERT INTO suppliers(supplier_id,name,contact_person,phone,email,address,products_supplied) VALUES(?,?,?,?,?,?,?)')
        ->execute([val($d,'supplierId'),val($d,'name'),val($d,'contactPerson'),val($d,'phone'),val($d,'email'),val($d,'address'),val($d,'productsSupplied')]);
    out(['success'=>true]);
  }
  if ($action === 'create_user') {
    need([val($d,'userId'),val($d,'fullName'),val($d,'username'),val($d,'role')]);
    $pdo->prepare('INSERT INTO system_users(user_id,full_name,username,role) VALUES(?,?,?,?)')
        ->execute([val($d,'userId'),val($d,'fullName'),val($d,'username'),val($d,'role')]);
    out(['success'=>true]);
  }
  if ($action === 'create_request') {
    need([val($d,'customerId'),val($d,'customerName'),val($d,'productName'),val($d,'requestedBy')]);
    if ((int)($d['quantity']??0) < 1) out(['success'=>false,'message'=>'Quantity must be at least 1.'], 422);
    $pdo->prepare('INSERT INTO product_requests(customer_id,customer_name,product_name,quantity,requested_by) VALUES(?,?,?,?,?)')
        ->execute([val($d,'customerId'),val($d,'customerName'),val($d,'productName'),(int)($d['quantity']??0),val($d,'requestedBy')]);
    out(['success'=>true]);
  }
  if ($action === 'create_sale') {
    need([val($d,'transactionId'),val($d,'customer'),val($d,'product')]);
    $qty = (int)($d['quantity'] ?? 0); $amt = (float)($d['totalAmount'] ?? 0);
    if ($qty < 1 || $amt < 0) out(['success'=>false,'message'=>'Enter a valid quantity and total amount.'], 422);
    $prod = val($d,'product');
    $pdo->beginTransaction();
    // If the product exists in stock, check and reduce its quantity automatically.
    $q = $pdo->prepare('SELECT id,quantity FROM products WHERE LOWER(name)=LOWER(?) OR LOWER(product_id)=LOWER(?) LIMIT 1 FOR UPDATE');
    $q->execute([$prod,$prod]); $m = $q->fetch();
    if (!empty($d['skipStock'])) $m = false;   // used when syncing sales recorded offline (stock already reduced)
    if ($m && (int)$m['quantity'] < $qty) {
      $pdo->rollBack();
      out(['success'=>false,'message'=>'Not enough stock. Available quantity: '.$m['quantity']], 422);
    }
    $pdo->prepare('INSERT INTO sales(transaction_id,customer,product,quantity,total_amount) VALUES(?,?,?,?,?)')
        ->execute([val($d,'transactionId'),val($d,'customer'),$prod,$qty,$amt]);
    if ($m) $pdo->prepare('UPDATE products SET quantity=quantity-? WHERE id=?')->execute([$qty,$m['id']]);
    $pdo->commit();
    out(['success'=>true]);
  }
  if ($action === 'delete_record') {
    $allowed = ['products'=>'products','customers'=>'customers','suppliers'=>'suppliers','requests'=>'product_requests','sales'=>'sales'];
    $t = val($d,'table'); $id = (int)($d['id'] ?? 0);
    if (!isset($allowed[$t]) || $id < 1) out(['success'=>false,'message'=>'Invalid delete request.'], 422);
    $pdo->prepare("DELETE FROM `{$allowed[$t]}` WHERE id=?")->execute([$id]);
    out(['success'=>true]);
  }
  if ($action === 'list_all') {
    $map = ['products'=>'products','customers'=>'customers','suppliers'=>'suppliers',
            'users'=>'system_users','requests'=>'product_requests','sales'=>'sales'];
    $all = [];
    foreach ($map as $key => $table) $all[$key] = $pdo->query("SELECT * FROM `$table` ORDER BY id DESC")->fetchAll();
    out(['success'=>true] + $all);
  }
  out(['success'=>false,'message'=>'Unknown action.'], 400);

} catch (PDOException $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  if ((string)$e->getCode() === '23000') out(['success'=>false,'message'=>'This ID or username already exists. Use a unique value.'], 409);
  out(['success'=>false,'message'=>'Database operation failed. Check the fields and try again.'], 500);
} catch (Throwable $e) {
  out(['success'=>false,'message'=>'Unexpected server error.'], 500);
}

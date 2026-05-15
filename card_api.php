<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$host = 'sql.freedb.tech';
$dbname = 'freedb_iKE7EpA2';
$user = 'u_Ztadqh';
$pass = 'ykCQurm6Uh7s';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
} catch (PDOException $e) {
    die(json_encode(['error' => 'DB connection failed']));
}

$action = $_GET['action'] ?? '';
$cardSerial = $_GET['card_serial'] ?? '';
$amount = $_GET['amount'] ?? null;

if ($action === 'get_balance' && $cardSerial) {
    $stmt = $pdo->prepare("SELECT balance FROM card_balances WHERE card_serial = ?");
    $stmt->execute([$cardSerial]);
    $balance = $stmt->fetchColumn();
    if ($balance === false) $balance = 100000;
    echo json_encode(['balance' => (int)$balance]);
    exit;
}

if ($action === 'deduct' && $cardSerial && $amount !== null) {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT balance FROM card_balances WHERE card_serial = ? FOR UPDATE");
    $stmt->execute([$cardSerial]);
    $current = $stmt->fetchColumn();
    if ($current === false) $current = 100000;
    $newBalance = $current - (int)$amount;
    if ($newBalance < 0) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Недостаточно средств']);
        exit;
    }
    $upsert = $pdo->prepare("INSERT INTO card_balances (card_serial, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = ?");
    $upsert->execute([$cardSerial, $newBalance, $newBalance]);
    $pdo->commit();
    echo json_encode(['success' => true, 'new_balance' => $newBalance]);
    exit;
}

if ($action === 'add_funds' && $cardSerial && $amount !== null) {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT balance FROM card_balances WHERE card_serial = ? FOR UPDATE");
    $stmt->execute([$cardSerial]);
    $current = $stmt->fetchColumn();
    if ($current === false) $current = 100000;
    $newBalance = $current + (int)$amount;
    $upsert = $pdo->prepare("INSERT INTO card_balances (card_serial, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = ?");
    $upsert->execute([$cardSerial, $newBalance, $newBalance]);
    $pdo->commit();
    echo json_encode(['success' => true, 'new_balance' => $newBalance]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);
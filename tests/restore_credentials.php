<?php

declare(strict_types=1);

define('FM_ROOT', dirname(__DIR__));
spl_autoload_register(static function (string $class): void {
    $prefix = 'FilamentManager\\';
    if (str_starts_with($class, $prefix)) {
        require FM_ROOT . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

final class RestoreTestPDO extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace('UTC_TIMESTAMP(6)', 'CURRENT_TIMESTAMP', $query), $options);
    }
}

function restoreCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function b64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function cborHead(int $major, int $length): string
{
    if ($length < 24) {
        return chr(($major << 5) | $length);
    }
    if ($length <= 0xff) {
        return chr(($major << 5) | 24) . chr($length);
    }
    if ($length <= 0xffff) {
        return chr(($major << 5) | 25) . pack('n', $length);
    }
    return chr(($major << 5) | 26) . pack('N', $length);
}

function cborInteger(int $value): string
{
    return $value >= 0 ? cborHead(0, $value) : cborHead(1, -1 - $value);
}

function cborBytes(string $value): string
{
    return cborHead(2, strlen($value)) . $value;
}

function cborText(string $value): string
{
    return cborHead(3, strlen($value)) . $value;
}

/** @param list<array{0:string,1:string}> $entries */
function cborMap(array $entries): string
{
    $encoded = cborHead(5, count($entries));
    foreach ($entries as [$key, $value]) {
        $encoded .= $key . $value;
    }
    return $encoded;
}

function clientData(string $type, string $challenge, string $origin): string
{
    return json_encode([
        'type' => $type,
        'challenge' => $challenge,
        'origin' => $origin,
        'crossOrigin' => false,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

$pdo = new RestoreTestPDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec(
    'CREATE TABLE users (
        id TEXT PRIMARY KEY,
        username TEXT NOT NULL,
        display_name TEXT NOT NULL,
        is_active INTEGER NOT NULL,
        deleted_at TEXT NULL
    );
    CREATE TABLE restore_credentials (
        id TEXT PRIMARY KEY,
        user_id TEXT NOT NULL,
        credential_id TEXT NOT NULL,
        credential_id_hash TEXT NOT NULL UNIQUE,
        public_key_pem TEXT NOT NULL,
        origin TEXT NOT NULL,
        sign_count INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE webauthn_challenges (
        id TEXT PRIMARY KEY,
        user_id TEXT NOT NULL,
        purpose TEXT NOT NULL,
        challenge_hash TEXT NOT NULL UNIQUE,
        expires_at TEXT NOT NULL,
        used_at TEXT NULL
    );'
);

$db = new FilamentManager\Core\Database($pdo);
$app = new FilamentManager\Core\App([]);
(new ReflectionProperty($app, 'database'))->setValue($app, $db);
$service = new FilamentManager\Services\RestoreCredentialService($app);
$user = [
    'id' => FilamentManager\Core\Uuid::v4(),
    'username' => 'restore-test',
    'display_name' => 'Restore Test',
];
$db->execute(
    'INSERT INTO users(id,username,display_name,is_active,deleted_at) VALUES(?,?,?,?,NULL)',
    [$user['id'], $user['username'], $user['display_name'], 1]
);

$privateKey = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name' => 'prime256v1',
]);
if ($privateKey === false) {
    throw new RuntimeException('Cannot create the test P-256 key.');
}
$keyDetails = openssl_pkey_get_details($privateKey);
$x = $keyDetails['ec']['x'] ?? null;
$y = $keyDetails['ec']['y'] ?? null;
restoreCheck(is_string($x) && strlen($x) === 32, 'Missing P-256 X coordinate.');
restoreCheck(is_string($y) && strlen($y) === 32, 'Missing P-256 Y coordinate.');

$credentialId = random_bytes(32);
$credentialIdEncoded = b64url($credentialId);
$origin = 'android:apk-key-hash:' . b64url(hex2bin(
    'c7d715b34f3bae6eb2ab94fddbef4ab06be0c8d41041c4538af5e23152ed1c2c'
));
$coseKey = cborMap([
    [cborInteger(1), cborInteger(2)],
    [cborInteger(3), cborInteger(-7)],
    [cborInteger(-1), cborInteger(1)],
    [cborInteger(-2), cborBytes($x)],
    [cborInteger(-3), cborBytes($y)],
]);

$registrationResponse = $service->registrationOptions($user);
restoreCheck($registrationResponse['userId'] === $user['id'], 'Registration did not return the user ID.');
$registrationOptions = json_decode(
    $registrationResponse['requestJson'],
    true,
    flags: JSON_THROW_ON_ERROR
);
restoreCheck($registrationOptions['rp']['id'] === 'pihrt.com', 'Unexpected relying-party ID.');
$registrationClient = clientData(
    'webauthn.create',
    (string) $registrationOptions['challenge'],
    $origin
);
$registrationAuthData = hash('sha256', 'pihrt.com', true)
    . chr(0x41)
    . pack('N', 0)
    . str_repeat("\0", 16)
    . pack('n', strlen($credentialId))
    . $credentialId
    . $coseKey;
$attestationObject = cborMap([
    [cborText('fmt'), cborText('none')],
    [cborText('attStmt'), cborMap([])],
    [cborText('authData'), cborBytes($registrationAuthData)],
]);
$service->completeRegistration($user, [
    'id' => $credentialIdEncoded,
    'rawId' => $credentialIdEncoded,
    'type' => 'public-key',
    'response' => [
        'clientDataJSON' => b64url($registrationClient),
        'attestationObject' => b64url($attestationObject),
    ],
]);
restoreCheck(
    (int) $db->fetch('SELECT COUNT(*) AS count FROM restore_credentials')['count'] === 1,
    'Restore credential was not stored.'
);

$authenticationOptions = json_decode(
    $service->authenticationOptions($user)['requestJson'],
    true,
    flags: JSON_THROW_ON_ERROR
);
restoreCheck(
    $authenticationOptions['allowCredentials'][0]['id'] === $credentialIdEncoded,
    'Authentication did not offer the registered credential.'
);
$authenticationClient = clientData(
    'webauthn.get',
    (string) $authenticationOptions['challenge'],
    $origin
);
$authenticationData = hash('sha256', 'pihrt.com', true) . chr(0x01) . pack('N', 1);
$signedData = $authenticationData . hash('sha256', $authenticationClient, true);
$signature = '';
restoreCheck(
    openssl_sign($signedData, $signature, $privateKey, OPENSSL_ALGO_SHA256),
    'Cannot sign the restore assertion.'
);
$assertion = [
    'id' => $credentialIdEncoded,
    'rawId' => $credentialIdEncoded,
    'type' => 'public-key',
    'response' => [
        'clientDataJSON' => b64url($authenticationClient),
        'authenticatorData' => b64url($authenticationData),
        'signature' => b64url($signature),
    ],
];
$authenticated = $service->authenticate((string) $user['id'], $assertion);
restoreCheck($authenticated['id'] === $user['id'], 'Restore assertion authenticated the wrong user.');
restoreCheck(
    (int) $db->fetch('SELECT sign_count FROM restore_credentials')['sign_count'] === 1,
    'Restore signature counter was not updated.'
);

try {
    $service->authenticate((string) $user['id'], $assertion);
    throw new RuntimeException('A restore challenge replay was accepted.');
} catch (FilamentManager\Core\HttpException $error) {
    restoreCheck($error->status() === 401, 'Challenge replay did not return HTTP 401.');
}

$invalidOriginOptions = json_decode(
    $service->registrationOptions($user)['requestJson'],
    true,
    flags: JSON_THROW_ON_ERROR
);
$invalidOriginClient = clientData(
    'webauthn.create',
    (string) $invalidOriginOptions['challenge'],
    'android:apk-key-hash:untrusted-signing-certificate'
);
try {
    $service->completeRegistration($user, [
        'id' => $credentialIdEncoded,
        'rawId' => $credentialIdEncoded,
        'type' => 'public-key',
        'response' => [
            'clientDataJSON' => b64url($invalidOriginClient),
            'attestationObject' => b64url($attestationObject),
        ],
    ]);
    throw new RuntimeException('An untrusted Android signing certificate was accepted.');
} catch (FilamentManager\Core\HttpException $error) {
    restoreCheck($error->status() === 422, 'Invalid Android origin did not return HTTP 422.');
}

echo "Restore credential regression tests passed.\n";

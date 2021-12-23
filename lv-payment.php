<?php
/**
 * Example Payment Code für DAV Landesverbände
 *
 * digitalROCK schickt für alle Athleten des LV, die eine nationale Lizenz beantragen,
 * einen POST Request mit Content-Type "x-www-form-urlencoded" und folgenden Parametern:
 * - PerId: digitalROCK interne ID für den Athleten
 * - firstname
 * - lastname
 * - year: Jahr des Lizenzbeginns
 *
 * Der LV antwortet / redirected nach erfolgter Bezahlung mit einem JWT (JSON Web Token), das mit dem registrierten
 * Key des LV verifiziert werden kann, und der obigen PerId as sub(ject) and license-year as claim:
 *
 * GET /egroupware/ranking/athlete.php&PerId=123&action=request&jwt=XXX
 * Host: www.digitalrock.de
 */

/*if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
	http_response_code(400);
	die("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
}*/
if (empty($_REQUEST['PerId']) || !is_numeric($_REQUEST['PerId']) ||
	empty($_REQUEST['year']) || !in_array($_REQUEST['year'], [date('Y'), date('Y')+1]) ||
	empty($_REQUEST['firstname']) || empty($_REQUEST['lastname']))
{
	http_response_code(400);
	die("Invalid or missing request parameters: ".json_encode($_REQUEST));
}

// composer require lcobucci/jwt:3.4
require __DIR__.'/../vendor/autoload.php';
// require PHP 8 fixed class before lcobucci/jwt:3.4 loads it
if (version_compare(PHP_VERSION, '8.0', '>='))
{
	require_once __DIR__.'/../openid/src/OpenSSL.php';
}

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

$config = Configuration::forSymmetricSigner(
	new Sha256(),
	// replace the value below with a key you get from digitalROCK
	InMemory::base64Encoded('mBC5v1sOKVvbdEitdSBenu59nfNfhwkedkJVNabosTw=')
);
$now   = new DateTimeImmutable();
$token = $config->builder()
	// Configures the issuer (iss claim)
	->issuedBy($_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'])
	// Configures the audience (aud claim)
	->permittedFor('https://digitalrock.de')
	// Configures the time that the token was issue (iat claim)
	->issuedAt($now)
	// Configures the time that the token can be used (nbf claim)
	->canOnlyBeUsedAfter($now)
	// Configures the expiration time of the token (exp claim)
	->expiresAt($now->modify('+1 hour'))
	// Configures claims
	->relatedTo($_REQUEST['PerId'])
	->withClaim('license-year', $_REQUEST['year'])
	// Builds a new token
	->getToken($config->signer(), $config->signingKey());

$jwt = $token->toString();

echo "<h1>".htmlspecialchars($_REQUEST['year'])." License Payment for ".htmlspecialchars($_REQUEST['firstname']).' '.htmlspecialchars($_REQUEST['lastname'])." (".htmlspecialchars($_REQUEST['PerId']).")</h1>\n";
echo "<form method='GET' action='".($_SERVER['HTTP_REFFERER'] ?? 'https://boulder.egroupware.org/egroupware/ranking/athlete.php')."'>\n";
echo "<input type='hidden' name='PerId' value='$_REQUEST[PerId]'/>\n";
echo "<input type='hidden' name='action' value='apply'/>\n";
echo "<input type='hidden' name='jwt' value='".htmlspecialchars($jwt)."'/>\n";
echo "<input type='submit' name='payed' value='Confirm Payment'/>\n";
echo "<input type='submit' name='jwt' value='Cancel'/>\n";
echo "</form>\n";
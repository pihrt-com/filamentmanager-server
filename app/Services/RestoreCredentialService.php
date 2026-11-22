<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use FilamentManager\Core\App;
use FilamentManager\Core\HttpException;
use FilamentManager\Core\Uuid;
use RuntimeException;

final class RestoreCredentialService
{
    private const RP_ID='pihrt.com';
    private const ANDROID_APP_ORIGINS=[
        'android:apk-key-hash:x9cVs087rm6yq5T92-9KsGvgyNQQQcRTivXiMVLtHCw',
    ];
    public function __construct(private readonly App $app){}
    public function registrationOptions(array $user):array
    {
        $challenge=$this->challenge((string)$user['id'],'restore_register');$exclude=[];
        foreach($this->app->db()->fetchAll('SELECT credential_id FROM restore_credentials WHERE user_id=?',[$user['id']]) as $row)$exclude[]=['type'=>'public-key','id'=>$row['credential_id']];
        $options=['rp'=>['id'=>self::RP_ID,'name'=>'FilamentManager'],'user'=>['id'=>$this->b64((string)$user['id']),'name'=>$user['username'],'displayName'=>$user['display_name']],'challenge'=>$challenge,'pubKeyCredParams'=>[['type'=>'public-key','alg'=>-7]],'timeout'=>300000,'attestation'=>'none','authenticatorSelection'=>['residentKey'=>'required','requireResidentKey'=>true,'userVerification'=>'preferred'],'excludeCredentials'=>$exclude];
        return ['requestJson'=>json_encode($options,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'userId'=>(string)$user['id']];
    }
    public function completeRegistration(array $user,array $credential):void
    {
        $response=$this->map($credential['response']??null);$clientRaw=$this->decode((string)($response['clientDataJSON']??''));$client=$this->json($clientRaw);
        $this->validateClient($client,'webauthn.create',(string)$user['id'],'restore_register');$origin=$this->origin($client);
        $attestation=$this->map((new CborDecoder($this->decode((string)($response['attestationObject']??''))))->decode());
        if(($attestation['fmt']??null)!=='none')throw new HttpException('Unsupported restore credential attestation format',422);
        $authData=$attestation['authData']??null;if(!is_string($authData))throw new HttpException('Invalid restore credential authenticator data',422);
        [$credentialId,$pem,$counter]=$this->registrationAuthData($authData);
        $provided=(string)($credential['rawId']??$credential['id']??'');if($provided===''||!hash_equals($provided,$this->b64($credentialId)))throw new HttpException('Restore credential ID mismatch',422);
        $hash=hash('sha256',$provided);$existing=$this->app->db()->fetch('SELECT id,user_id FROM restore_credentials WHERE credential_id_hash=?',[$hash]);
        if($existing&&$existing['user_id']!==$user['id'])throw new HttpException('Restore credential belongs to another account',409);
        if($existing)$this->app->db()->execute('UPDATE restore_credentials SET credential_id=?,public_key_pem=?,origin=?,sign_count=? WHERE id=?',[$provided,$pem,$origin,$counter,$existing['id']]);
        else $this->app->db()->execute('INSERT INTO restore_credentials(id,user_id,credential_id,credential_id_hash,public_key_pem,origin,sign_count) VALUES(?,?,?,?,?,?,?)',[Uuid::v4(),$user['id'],$provided,$hash,$pem,$origin,$counter]);
    }
    public function authenticationOptions(array $user):array
    {
        $credentials=$this->app->db()->fetchAll('SELECT credential_id FROM restore_credentials WHERE user_id=?',[$user['id']]);if(!$credentials)throw new HttpException('No restore credential is registered',404);
        $challenge=$this->challenge((string)$user['id'],'restore_authenticate');$allow=array_map(static fn(array $row):array=>['type'=>'public-key','id'=>$row['credential_id']],$credentials);
        $options=['challenge'=>$challenge,'rpId'=>self::RP_ID,'timeout'=>300000,'userVerification'=>'preferred','allowCredentials'=>$allow];
        return ['requestJson'=>json_encode($options,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)];
    }
    public function authenticate(string $userId,array $credential):array
    {
        $user=$this->app->db()->fetch('SELECT * FROM users WHERE id=? AND is_active=1 AND deleted_at IS NULL LIMIT 1',[$userId]);if(!$user)throw new HttpException('Restore credential is unavailable',401);
        $credentialId=(string)($credential['rawId']??$credential['id']??'');$stored=$this->app->db()->fetch('SELECT * FROM restore_credentials WHERE credential_id_hash=? AND user_id=?',[hash('sha256',$credentialId),$user['id']]);if(!$stored||!hash_equals((string)$stored['credential_id'],$credentialId))throw new HttpException('Restore credential is unavailable',401);
        $response=$this->map($credential['response']??null);$clientRaw=$this->decode((string)($response['clientDataJSON']??''));$client=$this->json($clientRaw);
        $this->validateClient($client,'webauthn.get',(string)$user['id'],'restore_authenticate');if(!hash_equals((string)$stored['origin'],$this->origin($client)))throw new HttpException('Restore credential origin mismatch',401);
        $authData=$this->decode((string)($response['authenticatorData']??''));if(strlen($authData)<37||!hash_equals(hash('sha256',self::RP_ID,true),substr($authData,0,32))||(ord($authData[32])&1)!==1)throw new HttpException('Invalid restore authenticator data',401);
        $counter=unpack('N',substr($authData,33,4))[1];$previous=(int)$stored['sign_count'];if($counter!==0&&$previous!==0&&$counter<=$previous)throw new HttpException('Restore credential counter did not advance',401);
        $signature=$this->decode((string)($response['signature']??''));$signed=$authData.hash('sha256',$clientRaw,true);if(openssl_verify($signed,$signature,(string)$stored['public_key_pem'],OPENSSL_ALGO_SHA256)!==1)throw new HttpException('Invalid restore credential signature',401);
        $this->app->db()->execute('UPDATE restore_credentials SET sign_count=? WHERE id=?',[$counter,$stored['id']]);return $user;
    }
    private function challenge(string $userId,string $purpose):string
    {
        $value=$this->b64(random_bytes(32));$this->app->db()->execute('DELETE FROM webauthn_challenges WHERE expires_at<=UTC_TIMESTAMP(6) OR used_at IS NOT NULL');
        $this->app->db()->execute('INSERT INTO webauthn_challenges(id,user_id,purpose,challenge_hash,expires_at) VALUES(?,?,?,?,?)',[Uuid::v4(),$userId,$purpose,hash('sha256',$value),gmdate('Y-m-d H:i:s',time()+300)]);return $value;
    }
    private function validateClient(array $client,string $type,string $userId,string $purpose):void
    {
        if(($client['type']??null)!==$type)throw new HttpException('Invalid restore credential operation',422);$challenge=(string)($client['challenge']??'');if($challenge==='')throw new HttpException('Restore credential challenge is missing',422);
        $row=$this->app->db()->fetch('SELECT id FROM webauthn_challenges WHERE user_id=? AND purpose=? AND challenge_hash=? AND used_at IS NULL AND expires_at>UTC_TIMESTAMP(6)',[$userId,$purpose,hash('sha256',$challenge)]);if(!$row)throw new HttpException('Restore credential challenge is invalid or expired',401);
        $updated=$this->app->db()->execute('UPDATE webauthn_challenges SET used_at=UTC_TIMESTAMP(6) WHERE id=? AND used_at IS NULL',[$row['id']])->rowCount();if($updated!==1)throw new HttpException('Restore credential challenge was already used',401);
    }
    private function registrationAuthData(string $data):array
    {
        if(strlen($data)<55||!hash_equals(hash('sha256',self::RP_ID,true),substr($data,0,32)))throw new HttpException('Invalid restore credential RP ID',422);$flags=ord($data[32]);if(($flags&1)!==1||($flags&64)!==64)throw new HttpException('Invalid restore credential flags',422);
        $counter=unpack('N',substr($data,33,4))[1];$length=unpack('n',substr($data,53,2))[1];if($length<1||strlen($data)<55+$length)throw new HttpException('Invalid restore credential ID',422);
        $credentialId=substr($data,55,$length);$key=$this->map((new CborDecoder(substr($data,55+$length)))->decode());if(($key[1]??null)!==2||($key[3]??null)!==-7||($key[-1]??null)!==1||!is_string($key[-2]??null)||!is_string($key[-3]??null)||strlen($key[-2])!==32||strlen($key[-3])!==32)throw new HttpException('Unsupported restore credential public key',422);
        $der=hex2bin('3059301306072A8648CE3D020106082A8648CE3D03010703420004').$key[-2].$key[-3];$pem="-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der),64,"\n")."-----END PUBLIC KEY-----\n";return [$credentialId,$pem,$counter];
    }
    private function origin(array $client):string{$origin=(string)($client['origin']??'');if(!in_array($origin,self::ANDROID_APP_ORIGINS,true))throw new HttpException('Invalid restore credential origin',422);return $origin;}
    private function decode(string $value):string{$decoded=base64_decode(strtr($value,'-_','+/'),true);if($decoded===false)throw new HttpException('Invalid restore credential encoding',422);return $decoded;}
    private function b64(string $value):string{return rtrim(strtr(base64_encode($value),'+/','-_'),'=');}
    private function json(string $value):array{$decoded=json_decode($value,true);if(!is_array($decoded))throw new HttpException('Invalid restore credential JSON',422);return $decoded;}
    private function map(mixed $value):array{if(!is_array($value))throw new HttpException('Invalid restore credential payload',422);return $value;}
}

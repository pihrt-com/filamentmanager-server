<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use FilamentManager\Core\App;
use FilamentManager\Core\HttpException;
use FilamentManager\Core\Uuid;

final class TokenService
{
    public function __construct(private readonly App $app) {}
    public function login(string $username,string $password,string $deviceName,string $appVersion,string $requestedDeviceId=''):array
    {
        $user=$this->app->db()->fetch('SELECT * FROM users WHERE username=? AND is_active=1 AND deleted_at IS NULL LIMIT 1',[$username]);
        if(!$user||($user['locked_until']&&strtotime((string)$user['locked_until'])>time())||!password_verify($password,(string)$user['password_hash'])){
            if($user){$count=(int)$user['failed_login_count']+1;$this->app->db()->execute('UPDATE users SET failed_login_count=?,locked_until=? WHERE id=?',[$count,$count>=5?gmdate('Y-m-d H:i:s',time()+900):null,$user['id']]);}
            usleep(random_int(200000,450000));throw new HttpException('Invalid credentials',401);
        }
        $this->app->db()->execute('UPDATE users SET failed_login_count=0,locked_until=NULL,last_login_at=UTC_TIMESTAMP(6) WHERE id=?',[$user['id']]);
        return $this->app->db()->transaction(function($db)use($user,$deviceName,$appVersion,$requestedDeviceId){$validId=preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',$requestedDeviceId)===1;$device=$validId?$db->fetch('SELECT id,workspace_id,user_id FROM devices WHERE id=? FOR UPDATE',[$requestedDeviceId]):null;$canReuse=$device&&$device['workspace_id']===$user['workspace_id']&&$device['user_id']===$user['id'];if($canReuse){$deviceId=$requestedDeviceId;$db->execute('UPDATE api_refresh_tokens SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP(6)) WHERE device_id=?',[$deviceId]);$db->execute('UPDATE api_access_tokens SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP(6)) WHERE device_id=?',[$deviceId]);$db->execute('UPDATE devices SET name=?,platform=?,app_version=?,last_seen_at=UTC_TIMESTAMP(6),revoked_at=NULL WHERE id=?',[mb_substr($deviceName?:'Android device',0,120),'android',mb_substr($appVersion,0,40),$deviceId]);}else{$deviceId=$validId&&!$device?$requestedDeviceId:Uuid::v4();$db->execute('INSERT INTO devices(id,workspace_id,user_id,name,platform,app_version,last_seen_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP(6))',[$deviceId,$user['workspace_id'],$user['id'],mb_substr($deviceName?:'Android device',0,120),'android',mb_substr($appVersion,0,40)]);}return $this->issuePair($user,$deviceId);});
    }
    public function refresh(string $rawRefresh):array
    {
        return $this->app->db()->transaction(function($db)use($rawRefresh){$hash=hash('sha256',$rawRefresh);$row=$db->fetch('SELECT rt.*,u.id user_id,u.workspace_id,u.username,u.display_name,u.role,u.locale,u.is_active,d.revoked_at device_revoked FROM api_refresh_tokens rt JOIN users u ON u.id=rt.user_id JOIN devices d ON d.id=rt.device_id WHERE rt.token_hash=? FOR UPDATE',[$hash]);if(!$row||$row['revoked_at']||$row['device_revoked']||!$row['is_active']||strtotime((string)$row['expires_at'])<=time())throw new HttpException('Invalid refresh token',401);return $this->issueAccess($row,$row['device_id'],$rawRefresh,(string)$row['expires_at']);});
    }
    public function authenticate(string $rawToken):array
    {
        $row=$this->app->db()->fetch('SELECT u.id,u.workspace_id,u.username,u.display_name,u.role,u.locale,t.device_id FROM api_access_tokens t JOIN users u ON u.id=t.user_id JOIN devices d ON d.id=t.device_id WHERE t.token_hash=? AND t.revoked_at IS NULL AND t.expires_at>UTC_TIMESTAMP(6) AND u.is_active=1 AND u.deleted_at IS NULL AND d.revoked_at IS NULL',[hash('sha256',$rawToken)]);
        if(!$row)throw new HttpException('Invalid or expired access token',401);
        $this->app->db()->execute('UPDATE devices SET last_seen_at=UTC_TIMESTAMP(6) WHERE id=?',[$row['device_id']]);return $row;
    }
    public function logout(string $rawRefresh):void
    {
        $row=$this->app->db()->fetch('SELECT device_id FROM api_refresh_tokens WHERE token_hash=?',[hash('sha256',$rawRefresh)]);if(!$row)return;$this->app->db()->execute('UPDATE api_refresh_tokens SET revoked_at=UTC_TIMESTAMP(6) WHERE device_id=? AND revoked_at IS NULL',[$row['device_id']]);$this->app->db()->execute('UPDATE api_access_tokens SET revoked_at=UTC_TIMESTAMP(6) WHERE device_id=? AND revoked_at IS NULL',[$row['device_id']]);
    }
    private function issuePair(array $user,string $deviceId,?string $rotatedFrom=null):array
    {
        $refresh=$this->randomToken();$refreshId=Uuid::v4();$refreshTtl=(int)$this->app->config('refresh_token_ttl',2592000);$userId=(string)($user['user_id']??$user['id']);$refreshExpiry=gmdate('Y-m-d H:i:s',time()+$refreshTtl);
        $this->app->db()->execute('INSERT INTO api_refresh_tokens(id,device_id,user_id,token_hash,expires_at,rotated_from_id) VALUES(?,?,?,?,?,?)',[$refreshId,$deviceId,$userId,hash('sha256',$refresh),gmdate('Y-m-d H:i:s',time()+$refreshTtl),$rotatedFrom]);
        return $this->issueAccess($user,$deviceId,$refresh,$refreshExpiry);
    }
    private function issueAccess(array $user,string $deviceId,string $refresh,string $refreshExpiry):array
    {
        $access=$this->randomToken();$accessId=Uuid::v4();$accessTtl=(int)$this->app->config('access_token_ttl',900);$userId=(string)($user['user_id']??$user['id']);$refreshTtl=max(1,strtotime($refreshExpiry)-time());
        $this->app->db()->execute('INSERT INTO api_access_tokens(id,device_id,user_id,token_hash,expires_at) VALUES(?,?,?,?,?)',[$accessId,$deviceId,$userId,hash('sha256',$access),gmdate('Y-m-d H:i:s',time()+$accessTtl)]);
        return ['tokenType'=>'Bearer','accessToken'=>$access,'accessTokenExpiresIn'=>$accessTtl,'refreshToken'=>$refresh,'refreshTokenExpiresIn'=>$refreshTtl,'deviceId'=>$deviceId,'user'=>['id'=>$userId,'workspaceId'=>$user['workspace_id'],'username'=>$user['username'],'displayName'=>$user['display_name'],'role'=>$user['role'],'locale'=>$user['locale']]];
    }
    private function randomToken():string{return rtrim(strtr(base64_encode(random_bytes(48)),'+/','-_'),'=');}
}

<?php

declare(strict_types=1);

namespace FilamentManager\Services;

use FilamentManager\Core\App;
use FilamentManager\Core\HttpException;
use FilamentManager\Core\Uuid;

final class IntegrationTokenService
{
    public function __construct(private readonly App $app) {}

    public function create(string $workspaceId,string $userId,string $name): array
    {
        $raw='fmps_'.rtrim(strtr(base64_encode(random_bytes(36)),'+/','-_'),'=');$id=Uuid::v4();$this->app->db()->execute('INSERT INTO integration_tokens(id,workspace_id,created_by_user_id,name,token_hash) VALUES(?,?,?,?,?)',[$id,$workspaceId,$userId,mb_substr(trim($name)?:'PrusaSlicer',0,120),hash('sha256',$raw)]);return ['id'=>$id,'token'=>$raw];
    }

    public function authenticate(string $raw): array
    {
        $row=$this->app->db()->fetch('SELECT * FROM integration_tokens WHERE token_hash=? AND revoked_at IS NULL',[hash('sha256',$raw)]);if(!$row)throw new HttpException('Invalid or revoked integration token',401);$this->app->db()->execute('UPDATE integration_tokens SET last_used_at=UTC_TIMESTAMP(6) WHERE id=?',[$row['id']]);return $row;
    }
}

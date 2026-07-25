<?php

namespace Tests\Unit;

use App\Utils\Vless;
use PHPUnit\Framework\TestCase;

class VlessTest extends TestCase
{
    public function testBuildsRealityUriAndClashMetaProxy()
    {
        $server = [
            'name' => 'HK Reality',
            'host' => 'hk.example.com',
            'port' => 443,
            'security' => 'reality',
            'flow' => 'xtls-rprx-vision',
            'network' => 'grpc',
            'networkSettings' => ['serviceName' => 'edge'],
            'realitySettings' => [
                'serverName' => 'www.example.com',
                'publicKey' => 'public-key',
                'shortId' => '0123456789abcdef',
                'fingerprint' => 'chrome'
            ]
        ];

        $uri = Vless::uri('11111111-1111-1111-1111-111111111111', $server);
        $proxy = Vless::clash('11111111-1111-1111-1111-111111111111', $server);

        $this->assertStringStartsWith('vless://', $uri);
        $this->assertStringContainsString('security=reality', $uri);
        $this->assertStringContainsString('pbk=public-key', $uri);
        $this->assertStringEndsWith("\r\n", $uri);
        $this->assertSame('vless', $proxy['type']);
        $this->assertSame('public-key', $proxy['reality-opts']['public-key']);
    }

    public function testBuildsV2bXCompatibleNodeConfigWithoutLeakingPrivateKey()
    {
        $server = [
            'server_port' => 443,
            'security' => 'reality',
            'network' => 'tcp',
            'flow' => 'xtls-rprx-vision',
            'realitySettings' => [
                'serverName' => 'www.example.com',
                'publicKey' => 'public-key',
                'privateKey' => 'private-key',
                'shortId' => '0123456789abcdef',
                'dest' => 'www.example.com',
                'serverPort' => 443
            ]
        ];

        $node = Vless::uniProxyConfig($server);
        $client = Vless::clientRealitySettings($server['realitySettings']);

        $this->assertSame(2, $node['tls']);
        $this->assertSame('private-key', $node['tlsSettings']['private_key']);
        $this->assertArrayNotHasKey('privateKey', $client);
        $this->assertArrayNotHasKey('dest', $client);
        $this->assertArrayNotHasKey('serverPort', $client);
    }
}

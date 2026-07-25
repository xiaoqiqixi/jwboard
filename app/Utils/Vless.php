<?php

namespace App\Utils;

class Vless
{
    public static function uniProxyConfig(array $server): array
    {
        $security = $server['security'] ?? 'tls';
        $tls = $server['tlsSettings'] ?? [];
        $reality = $server['realitySettings'] ?? [];

        return [
            'server_port' => (int) $server['server_port'],
            'network' => $server['network'] ?? 'tcp',
            'networkSettings' => $server['networkSettings'] ?? [],
            'security' => $security,
            // V2bX's JWBoard adapter uses the legacy numeric `tls` field.
            'tls' => ['none' => 0, 'tls' => 1, 'reality' => 2][$security] ?? 0,
            'flow' => $server['flow'] ?? '',
            'tlsSettings' => [
                'server_name' => $security === 'reality'
                    ? ($reality['serverName'] ?? '')
                    : ($tls['serverName'] ?? ''),
                'dest' => $reality['dest'] ?? '',
                'server_port' => (string) ($reality['serverPort'] ?? ''),
                'short_id' => $reality['shortId'] ?? '',
                'private_key' => $reality['privateKey'] ?? ''
            ],
            'realitySettings' => $reality
        ];
    }

    public static function clientRealitySettings(array $settings): array
    {
        return array_diff_key($settings, array_flip(['privateKey', 'dest', 'serverPort']));
    }

    public static function uri(string $uuid, array $server): string
    {
        $security = $server['security'] ?? 'tls';
        $query = [
            'encryption' => 'none',
            'security' => $security,
            'type' => $server['network'] ?? 'tcp'
        ];
        if (!empty($server['flow'])) $query['flow'] = $server['flow'];

        $tls = $server['tlsSettings'] ?? [];
        if (!empty($tls['serverName'])) $query['sni'] = $tls['serverName'];
        if (!empty($tls['allowInsecure'])) $query['allowInsecure'] = '1';

        $network = $server['networkSettings'] ?? [];
        if (($server['network'] ?? '') === 'ws') {
            if (!empty($network['path'])) $query['path'] = $network['path'];
            if (!empty($network['headers']['Host'])) $query['host'] = $network['headers']['Host'];
        }
        if (($server['network'] ?? '') === 'grpc' && !empty($network['serviceName'])) {
            $query['serviceName'] = $network['serviceName'];
        }

        if ($security === 'reality') {
            $reality = $server['realitySettings'] ?? [];
            if (!empty($reality['serverName'])) $query['sni'] = $reality['serverName'];
            if (!empty($reality['publicKey'])) $query['pbk'] = $reality['publicKey'];
            if (!empty($reality['shortId'])) $query['sid'] = $reality['shortId'];
            if (!empty($reality['fingerprint'])) $query['fp'] = $reality['fingerprint'];
            if (!empty($reality['spiderX'])) $query['spx'] = $reality['spiderX'];
        }

        return sprintf(
            "vless://%s@%s:%s?%s#%s\r\n",
            rawurlencode($uuid),
            $server['host'],
            $server['port'],
            http_build_query($query, '', '&', PHP_QUERY_RFC3986),
            rawurlencode($server['name'])
        );
    }

    public static function clash(string $uuid, array $server): array
    {
        $security = $server['security'] ?? 'tls';
        $proxy = [
            'name' => $server['name'],
            'type' => 'vless',
            'server' => $server['host'],
            'port' => (int) $server['port'],
            'uuid' => $uuid,
            'udp' => true,
            'tls' => $security !== 'none'
        ];
        if (!empty($server['flow'])) $proxy['flow'] = $server['flow'];

        $tls = $server['tlsSettings'] ?? [];
        if (!empty($tls['serverName'])) $proxy['servername'] = $tls['serverName'];
        if (!empty($tls['allowInsecure'])) $proxy['skip-cert-verify'] = true;

        $network = $server['networkSettings'] ?? [];
        if (($server['network'] ?? '') === 'ws') {
            $proxy['network'] = 'ws';
            if (!empty($network['path'])) $proxy['ws-opts']['path'] = $network['path'];
            if (!empty($network['headers']['Host'])) $proxy['ws-opts']['headers'] = ['Host' => $network['headers']['Host']];
        }
        if (($server['network'] ?? '') === 'grpc') {
            $proxy['network'] = 'grpc';
            if (!empty($network['serviceName'])) $proxy['grpc-opts']['grpc-service-name'] = $network['serviceName'];
        }
        if ($security === 'reality') {
            $reality = $server['realitySettings'] ?? [];
            $proxy['reality-opts'] = array_filter([
                'public-key' => $reality['publicKey'] ?? null,
                'short-id' => $reality['shortId'] ?? null
            ]);
            if (!empty($reality['serverName'])) $proxy['servername'] = $reality['serverName'];
            if (!empty($reality['fingerprint'])) $proxy['client-fingerprint'] = $reality['fingerprint'];
        }
        return $proxy;
    }
}

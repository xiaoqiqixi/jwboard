<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ServerVlessSave extends FormRequest
{
    public function rules()
    {
        return [
            'show' => 'nullable|in:0,1',
            'name' => 'required',
            'group_id' => 'required|array',
            'route_id' => 'nullable|array',
            'parent_id' => 'nullable|integer',
            'host' => 'required',
            'port' => 'required',
            'server_port' => 'required|integer',
            'security' => 'required|in:none,tls,reality',
            'flow' => 'nullable|in:,xtls-rprx-vision,xtls-rprx-vision-udp443',
            'tags' => 'nullable|array',
            'rate' => 'required|numeric',
            'network' => 'required|in:tcp,ws,grpc',
            'networkSettings' => 'nullable|array',
            'tlsSettings' => 'nullable|array',
            'realitySettings' => 'required_if:security,reality|array',
            'realitySettings.serverName' => 'required_if:security,reality|string',
            'realitySettings.publicKey' => 'required_if:security,reality|string',
            'realitySettings.privateKey' => 'required_if:security,reality|string',
            'realitySettings.shortId' => 'required_if:security,reality|string',
            'realitySettings.dest' => 'required_if:security,reality|string',
            'realitySettings.serverPort' => 'required_if:security,reality|integer|min:1|max:65535',
            'ruleSettings' => 'nullable|array',
            'dnsSettings' => 'nullable|array'
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin\Server;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServerVlessSave;
use App\Http\Requests\Admin\ServerVlessUpdate;
use App\Models\ServerVless;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VlessController extends Controller
{
    public function fetch()
    {
        return response(['data' => ServerVless::orderBy('sort', 'ASC')->get()]);
    }

    public function save(ServerVlessSave $request)
    {
        $params = $request->validated();
        if ($request->input('id')) {
            $server = ServerVless::find($request->input('id'));
            if (!$server) abort(404, 'VLESS节点不存在');
            $server->update($params);
        } else {
            ServerVless::create($params);
        }
        return response(['data' => true]);
    }

    public function drop(Request $request)
    {
        $server = ServerVless::find($request->input('id'));
        if (!$server) abort(404, 'VLESS节点不存在');
        return response(['data' => $server->delete()]);
    }

    public function update(ServerVlessUpdate $request)
    {
        $server = ServerVless::find($request->input('id'));
        if (!$server) abort(404, 'VLESS节点不存在');
        $server->update($request->validated());
        return response(['data' => true]);
    }

    public function copy(Request $request)
    {
        $server = ServerVless::find($request->input('id'));
        if (!$server) abort(404, 'VLESS节点不存在');
        $copy = $server->replicate(['id']);
        $copy->show = 0;
        $copy->save();
        return response(['data' => true]);
    }

    public function sort(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array'])['ids'];
        DB::transaction(function () use ($ids) {
            foreach ($ids as $sort => $id) {
                ServerVless::whereKey($id)->update(['sort' => $sort + 1]);
            }
        });
        return response(['data' => true]);
    }

}

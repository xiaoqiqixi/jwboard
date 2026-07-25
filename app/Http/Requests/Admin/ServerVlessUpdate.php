<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ServerVlessUpdate extends FormRequest
{
    public function rules()
    {
        return ['show' => 'required|in:0,1'];
    }
}

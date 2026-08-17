<?php

namespace App\Http\Requests\Task;

class UpdateTaskRequest extends StoreTaskRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['title'] = ['sometimes', 'required', 'string', 'max:255'];

        return $rules;
    }
}

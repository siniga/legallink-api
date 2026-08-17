<?php

namespace App\Http\Requests\Calendar;

class UpdateCalendarEventRequest extends StoreCalendarEventRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['title'] = ['sometimes', 'required', 'string', 'max:255'];
        $rules['type'] = ['sometimes', 'required', 'in:hearing,court_mention,meeting,deadline,other'];
        $rules['date'] = ['sometimes', 'required', 'date'];

        return $rules;
    }
}

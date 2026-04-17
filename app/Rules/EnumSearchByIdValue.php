<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;

class EnumSearchByIdValue implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    protected $model;
    public function __construct($model)
    {
        $this->model = $model;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!$value) {
            return true;
        }

        return $this->validateField($value,$attribute);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'No existe opción con el valor indicado. Busque en la pagina de Enumn';
    }

    protected function validateField($value,$attribute)
    {
        $modelSearch = 'App\Models\\' . $this->model;
        try {
            $result = $modelSearch::findOrFail($value);
            return true;
        } catch (ModelNotFoundException $exception) {
            return false;
        }

        return true;
    }
}

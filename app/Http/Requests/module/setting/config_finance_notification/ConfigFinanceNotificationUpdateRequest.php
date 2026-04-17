<?php

namespace App\Http\Requests\module\setting\config_finance_notification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;


class ConfigFinanceNotificationUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'auto_send_notifications' => 'required|boolean',
            'message_type' => 'required|in:email,sms,whatsapp',
            'notification_days' => [
                'string',
                function ($attribute, $value, $fail) {
                    $days = explode(',', $value);
                    foreach ($days as $day) {
                        if (!is_numeric($day)) {
                            $fail('Los días de notificación deben ser números.');
                            return;
                        }
                        $day = (int)$day;
                        if ($day < 1 || $day > 7) {
                            $fail('Los días de notificación deben estar entre 1 (Lunes) y 7 (Domingo).');
                            return;
                        }
                    }
                }
            ],
            'notification_hours' => [
                'string',
                function ($attribute, $value, $fail) {
                    $hours = explode(',', $value);
                    foreach ($hours as $hour) {
                        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $hour)) {
                            $fail('El formato de las horas debe ser HH:MM (ej. 09:00, 17:30).');
                            return;
                        }
                    }
                }
            ],
            'email_bcc' => [
                'string',
                function ($attribute, $value, $fail) {
                    $emails = explode(',', $value);
                    foreach ($emails as $email) {
                        if (!filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                            $fail('El campo email_bcc contiene direcciones de correo no válidas.');
                            return;
                        }
                    }
                }
            ],
        ];
    }

    public function messages()
    {
        return [
            'message_type.in' => 'El tipo de mensaje debe ser email, sms o whatsapp',
            'delay_hours.max' => 'El retraso máximo permitido es de 24 horas',
            // Agrega más mensajes personalizados según necesites
        ];
    }
}

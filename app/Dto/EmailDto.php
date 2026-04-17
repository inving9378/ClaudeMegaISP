<?php

namespace App\Dto;

final class EmailDto
{
    public string $subject = '';
    public string $html = '';
    public string $recipient_email = '';
    public string $cc_email = '';
    public string $client_id = '';
    public string $email_bcc = '';
}

<?php


namespace App\Http\Base;


class Encryption
{
    var $skey = "yourSecretKey"; // you can change it

    public function safe_b64encode($string)
    {
        $data = base64_encode($string);
        return str_replace(array('+', '/', '='), array('-', '_', ''), $data);
    }

    public function safe_b64decode($string)
    {
        $data = str_replace(array('-', '_'), array('+', '/'), $string);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }

    public function encode($value)
    {
        if (!$value) {
            return false;
        }
        $text = $value;
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $crypttext = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $this->skey, $text, MCRYPT_MODE_ECB, $iv);
        return trim($this->safe_b64encode($crypttext));
    }

    public function decode($value)
    {
        if (!$value) {
            return false;
        }
        $crypttext = $this->safe_b64decode($value);
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $decrypttext = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $this->skey, $crypttext, MCRYPT_MODE_ECB, $iv);
        return trim($decrypttext);
    }

    public static function randomPassword() {
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '1234567890';
        $special = '!@#$%^&*()-_+=<>?'; // Opcional si quieres incluir símbolos
        $allCharacters = $lowercase . $uppercase . $numbers;

        // Asegurarse de que cumple con los requisitos mínimos
        $password = [
            $lowercase[rand(0, strlen($lowercase) - 1)], // Al menos una minúscula
            $uppercase[rand(0, strlen($uppercase) - 1)], // Al menos una mayúscula
            $numbers[rand(0, strlen($numbers) - 1)],     // Al menos un número
        ];

        // Completar con caracteres aleatorios hasta llegar a 8 caracteres
        for ($i = 3; $i < 8; $i++) {
            $password[] = $allCharacters[rand(0, strlen($allCharacters) - 1)];
        }

        // Mezclar los caracteres para evitar un patrón predecible
        shuffle($password);

        return implode($password);
    }

    public static function randomUser() {
        $alphabet = '1234567890';
        $user = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $user[] = $alphabet[$n];
        }
        return implode($user); //turn the array into a string
    }
}

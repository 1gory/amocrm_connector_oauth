<?php

class Connector
{
    public function __construct()
    {
        $expiration_date = file_exists(EXPIRATION_DATE_PATH) ? file_get_contents(EXPIRATION_DATE_PATH) : null;
        $access_token = file_exists(ACCESS_TOKEN_PATH) ? file_get_contents(ACCESS_TOKEN_PATH) : null;
        $refresh_token = file_exists(REFRESH_TOKEN_PATH) ? file_get_contents(REFRESH_TOKEN_PATH) : null;

        if ($access_token && $refresh_token && $expiration_date > time()) {
            return;
        }

        $link = 'https://' . SUBDOMAIN . '.amocrm.ru/oauth2/access_token';

        $data = [
            'client_id' => CLIENT_ID,
            'client_secret' => CLIENT_SECRET,
            'redirect_uri' => REDIRECT_URI,
        ];

        if ($refresh_token && $expiration_date < time()) {
            // need refresh access token
            $grant_type = 'refresh_token';
            $data['refresh_token'] = $refresh_token;
        } else {
            // need get access token
            $grant_type = 'authorization_code';
            $data['code'] = AUTHORIZATION_CODE;
        }

        $data['grant_type'] = $grant_type;

        $out = $this->sendCurlRequest($data, $link);

        $response = json_decode($out, true);

        $access_token = $response['access_token']; //Access токен
        $refresh_token = $response['refresh_token']; //Refresh токен
        $expires_in = $response['expires_in']; //Refresh токен

        file_put_contents(EXPIRATION_DATE_PATH, time() + $expires_in);
        file_put_contents(ACCESS_TOKEN_PATH, $access_token);
        file_put_contents(REFRESH_TOKEN_PATH, $refresh_token);
    }

    /**
     * @param string $leadName
     * @param string $price
     * @param array $customFields
     * @return int
     * @throws Exception
     */
    public function createLead($leadName, $price, $customFields = [])
    {
        $leads['request']['leads']['add'] = [
            [
                'name' => $leadName,
                'price' => $price,
                'date_create' => (new \DateTime())->format('U'),
                'custom_fields' => $customFields,
            ],
        ];

        $link = 'https://' . SUBDOMAIN . '.amocrm.ru/private/api/v2/json/leads/set';
        $access_token = file_get_contents(ACCESS_TOKEN_PATH);
        $out = $this->sendCurlRequest($leads, $link, ['Authorization: Bearer ' . $access_token]);

        $response = json_decode($out, true);

        $response = $response['response'];

        return $response['leads']['add'][0]['id'];
    }

    /**
     * @param $orderId
     * @param $contactName
     * @param $customFields
     * @return mixed
     * @throws Exception
     */
    public function createContact($orderId, $contactName, $customFields = [])
    {
        $contacts['add'] = [
            [
                'name' => $contactName,
                'created_at' => (new \DateTime())->format('U'),
                'leads_id' => [
                    (string)$orderId,
                ],
                'custom_fields' => $customFields,
            ],
        ];

        $link = 'https://' . SUBDOMAIN . '.amocrm.ru/api/v2/contacts';

        $access_token = file_get_contents(ACCESS_TOKEN_PATH);
        $this->sendCurlRequest($contacts, $link, ['Authorization: Bearer ' . $access_token]);

//        $response = json_decode($out, true);
//        $response = $response['response'];

        return;
    }

    /**
     * @param $orderId
     * @param $message
     * @return null
     * @throws Exception
     */
    public function createNote($orderId, $message)
    {
        $notes = ['add' => []];

        $notes['add'][] = [
            'element_id' => $orderId,
            'element_type' => '2',
            'text' => $message,
            'note_type' => '4',
            'created_at' => (new \DateTime())->format('U'),
        ];

        $link = 'https://' . SUBDOMAIN . '.amocrm.ru/api/v2/notes';

        if (empty($notes)) {
            return null;
        }

        $access_token = file_get_contents(ACCESS_TOKEN_PATH);
        $this->sendCurlRequest($notes, $link, ['Authorization: Bearer ' . $access_token]);

        return;
    }

    /**
     * @param $data
     * @param $link
     * @param array $header
     * @return bool|string
     */
    private function sendCurlRequest($data, $link, $header = [])
    {
        $curl = curl_init();

        $defaultHeader = ['Content-Type: application/json'];

        $header = array_merge($header, $defaultHeader);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-API-client/1.0');
        curl_setopt($curl, CURLOPT_URL, $link);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);

        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $code = (int)$code;

        $errors = [
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
        ];

        try {
            if ($code < 200 || $code > 204) {
                throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undefined error', $code);
            }
        } catch (\Exception $e) {
            die('Ошибка: ' . $e->getMessage() . PHP_EOL . 'Код ошибки: ' . $e->getCode());
        }

        return $out;
    }
}

<?php

if (!function_exists('makeCURLRequest')) {
    /**
     * Make a cURL request.
     *
     * @author Ajmal Akram S
     * @param string $url The endpoint URL.
     * @param array $postData Data to send in the POST request.
     * @param array $headers Headers for the cURL request.
     * @return array|string Response data or error message.
     */
    function makeCURLRequest(string $url, array $postData, array $headers = [])
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        // Execute the request and store the response
        $response = curl_exec($curl);

        // Handle errors
        if (curl_errno($curl)) {
            $error = 'cURL Error: ' . curl_error($curl);
            curl_close($curl);
            return ['error' => $error];
        }

        curl_close($curl);

        // Return the response
        return $response;
    }
}

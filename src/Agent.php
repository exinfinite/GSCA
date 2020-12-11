<?php
namespace Exinfinite\GSCA;

class Agent {
    private $api = "https://www.googleapis.com/webmasters/v3/sites/%s/searchAnalytics/query";
    public function __construct($credentials_path) {
        putenv("GOOGLE_APPLICATION_CREDENTIALS={$credentials_path}");
        $this->authClient();
    }
    private function authClient() {
        $client = new \Google\Client();
        $client->useApplicationDefaultCredentials();
        $client->addScope(\Google_Service_SearchConsole::WEBMASTERS);
        $client->addScope(\Google_Service_SearchConsole::WEBMASTERS_READONLY);
        $this->httpClient = $client->authorize();
    }
    private function parseResult(\Psr\Http\Message\ResponseInterface $response) {
        $contents = $response->getBody()->getContents();
        $json = json_decode($contents, true);
        return (json_last_error() == JSON_ERROR_NONE) ? $json : [];
    }
    /**
     * 網站成效
     *
     * @param String $site
     * @param Array $body
     * @return Array
     * Ref: https://developers.google.com/webmaster-tools/search-console-api-original/v3/searchanalytics/query
     */
    public function performance($site, $body = []) {
        $response = $this->httpClient->post(sprintf($this->api, urlencode($site)), ['json' => $body]);
        return $this->parseResult($response);
    }
}
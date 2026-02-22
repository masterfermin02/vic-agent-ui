<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class VicidialApiService
{
    public function agentLogin(
        string $user,
        string $pass,
        string $phoneLogin,
        string $phonePass,
        string $campaignId
    ): string {
        return $this->post([
            'function' => 'login',
            'user' => $user,
            'pass' => $pass,
            'phone_login' => $phoneLogin,
            'phone_pass' => $phonePass,
            'campaign' => $campaignId,
            'format' => 'text',
        ]);
    }

    public function agentLogout(string $user, string $pass, string $campaignId): string
    {
        return $this->post([
            'function' => 'logout',
            'user' => $user,
            'pass' => $pass,
            'campaign' => $campaignId,
            'format' => 'text',
        ]);
    }

    public function setPauseCode(string $user, string $pass, string $campaignId, string $pauseCode = ''): string
    {
        return $this->post([
            'function' => 'pause',
            'user' => $user,
            'pass' => $pass,
            'campaign' => $campaignId,
            'pause_code' => $pauseCode,
            'format' => 'text',
        ]);
    }

    public function sendDisposition(string $user, string $pass, string $campaignId, string $leadId, string $status): string
    {
        return $this->post([
            'function' => 'disposition',
            'user' => $user,
            'pass' => $pass,
            'campaign' => $campaignId,
            'lead_id' => $leadId,
            'status' => $status,
            'format' => 'text',
        ]);
    }

    public function manualDial(string $user, string $pass, string $campaignId, string $phone): string
    {
        return $this->post([
            'function' => 'dial_phone_number',
            'user' => $user,
            'pass' => $pass,
            'campaign' => $campaignId,
            'phone_number' => $phone,
            'format' => 'text',
        ]);
    }

    private function post(array $params): string
    {
        $params['source'] = 'vic-agent-ui';

        $response = Http::asForm()->post(config('vicidial.api_url'), $params);

        $body = trim($response->body());

        if (str_starts_with($body, 'ERROR')) {
            throw new \RuntimeException("VICIdial API error: {$body}");
        }

        return $body;
    }
}

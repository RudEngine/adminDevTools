<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTelegramWebhookToken;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TelegramWebhookTokenTest extends TestCase
{
    private const string SECRET = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('nutgram.webhook_secret', self::SECRET);
    }

    private function webhookUrl(): string
    {
        $domain = config('app.api_domain');

        return $domain ? 'http://'.$domain.'/webhook' : '/webhook';
    }

    public function test_webhook_is_forbidden_without_token(): void
    {
        $this->postJson($this->webhookUrl(), ['update_id' => 1])->assertForbidden();
    }

    public function test_webhook_is_forbidden_with_wrong_token(): void
    {
        $this->postJson($this->webhookUrl(), ['update_id' => 1], [
            VerifyTelegramWebhookToken::HEADER => 'wrong-secret',
        ])->assertForbidden();
    }

    public function test_webhook_is_forbidden_when_secret_is_not_configured(): void
    {
        Config::set('nutgram.webhook_secret', null);

        $this->postJson($this->webhookUrl(), ['update_id' => 1], [
            VerifyTelegramWebhookToken::HEADER => self::SECRET,
        ])->assertForbidden();
    }

    public function test_webhook_passes_with_valid_token(): void
    {
        $this->postJson($this->webhookUrl(), ['update_id' => 1], [
            VerifyTelegramWebhookToken::HEADER => self::SECRET,
        ])->assertNoContent();
    }

    public function test_secret_defaults_to_md5_of_app_key(): void
    {
        $config = require base_path('config/nutgram.php');

        $this->assertSame(
            env('TELEGRAM_WEBHOOK_SECRET') ?: md5((string) config('app.key')),
            $config['webhook_secret'],
        );
    }
}

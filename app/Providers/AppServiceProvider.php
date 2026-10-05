<?php

namespace App\Providers;

use App\Domain\Telegram\Repository\TelegramChannelsRepositoryInterface;
use App\Infrastructure\Repository\Postgress\Telegram\TelegramChannels\TelegramChannelsRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Реализации доменных портов: домен знает только интерфейс, инфраструктуру подставляем здесь.
     */
    public array $bindings = [
        TelegramChannelsRepositoryInterface::class => TelegramChannelsRepository::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->enforceMoonShineOnlyAuth();
    }

    /**
     * Оставить в приложении единственный способ аутентификации — гвард moonshine.
     *
     * Удалить гвард web правкой config/auth.php нельзя: Illuminate\Foundation\Bootstrap\
     * LoadConfiguration::mergeableOptions() всегда мержит ключи guards, providers и passwords
     * из vendor/laravel/framework/config/auth.php поверх конфига приложения, поэтому web
     * и провайдер users возвращаются даже после удаления их из config/auth.php.
     * Единственный надёжный момент их убрать — после загрузки конфигурации.
     */
    private function enforceMoonShineOnlyAuth(): void
    {
        Config::set('auth.guards', Arr::only(Config::get('auth.guards', []), ['moonshine']));
        Config::set('auth.providers', Arr::only(Config::get('auth.providers', []), ['moonshine']));

        // Сброс пароля по email: брокеров нет — значит нет и обходного пути входа.
        Config::set('auth.passwords', []);
    }
}

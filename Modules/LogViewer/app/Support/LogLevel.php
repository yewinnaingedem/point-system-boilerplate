<?php

namespace Modules\LogViewer\Support;

/**
 * PSR-3 levels as Laravel writes them, with their AdminLTE colour and icon.
 */
enum LogLevel: string
{
    case Emergency = 'emergency';
    case Alert = 'alert';
    case Critical = 'critical';
    case Error = 'error';
    case Warning = 'warning';
    case Notice = 'notice';
    case Info = 'info';
    case Debug = 'debug';

    public static function fromLogName(string $name): self
    {
        return self::tryFrom(strtolower($name)) ?? self::Info;
    }

    public function color(): string
    {
        return match ($this) {
            self::Emergency, self::Alert, self::Critical, self::Error => 'danger',
            self::Warning => 'warning',
            self::Notice => 'primary',
            self::Info => 'info',
            self::Debug => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Emergency => 'fas fa-skull-crossbones',
            self::Alert => 'fas fa-bullhorn',
            self::Critical => 'fas fa-heartbeat',
            self::Error => 'fas fa-times-circle',
            self::Warning => 'fas fa-exclamation-triangle',
            self::Notice => 'fas fa-flag',
            self::Info => 'fas fa-info-circle',
            self::Debug => 'fas fa-bug',
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

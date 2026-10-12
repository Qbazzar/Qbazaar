<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\IntegrationFieldKind;
use App\Enums\IntegrationGroup;
use Illuminate\Validation\Rule;

final readonly class IntegrationField
{
    /**
     * @param list<string> $options allowed values for select fields
     * @param list<mixed> $extraRules
     */
    private function __construct(
        public IntegrationGroup $group,
        public IntegrationFieldKind $kind,
        public bool $secret = false,
        public bool $public = false,
        public array $options = [],
        private array $extraRules = [],
    ) {}

    /**
     * @param list<mixed> $rules
     */
    public static function plain(IntegrationGroup $group, IntegrationFieldKind $kind, bool $public = false, array $rules = []): self
    {
        return new self($group, $kind, false, $public, [], $rules);
    }

    /**
     * @param list<mixed> $rules
     */
    public static function secret(IntegrationGroup $group, IntegrationFieldKind $kind = IntegrationFieldKind::TEXT, array $rules = []): self
    {
        return new self($group, $kind, true, false, [], $rules);
    }

    /**
     * @param list<string> $options
     */
    public static function select(IntegrationGroup $group, array $options): self
    {
        return new self($group, IntegrationFieldKind::SELECT, false, false, $options);
    }

    public static function toggle(IntegrationGroup $group, bool $public = false): self
    {
        return new self($group, IntegrationFieldKind::TOGGLE, false, $public);
    }

    /**
     * @return list<mixed>
     */
    public function rules(): array
    {
        if ($this->kind === IntegrationFieldKind::TOGGLE) {
            return ['boolean'];
        }

        $base = match ($this->kind) {
            IntegrationFieldKind::EMAIL => ['email:rfc', 'max:190'],
            IntegrationFieldKind::PHONE => ['regex:/^\+[1-9]\d{6,14}$/'],
            IntegrationFieldKind::URL => ['url:https', 'max:255'],
            IntegrationFieldKind::SELECT => [Rule::in($this->options)],
            IntegrationFieldKind::TEXTAREA => ['string', 'max:8000'],
            default => ['string', 'max:255'],
        };

        return ['nullable', ...$base, ...$this->extraRules];
    }
}

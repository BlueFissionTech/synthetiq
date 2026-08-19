<?php

declare(strict_types=1);

namespace BlueFission\SynthetIQ\Profiles;

use BlueFission\Arr;
use BlueFission\DevElation as Dev;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

class ProfileRegistry extends Obj
{
    /**
     * @var array<string, ConversationProfile>
     */
    protected array $_profiles = [];

    /**
     * @param array<int, ConversationProfile> $profiles
     */
    public function __construct(array $profiles = [])
    {
        parent::__construct();

        foreach ($profiles as $profile) {
            if ($profile instanceof ConversationProfile) {
                $this->register($profile);
            }
        }
    }

    public function register(ConversationProfile $profile): self
    {
        if (!$profile->isValid()) {
            return $this;
        }

        $this->_profiles[$profile->id()] = $profile;
        Dev::do('synthetiq.profile.registered', $profile->toArray());

        return $this;
    }

    /**
     * @param array<int, string> $requiredCapabilities
     */
    public function selectFor(string $intent, array $requiredCapabilities = []): ?ConversationProfile
    {
        $intent = Dev::apply('synthetiq.profile.selection.intent', Str::trim($intent));
        $intent = Str::is($intent) ? $intent : '';
        $requiredCapabilities = self::stringList($requiredCapabilities);

        foreach ($this->_profiles as $profile) {
            if (!$profile->supportsIntent($intent)) {
                continue;
            }

            $compatible = true;
            foreach ($requiredCapabilities as $capability) {
                if (!$profile->declaresCapability($capability)) {
                    $compatible = false;
                    break;
                }
            }

            if ($compatible) {
                Dev::do('synthetiq.profile.selected', [
                    'profile_id' => $profile->id(),
                    'intent' => $intent,
                ]);

                return $profile;
            }
        }

        Dev::do('synthetiq.profile.selection_failed', [
            'intent' => $intent,
            'required_capabilities' => $requiredCapabilities,
        ]);

        return null;
    }

    /**
     * @return array<string, ConversationProfile>
     */
    public function profiles(): array
    {
        return $this->_profiles;
    }

    /**
     * @return array<int, string>
     */
    protected static function stringList(array $values): array
    {
        $list = [];
        foreach ($values as $value) {
            $value = Str::trim((string)$value);
            if (Val::isNotEmpty($value) && !Arr::has($list, $value, true)) {
                $list[] = $value;
            }
        }

        return $list;
    }
}

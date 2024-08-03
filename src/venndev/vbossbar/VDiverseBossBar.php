<?php

declare(strict_types=1);

namespace venndev\vbossbar;

use pocketmine\player\Player;

final class VDiverseBossBar
{

    /** @var array<string, VBossBar> */
    private array $bossBars = [];

    public function getBossBars(): array
    {
        return $this->bossBars;
    }

    public function addBossBar(Player $player, VBossBar $bossBar): void
    {
        $this->bossBars[$player->getXuid()] = $bossBar;
    }

    public function removeBossBar(Player $player): void
    {
        unset($this->bossBars[$player->getXuid()]);
    }

    public function getBossBar(Player $player): ?VBossBar
    {
        return $this->bossBars[$player->getXuid()] ?? null;
    }

}
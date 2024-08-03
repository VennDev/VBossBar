<?php

declare(strict_types=1);

namespace venndev\vbossbar;

use GlobalLogger;
use InvalidArgumentException;
use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeFactory;
use pocketmine\entity\AttributeMap;
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use vennv\vapm\FiberManager;
use vennv\vapm\Promise;
use Throwable;

/**
 * Class VBossBar
 * @package venndev\vbossbar
 */
final class VBossBar
{

    private static int $currentId = 0;

    /** @var Player[] */
    private array $players = [];
    private string $title = "";
    private string $subTitle = "";
    private int $color = BossBarColor::PURPLE;
    public ?int $actorId = null;
    private int $id;
    private AttributeMap $attributeMap;
    protected EntityMetadataCollection $propertyManager;

    /**
     * BossBar constructor.
     * This will not spawn the bar, since there would be no players to spawn it to
     */
    public function __construct(private readonly bool $darkenScreen = false)
    {
        $this->id = self::$currentId++;
        $this->attributeMap = new AttributeMap();
        $attributeFactory = AttributeFactory::getInstance();

        $this->getAttributeMap()->add($attributeFactory->mustGet(Attribute::HEALTH)
            ->setMaxValue(100.0)
            ->setMinValue(0.0)
            ->setDefaultValue(100.0)
        );

        $this->propertyManager = new EntityMetadataCollection();

        /**
         * Why are we setting these flags?
         * Read more: https://learn.microsoft.com/en-us/minecraft/creator/reference/source/vanillabehaviorpack_snippets/entities/ender_dragon?view=minecraft-bedrock-stable
         */
        $this->propertyManager->setLong(EntityMetadataProperties::FLAGS, 0
            ^ 1 << EntityMetadataFlags::SILENT
            ^ 1 << EntityMetadataFlags::INVISIBLE
            ^ 1 << EntityMetadataFlags::NO_AI
            ^ 1 << EntityMetadataFlags::FIRE_IMMUNE
        );
        $this->propertyManager->setShort(EntityMetadataProperties::MAX_AIR, 400);
        $this->propertyManager->setString(EntityMetadataProperties::NAMETAG, $this->getFullTitle());
        $this->propertyManager->setLong(EntityMetadataProperties::LEAD_HOLDER_EID, -1);
        $this->propertyManager->setFloat(EntityMetadataProperties::SCALE, 0);
        $this->propertyManager->setFloat(EntityMetadataProperties::BOUNDING_BOX_WIDTH, 0.0);
        $this->propertyManager->setFloat(EntityMetadataProperties::BOUNDING_BOX_HEIGHT, 0.0);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function isDarkenScreen(): bool
    {
        return $this->darkenScreen;
    }

    /**
     * @return Player[]
     */
    public function getPlayers(): array
    {
        return $this->players;
    }

    /**
     * @param Player|null $player Only used for DiverseBossBar
     * @return AttributeMap
     */
    public function getAttributeMap(Player $player = null): AttributeMap
    {
        return $this->attributeMap;
    }

    protected function getPropertyManager(): EntityMetadataCollection
    {
        return $this->propertyManager;
    }

    /**
     * The text above the bar
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Text above the bar. Can be empty. Should be single-line
     * @param string $title
     * @return static
     * @throws Throwable
     */
    public function setTitle(string $title = ""): VBossBar
    {
        $this->title = $title;
        $this->sendBossTextPacket($this->getPlayers());
        return $this;
    }

    public function getSubTitle(): string
    {
        return $this->subTitle;
    }

    /**
     * Optional text below the bar. Can be empty
     * @param string $subTitle
     * @return static
     * @throws Throwable
     */
    public function setSubTitle(string $subTitle = ""): VBossBar
    {
        $this->subTitle = $subTitle;
        $this->sendBossTextPacket($this->getPlayers());
        return $this;
    }

    /**
     * The full title as a combination of the title and its subtitle. Automatically fixes encoding issues caused by newline characters
     * @return string
     */
    public function getFullTitle(): string
    {
        $text = $this->title;
        if (!empty($this->subTitle)) $text .= "\n\n" . $this->subTitle;
        return mb_convert_encoding($text, 'UTF-8');
    }


    /**
     * @param int $percentage 0-100
     * @return static
     * @throws Throwable
     */
    public function setPercentage(int $percentage): VBossBar
    {
        $percentage = (float)min(1.0, max(0.0, $percentage / 100));
        $this->getAttributeMap()->get(Attribute::HEALTH)->setValue($percentage * $this->getAttributeMap()->get(Attribute::HEALTH)->getMaxValue(), true, true);
        $this->sendBossHealthPacket($this->getPlayers());
        return $this;
    }

    public function getPercentage(): float
    {
        return $this->getAttributeMap()->get(Attribute::HEALTH)->getValue() / 100;
    }

    public function getColor(): int
    {
        return $this->color;
    }

    public static function getColorByName(string $colorName): int
    {
        return VBarColor::getColorByName($colorName);
    }

    /**
     * @throws Throwable
     */
    public function setColor(int|string $color): VBossBar
    {
        if (is_string($color)) {
            $color = VBarColor::getColorByName($color);
        } elseif (!in_array($color, VBarColor::getColors(), true)) {
            throw new InvalidArgumentException("Invalid color specified.");
        }
        $this->color = $color;
        $this->sendBossPacket($this->getPlayers());
        return $this;
    }

    /**
     * @param Player[] $players
     * @return Promise<Throwable|bool>
     * @throws Throwable
     */
    public function addPlayers(array $players): Promise
    {
        return new Promise(function ($resolve, $reject) use ($players): void {
            try {
                foreach ($players as $player) {
                    $this->addPlayer($player);
                    FiberManager::wait();
                }
                $resolve(true);
            } catch (Throwable $e) {
                $reject($e);
            }
        });
    }

    /**
     * @throws Throwable
     */
    public function addPlayer(Player $player): VBossBar
    {
        if (isset($this->players[$player->getId()])) return $this;
        $this->sendBossPacket([$player]);
        $this->players[$player->getId()] = $player;
        return $this;
    }

    /**
     * Removes a single player from this bar.
     * Use @param Player $player
     * @return static
     * @throws Throwable
     * @see BossBar::hideFrom() when just removing temporarily to save some performance / bandwidth
     */
    public function removePlayer(Player $player): VBossBar
    {
        if (!isset($this->players[$player->getId()])) {
            GlobalLogger::get()->debug("Removed player that was not added to the boss bar (" . $this::class . ")");
            return $this;
        }
        $this->sendRemoveBossPacket([$player]);
        unset($this->players[$player->getId()]);
        return $this;
    }

    /**
     * @param Player[] $players
     * @return Promise<Throwable|bool>
     * @throws Throwable
     */
    public function removePlayers(array $players): Promise
    {
        return new Promise(function ($resolve, $reject) use ($players): void {
            try {
                foreach ($players as $player) {
                    $this->removePlayer($player);
                    FiberManager::wait();
                }
                $resolve(true);
            } catch (Throwable $e) {
                $reject($e);
            }
        });
    }

    /**
     * Removes all players from this bar
     * @return Promise<Throwable|bool>
     * @throws Throwable
     */
    public function removeAllPlayers(): Promise
    {
        return new Promise(function ($resolve, $reject): void {
            try {
                foreach ($this->getPlayers() as $player) {
                    $this->removePlayer($player);
                    FiberManager::wait();
                }
                $resolve(true);
            } catch (Throwable $e) {
                $reject($e);
            }
        });
    }

    /**
     * TODO: Only registered players validation
     * Hides the bar from the specified players without removing it.
     * Useful when saving some bandwidth or when you'd like to keep the entity
     *
     * @param Player[] $players
     * @throws Throwable
     * @return Promise<Throwable|bool>
     */
    public function hideFrom(array $players): Promise
    {
        return new Promise(function ($resolve, $reject) use ($players): void {
            try {
                foreach ($players as $player) {
                    if (!$player->isConnected()) continue;
                    $player->getNetworkSession()->sendDataPacket(BossEventPacket::hide($this->actorId ?? $player->getId()));
                    FiberManager::wait();
                }
                $resolve(true);
            } catch (Throwable $e) {
                $reject($e);
            }
        });
    }

    /**
     * Hides the bar from all registered players
     * @throws Throwable
     */
    public function hideFromAll(): void
    {
        $this->hideFrom($this->getPlayers());
    }

    /**
     * TODO: Only registered players validation
     * Displays the bar to the specified players
     * @param Player[] $players
     * @throws Throwable
     */
    public function showTo(array $players): void
    {
        $this->sendBossPacket($players);
    }

    /**
     * Displays the bar to all registered players
     * @throws Throwable
     */
    public function showToAll(): void
    {
        $this->showTo($this->getPlayers());
    }

    public function getEntity(): ?Entity
    {
        if ($this->actorId === null) return null;
        return Server::getInstance()->getWorldManager()->findEntity($this->actorId);
    }

    /**
     * STILL TODO, SHOULD NOT BE USED YET
     * @param null|Entity $entity
     * @return static
     * @throws Throwable
     * TODO: use attributes and properties of the custom entity
     */
    public function setEntity(?Entity $entity = null): VBossBar
    {
        if ($entity instanceof Entity && ($entity->isClosed() || $entity->isFlaggedForDespawn())) {
            throw new InvalidArgumentException(
                message: "Entity $entity can not be used since its not valid anymore (closed or flagged for respawn)"
            );
        }
        if ($this->getEntity() instanceof Entity && !$entity instanceof Player) {
            $this->getEntity()->flagForDespawn();
        } else {
            $pk = new RemoveActorPacket();
            $pk->actorUniqueId = $this->actorId;
            NetworkBroadcastUtils::broadcastPackets($this->getPlayers(), [$pk]);
        }
        if ($entity instanceof Entity) {
            $this->actorId = $entity->getId();
            $this->attributeMap = $entity->getAttributeMap(); // TODO try some kind of auto-updating reference
            $this->getAttributeMap()->add($entity->getAttributeMap()->get(Attribute::HEALTH)); // TODO Auto-update bar for entity? Would be cool, so the api can be used for actual bosses
            $this->propertyManager = $entity->getNetworkProperties();
            if (!$entity instanceof Player) {
                $entity->despawnFromAll(); // TODO figure out why this is even here
            }
        } else {
            $this->actorId = Entity::nextRuntimeId();
        }
        $this->sendBossPacket($this->getPlayers());
        return $this;
    }

    /**
     * @param bool $removeEntity Be careful with this. If set to true, the entity will be deleted.
     * @return static
     * @throws Throwable
     */
    public function resetEntity(bool $removeEntity = false): VBossBar
    {
        if ($removeEntity && $this->getEntity() instanceof Entity && !$this->getEntity() instanceof Player) $this->getEntity()->close();
        return $this->setEntity();
    }

    /**
     * @param Player[] $players
     * @throws Throwable
     * @return Promise<Throwable|bool>
     */
    protected function sendBossPacket(array $players): Promise
    {
        return new Promise(function ($resolve, $reject) use ($players): void {
            try {
                foreach ($players as $player) {
                    if (!$player->isConnected()) continue;
                    $player->getNetworkSession()->sendDataPacket(
                        BossEventPacket::show(
                            bossActorUniqueId: $this->actorId ?? $player->getId(),
                            title: $this->getFullTitle(),
                            healthPercent: $this->getPercentage(),
                            darkenScreen: $this->isDarkenScreen(),
                            color: $this->getColor()
                        )
                    );
                    FiberManager::wait();
                }
                $resolve(true);
            } catch (Throwable $e) {
                $reject($e);
            }
        });
    }

    /**
     * @param Player[] $players
     * @throws Throwable
     * @return Promise<Throwable|bool>
     */
    protected function sendRemoveBossPacket(array $players): Promise
    {
        return new Promise(function ($resolve, $reject) use ($players): void {
            try {
                foreach ($players as $player) {
                    if (!$player->isConnected()) continue;
                    $player->getNetworkSession()->sendDataPacket(BossEventPacket::hide($this->actorId ?? $player->getId()));
                    FiberManager::wait();
                }
                $resolve(true);
            } catch (Throwable $e) {
                $reject($e);
            }
        });
    }

    /**
     * @param Player[] $players
     * @throws Throwable
     * @return Promise<Throwable|bool>
     */
    protected function sendBossTextPacket(array $players): Promise
    {
        return new Promise(function ($resolve, $reject) use ($players): void {
            try {
                foreach ($players as $player) {
                    if (!$player->isConnected()) continue;
                    $player->getNetworkSession()->sendDataPacket(BossEventPacket::title($this->actorId ?? $player->getId(), $this->getFullTitle()));
                    FiberManager::wait();
                }
                $resolve(true);
            } catch (Throwable $e) {
                $reject($e);
            }
        });
    }

    /**
     * @param Player[] $players
     */
    protected function sendAttributesPacket(array $players): void
    {
        if ($this->actorId === null) return;
        $pk = new UpdateAttributesPacket();
        $pk->actorRuntimeId = $this->actorId;
        $pk->entries = $this->getAttributeMap()->needSend();
        NetworkBroadcastUtils::broadcastPackets($players, [$pk]);
    }

    /**
     * @param Player[] $players
     * @throws Throwable
     * @return Promise<Throwable|bool>
     */
    protected function sendBossHealthPacket(array $players): Promise
    {
        return new Promise(function ($resolve, $reject) use ($players): void {
            try {
                foreach ($players as $player) {
                    if (!$player->isConnected()) continue;
                    $player->getNetworkSession()->sendDataPacket(BossEventPacket::healthPercent($this->actorId ?? $player->getId(), $this->getPercentage()));
                    FiberManager::wait();
                }
                $resolve(true);
            } catch (Throwable $e) {
                $reject($e);
            }
        });
    }

    public function __toString(): string
    {
        return __CLASS__ . " ID: $this->actorId, Players: " . count($this->players) . ", Title: \"$this->title\", Subtitle: \"$this->subTitle\", Percentage: \"" . $this->getPercentage() . "\", Color: \"" . $this->color . "\"";
    }

}
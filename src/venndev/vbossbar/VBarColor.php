<?php

declare(strict_types=1);

namespace venndev\vbossbar;

use InvalidArgumentException;
use pocketmine\network\mcpe\protocol\types\BossBarColor;

final class VBarColor
{

    public const PINK = BossBarColor::PINK;
    public const BLUE = BossBarColor::BLUE;
    public const RED = BossBarColor::RED;
    public const GREEN = BossBarColor::GREEN;
    public const YELLOW = BossBarColor::YELLOW;
    public const PURPLE = BossBarColor::PURPLE;
    public const REBECCA_PURPLE = BossBarColor::REBECCA_PURPLE;
    public const WHITE = BossBarColor::WHITE;

    /** @var string[] */
    public static array $colorNames = [
        self::PINK => "pink",
        self::BLUE => "blue",
        self::RED => "red",
        self::GREEN => "green",
        self::YELLOW => "yellow",
        self::PURPLE => "purple",
        self::REBECCA_PURPLE => "rebecca_purple",
        self::WHITE => "white",
    ];

    /**
     * Get all available boss bar colors.
     *
     * @return int[]
     */
    public static function getColors(): array
    {
        return array_keys(self::$colorNames);
    }

    /**
     * Get color constant by color name.
     *
     * @param string $colorName
     * @return int
     * @throws InvalidArgumentException
     */
    public static function getColorByName(string $colorName): int
    {
        $colorNameLower = strtolower($colorName);
        foreach (self::$colorNames as $color => $name) if ($colorNameLower === strtolower($name)) return $color;
        throw new InvalidArgumentException("Invalid color name specified: " . $colorName);
    }

    /**
     * Get color name by color constant.
     *
     * @param int $color
     * @return string
     * @throws InvalidArgumentException
     */
    public static function getNameByColor(int $color): string
    {
        if (isset(self::$colorNames[$color])) return self::$colorNames[$color];
        throw new InvalidArgumentException("Invalid color constant specified: " . $color);
    }

}